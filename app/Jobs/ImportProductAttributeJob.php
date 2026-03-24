<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

use App\Models\TransactionLog;
use App\Models\Product;
use App\Models\MeasurementType;

class ImportProductAttributeJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	protected $header;
	protected $chunk;
	protected $userRole;

	public function __construct($data)
	{
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->userRole = $data['userRole'];
	}

	public function handle()
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$measurementType = MeasurementType::with('units:measurement_type_id,id,name')->get()->toArray();
		$measurementTypeIdArray = [];
		foreach ($measurementType as $type) {
			foreach ($type['units'] as $unit) {
				$measurementTypeIdArray[$type['id']][$unit['name']] = $unit['id'];
			}
		}

		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$previousSuccessCount = $descArray["Success Count"] ?? 0;
		$previousFailedCount = $descArray["Failed Count"] ?? 0;

		$errorArray = [];
		$success = 0;
		$failed = 0;

		foreach ($this->chunk as $row) {
			$rowData = [];
			$rowError = [];

			/* Validate column count */
			if (count($this->header) === count($row)) {
				$rowData = array_combine($this->header, $row);
			} else {
				$rowError[] = 'Column mismatch: Row has incorrect data structure.';
				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Validate Product */
			if (empty($rowData['ID']) || !$product = Product::find($rowData['ID'])) {
				$rowError[] = 'Invalid Product ID or product not found.';
				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			if (!in_array($this->userRole, ['Admin', 'Super Admin']) && $product->approved == 1) {
				$rowError[] = "This product has already been approved and cannot be modified.";
				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Validate Required Attribute */
			$productCategoryAttributes = $product->productCategoryAttributes()
			->reject(fn($attribute) => $attribute['type'] === 'multiselect')
			->map(function ($attribute) {
				$attribute->measurement_type_id = $attribute->type === 'measurement'
				? $attribute->measurementUnits->first()->type->id ?? null
				: null;
				return $attribute;
			})
			->values();
			$productCategoryAttributeNames = $productCategoryAttributes->pluck('name')->toArray();

			/* Check if any required attributes are missing from the CSV header */
			$missingCategoryAttributes = array_diff($productCategoryAttributeNames, $this->header);
			if (!empty($missingCategoryAttributes)) {
				$rowError[] = "Missing category-specific attributes in CSV header: " . implode(', ', $missingCategoryAttributes);
				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Get non-null values from row, excluding fixed columns */
			$filteredRowAttributes = array_filter($rowData, function ($value, $key) {
				return !in_array($key, ['ID', 'SKU', 'Name']) &&
				!is_null($value) &&
				!str_ends_with($key, ' Measurement Unit');
			}, ARRAY_FILTER_USE_BOTH);

			/* Check for extra attributes not linked to this product category */
			$invalidAttributes = array_diff(array_keys($filteredRowAttributes), $productCategoryAttributeNames);
			if (!empty($invalidAttributes)) {
				$rowError[] = "Invalid attributes found for product category: " . implode(', ', $invalidAttributes);
				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Start Transaction */
			DB::beginTransaction();

			try {
				$attributeData = [];
				$attributeIds = [];

				foreach ($productCategoryAttributes as $index => $categoryAttribute) {
					$attributeValue = trim($rowData[$categoryAttribute->name] ?? '');

					if (!empty($attributeValue)) {
						if (in_array($categoryAttribute->type, ['text', 'number', 'select', 'price', 'measurement', 'toggle', 'date'])) {

							/* Ensure attribute value exists for select type */
							if ($categoryAttribute->type == 'select') {
								$attribute = $categoryAttribute->attributeValues()->firstOrCreate([
									'attribute_value' => $attributeValue
								]);

								if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
									$attribute->translateOrNew('en')->attribute_value_tr = $attributeValue;
									$attribute->save();
								}
							}

							/* Handle measurement unit */
							if ($categoryAttribute->type === 'measurement') {
								$columnName = $categoryAttribute->name . ' Measurement Unit';
								$attributeMeasurement = trim($rowData[$columnName] ?? '');

								$attributeMeasurementId = $measurementTypeIdArray[$categoryAttribute->measurement_type_id][$attributeMeasurement] ?? null;
								/* Both value and measurement unit are required */
								if (($attributeValue && !$attributeMeasurementId) || (!$attributeValue && $attributeMeasurementId)) {
									DB::rollBack();

									if (empty($attributeValue)) {
										$rowError[] = "Value not defined for attribute: {$categoryAttribute->name}";
									}
									if (empty($attributeMeasurement) || !$attributeMeasurementId) {
										$rowError[] = "Measurement Unit not defined or invalid for attribute: {$categoryAttribute->name}";
									}
									continue;
								}
							} else {
								$attributeMeasurementId = null;
							}

							/* Collect attribute data */
							$attributeData[] = [
								'product_id' => $product->id,
								'attribute_id' => $categoryAttribute->id,
								'attribute_value' => $attributeValue,
								'measurement_unit_id' => $attributeMeasurementId,
							];

							$attributeIds[] = $categoryAttribute->id;
						} else if (in_array($categoryAttribute->type, ['image', 'video', 'file'])) {
							if (Str::startsWith($attributeValue, ['http://', 'https://'])) {
								/* If the file is already on the HorecaStore S3, use it directly */
								if (Str::startsWith($attributeValue, [env('AWS_URL'), env('AWS_CACHE_URL')])) {
									$uploadedUrl = $attributeValue;
								} else {
									$uploadedUrl = null;
									$fileName = "{$categoryAttribute->type}_{$product->id}_{$categoryAttribute->id}";

									/* Extract file extension */
									$fileExtension = strtolower(pathinfo(parse_url($attributeValue, PHP_URL_PATH), PATHINFO_EXTENSION));

									switch ($categoryAttribute->type) {
										case 'image':
										$uploadedUrl = $this->uploadImageFromURL($attributeValue, $fileName);
										break;

										case 'video':
										$uploadedUrl = $this->uploadVideoFromURL($attributeValue, $fileName);
										break;

										case 'file':
										/* Only allow PDFs for file uploads */
										if ($fileExtension !== 'pdf') {
											$rowError[] = "Only PDF files are allowed for {$categoryAttribute->name}. Given file: '{$attributeValue}'";
											continue 2;
										}

										$uploadedUrl = $this->uploadFileFromURL($attributeValue, $fileName);
										break;
									}
								}

								/* Validate upload success */
								if ($uploadedUrl) {
									$attributeData[] = [
										'product_id' => $product->id,
										'attribute_id' => $categoryAttribute->id,
										'attribute_value' => $uploadedUrl
									];

									$attributeIds[] = $categoryAttribute->id;
								} else {
									$rowError[] = "Failed to upload {$categoryAttribute->type}: '{$attributeValue}' in {$categoryAttribute->name} field.";
									continue;
								}
							} else {
								$rowError[] = "Invalid URL '{$attributeValue}' in {$categoryAttribute->name} ({$categoryAttribute->type} field).";
								continue;
							}
						}
					}
				}

				/* Log errors only once at the end */
				if (!empty($rowError)) {
					$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
					$failed++;
					DB::rollBack();
				} else {
					/* Delete old attributes that are not in the provided list */
					$product->productAttributes()
					->whereNotIn('attribute_id', $attributeIds)
					->get()
					->each(function ($productAttribute) {
						$productAttribute->translations()->delete();
						$productAttribute->delete();
					});

					/* Insert or update new attributes with translations */
					foreach ($attributeData as $data) {
						$productAttribute = $product->productAttributes()->updateOrCreate(
							[
								'product_id' => $data['product_id'],
								'attribute_id' => $data['attribute_id']
							],
							[
								'attribute_value' => $data['attribute_value'],
								'measurement_unit_id' => $data['measurement_unit_id'],
							]
						);

						/* Handle translations for non-English locales */
						if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
							$productAttribute->translateOrNew('en')->attribute_value_tr = $data['attribute_value'];
							$productAttribute->save();
						}
					}

					DB::commit();
					$success++;
				}
			} catch (Throwable $e) {
				DB::rollBack();

				$rowError = [
					'Error processing row: ' . $e->getMessage(),
					'File: ' . $e->getFile(),
					'Line: ' . $e->getLine()
				];

				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
			}
		}

		/* Update Transaction Log */
		$this->updateTransactionLog($log, $success, $failed, $errorArray);
	}

	/**
	 * Log errors for a specific row.
	 */
	private function logError(&$rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, &$errorArray)
	{
		$errorArray[] = [
			"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
			"Error" => implode(' | ', $rowError),
		];
	}

	private function uploadImageFromURL(?string $url, ?string $fileBaseName): ?string
	{
		$s3Disk = Storage::disk('s3');

		/* Validate URL */
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			Log::error('Invalid URL provided: ' . $url);
			return null;
		}

		/* Fetch image content */
		$imageContents = file_get_contents($url);
		if ($imageContents === false || empty($imageContents)) {
			Log::error('Failed to download image from URL or content is empty: ' . $url);
			return null;
		}

		// /* Sanitize file name */
		// $fileNameWithQuery = basename(parse_url($url, PHP_URL_PATH));
		// $fileName = preg_replace('/\?.*/', '', $fileNameWithQuery);
		// $fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
		$fileExtension = 'webp'; /* Convert all to WebP */

		// if (empty($fileBaseName)) {
		// 	Log::error('Invalid file name extracted from URL: ' . $url);
		// 	return null;
		// }

		$imageUrl = '';

		try {
			/* Create image resource from content */
			$image = imagecreatefromstring($imageContents);
			if (!$image) {
				Log::error('Failed to create image from URL: ' . $url);
				return null;
			}

			/* Ensure image is in Truecolor format */
			if (imageistruecolor($image) === false) {
				imagepalettetotruecolor($image);
			}

			/* Save original image */
			$originalPath = env('STORAGE_ENV')."/attributes/{$fileBaseName}.{$fileExtension}";
			ob_start();
			imagewebp($image);
			$originalData = ob_get_clean();
			$s3Disk->put($originalPath, $originalData);
			$imageUrl = $s3Disk->url($originalPath);
			imagedestroy($image);
			Log::info('Uploaded Images: ' . $imageUrl);
			return $imageUrl;
		} catch (\Exception $e) {
			Log::error('S3 Upload Error: ' . $e->getMessage());
			return null;
		}
	}

	private function uploadVideoFromURL($fileUrl, $fileBaseName)
	{
		try {
			$s3Disk = Storage::disk('s3');
			/* Get video content from the given URL */
			$response = Http::get($fileUrl);

			if ($response->failed()) {
				return null;
			}

			$originalData = $response->body();
			$fileExtension = pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4';

			$originalPath = env('STORAGE_ENV')."/attributes/{$fileBaseName}.{$fileExtension}";

			/* Store the video on AWS S3 */
			$s3Disk->put($originalPath, $originalData);
			$imageUrl = $s3Disk->url($originalPath);
			Log::info('Uploaded Video: ' . $fileUrl);
			return $imageUrl;
		} catch (\Exception $e) {
			\Log::error('Video upload to S3 failed: ' . $e->getMessage());
			return null;
		}
	}

	private function uploadFileFromURL($fileUrl, $fileBaseName)
	{
		/* Validate the file URL */
		if (!filter_var($fileUrl, FILTER_VALIDATE_URL)) {
			return null;
		}

		/* Get the file extension */
		$pathInfo = pathinfo(parse_url($fileUrl, PHP_URL_PATH));
		$extension = strtolower($pathInfo['extension'] ?? '');

		/* Validate that the file is a PDF */
		if ($extension !== 'pdf') {
			return null;
		}

		/* Download the file from the URL */
		$response = Http::get($fileUrl);
		if (!$response->successful()) {
			return null;
		}

		/* Generate a unique filename */
		$fileName = env('STORAGE_ENV')."/attributes/{$fileBaseName}.pdf";

		/* Upload to S3 */
		Storage::disk('s3')->put($fileName, $response->body());

		/* Generate the S3 file URL */
		$s3Url = Storage::disk('s3')->url($fileName);

		return $s3Url;
	}

	/**
	 * Update Transaction Log.
	 */
	private function updateTransactionLog($log, $success, $failed, $errorArray)
	{
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$descArray["Success Count"] = ($descArray["Success Count"] ?? 0) + $success;
		$descArray["Failed Count"] = ($descArray["Failed Count"] ?? 0) + $failed;
		$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

		$log->update([
			'description' => json_encode($descArray),
		]);
	}

	/**
	 * Handle a job failure.
	 */
	public function failed(\Throwable $exception): void
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();

		if (!$log) {
			logger()->error('Transaction log not found for batch: ' . $this->batch()->id);
			return;
		}

		$jobName = class_basename($this);

		$errorDetails = [
			'job' => $jobName,
			'message' => $exception->getMessage(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		];

		logger()->error("{$jobName} failed", $errorDetails);

		$description = json_decode($log->description, true) ?? [];

		$description['Success Count'] = $description['Success Count'] ?? 0;
		$description['Failed Count'] = $description['Failed Count'] ?? 0;
		$description['Errors'] = $description['Errors'] ?? [];

		$description['Errors'][] = [
			'Row Number' => 'N/A',
			'Job' => $jobName,
			'Error' => $errorDetails['message'],
			'File' => $errorDetails['file'],
			'Line' => $errorDetails['line'],
		];

		TransactionLog::where('id', $log->id)->update([
			'status' => 'Failed',
			'description' => json_encode($description, JSON_UNESCAPED_UNICODE),
		]);
	}
}
