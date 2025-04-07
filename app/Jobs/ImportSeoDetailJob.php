<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

use App\Models\TransactionLog;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Blog;
use App\Models\SeoManagement;
use App\Models\SeoSecondaryKeyword;

class ImportSeoDetailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $timeout = 43200;

	protected $header;
	protected $chunk;
	protected $userId;
	protected $seoFileFormatArray;

	public function __construct(array $data)
	{
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->userId = $data['userId'];
		$this->seoFileFormatArray = $data['seoFileFormatArray'];
	}

	public function handle()
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$previousSuccessCount = $descArray["Success Count"] ?? 0;
		$previousFailedCount = $descArray["Failed Count"] ?? 0;

		$errorArray = [];
		$success = 0;
		$failed = 0;

		$groupedPrimary = [];

		foreach ($this->chunk as $rowIndex => $row) {
			$rowData = [];
			$rowError = [];

			if (count($this->header) === count($row)) {
				$rowData = array_combine($this->header, $row);
			} else {
				$rowError[] = 'The data in this row is not compatible for import.';
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError)
				];
				$failed++;
				continue;
			}

			foreach ($this->seoFileFormatArray as $headerKey => $variableName) {
				if (array_key_exists($headerKey, $rowData)) {
					${$variableName} = trim($rowData[$headerKey]);
				}
			}

			// Required data validation
			if ((empty($relational_id) && empty($relational_name)) || empty($relational_type) || empty($url) || empty($primary_keyword) || empty($primary_monthly_search_volume) || empty($secondary_keyword) || empty($secondary_monthly_search_volume) || empty($title_tag) || empty($meta_title) || empty($meta_description)) {
				$rowError[] = 'One or more required fields are missing.';
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			if (!in_array($relational_type, ['Product', 'Category', 'Brand', 'Blog'])) {
				$rowError[] = "Invalid Relational Type. Must be one of 'Product', 'Category', 'Brand', 'Blog'.";
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			$model = match ($relational_type) {
				'Product' => Product::class,
				'Category' => Category::class,
				'Brand' => Brand::class,
				'Blog' => Blog::class,
			};

			try {
				if ($relational_id) {
					$exist = $model::findOrFail($relational_id);
				} elseif ($relational_name) {
					$exist = $model::where('relational_name', $relational_name)->firstOrFail();
				}
				$relational_id = $exist->id;
			} catch (ModelNotFoundException $e) {
				$rowError[] = "{$relational_type} does not exist for the given relational identifier.";
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			$primaryKey = $relational_id . '|' . $relational_type;

			$groupedPrimary[$primaryKey]['primary'] = [
				'relational_id' => $relational_id,
				'relational_type' => $relational_type,
				'url' => $url,
				'primary_keyword' => $primary_keyword,
				'primary_monthly_search_volume' => $primary_monthly_search_volume,
				'title_tag' => $title_tag,
				'meta_title' => $meta_title,
				'meta_description' => $meta_description,
				'internal_links' => $internal_links ?? null,
				'indexing' => $indexing ?? null,
				'og_title' => $og_title ?? null,
				'og_description' => $og_description ?? null,
				'og_image_url' => $og_image_url ?? null,
				'og_image_alt_text' => $og_image_alt_text ?? null,
				'og_image_name' => $og_image_name ?? null,
				'tags' => $tags ?? null,
			];

			$groupedPrimary[$primaryKey]['secondary'][] = [
				'secondary_keyword' => $secondary_keyword,
				'monthly_search_volume' => $secondary_monthly_search_volume,
			];

			$success++;
		}

		DB::beginTransaction();

		try {
			foreach ($groupedPrimary as $group) {
				$primaryData = $group['primary'];
				$seo = SeoManagement::updateOrCreate(
					[
						'relational_id' => $primaryData['relational_id'],
						'relational_type' => $primaryData['relational_type']
					],
					$primaryData
				);

				foreach ($group['secondary'] as $secondary) {
					SeoSecondaryKeyword::updateOrCreate(
						[
							'primary_keyword_id' => $seo->id,
							'secondary_keyword' => $secondary['secondary_keyword'],
						],
						['monthly_search_volume' => $secondary['monthly_search_volume']]
					);
				}
			}

			// Update Transaction Log
			$descArray["Success Count"] += $success;
			$descArray["Failed Count"] += $failed;
			$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

			$log->update([
				'description' => json_encode($descArray),
			]);

			DB::commit();
		} catch (\Throwable $e) {
			DB::rollBack();
		}
	}

	protected function getImageURLs(array $images): array
	{
		/* Ensure images are properly formatted and split if needed */
		$images = array_values(array_filter(
			array_map('trim', preg_split('/\s*,\s*/', implode(',', $images)))
		));

		Log::info('Image URLs before processing: ' . json_encode($images));

		foreach ($images as $key => $image) {
			if (Str::startsWith($image, 'https://horecastore-s3-storage.s3.us-west-1.amazonaws.com/')) {
				$images[$key] = $image;
			} else {
				if (Str::startsWith($image, ['http://', 'https://'])) {
					$uploadedImage = $this->uploadImageFromURL($image);
					$images[$key] = $uploadedImage;
				} else {
					Log::warning("Invalid image URL at index $key: " . $image);
					unset($images[$key]);
				}
			}
		}

		Log::info('Final Processed Images: ' . json_encode(array_values($images)));
		return array_values($images);
	}

	protected function uploadImageFromURL(?string $url): ?string
	{
		$s3Disk = Storage::disk('s3');

		// Validate URL
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			Log::error('Invalid URL provided: ' . $url);
			return null;
		}

		// Fetch image content
		$imageContents = file_get_contents($url);
		if ($imageContents === false || empty($imageContents)) {
			Log::error('Failed to download image from URL or content is empty: ' . $url);
			return null;
		}

		// Sanitize file name
		$fileNameWithQuery = basename(parse_url($url, PHP_URL_PATH));
		$fileName = preg_replace('/\?.*/', '', $fileNameWithQuery);
		$fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
		$fileExtension = 'webp'; // Convert all to WebP

		if (empty($fileBaseName)) {
			Log::error('Invalid file name extracted from URL: ' . $url);
			return null;
		}

		// Define sizes
		$sizes = [
			'thumb' => [150, 150],
			'medium' => [300, 300],
			'large' => [790, 510]
		];

		$imageUrl = '';

		try {
			// Create image resource from content
			$image = imagecreatefromstring($imageContents);
			if (!$image) {
				Log::error('Failed to create image from URL: ' . $url);
				return null;
			}

			/* Ensure image is in Truecolor format */
			if (imageistruecolor($image) === false) {
				imagepalettetotruecolor($image);
			}

			// Save original image
			$originalPath = env('STORAGE_ENV')."/products/{$fileBaseName}.{$fileExtension}";
			ob_start();
			imagewebp($image);
			$originalData = ob_get_clean();
			$s3Disk->put($originalPath, $originalData);
			$imageUrl = $s3Disk->url($originalPath);

			// Resize and save images
			foreach ($sizes as $sizeName => [$width, $height]) {
				$resizedImage = $this->resizeImageGD($image, $width, $height);
				if (!$resizedImage) {
					continue;
				}

				$resizedPath = env('STORAGE_ENV')."/products/{$fileBaseName}-{$width}x{$height}.{$fileExtension}";
				ob_start();
				imagewebp($resizedImage);
				$resizedData = ob_get_clean();
				$s3Disk->put($resizedPath, $resizedData);
				// $imageUrls[$sizeName] = $s3Disk->url($resizedPath);
			}

			imagedestroy($image);
			Log::info('Uploaded Images: ' . $imageUrl);
			return $imageUrl;
		} catch (\Exception $e) {
			Log::error('S3 Upload Error: ' . $e->getMessage());
			return null;
		}
	}

	protected function resizeImageGD($image, $newWidth, $newHeight)
	{
		$width = imagesx($image);
		$height = imagesy($image);

		// Create new image canvas with exact width & height
		$resizedImage = imagecreatetruecolor($newWidth, $newHeight);
		imagealphablending($resizedImage, false);
		imagesavealpha($resizedImage, true);
		$transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
		imagefill($resizedImage, 0, 0, $transparent);

		// Force resize without aspect ratio (stretching)
		imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

		return $resizedImage;
	}

	/**
	 * Handle a job failure.
	 */
	public function failed(Throwable $exception): void
	{
		$error = $exception->getMessage().$exception->getTraceAsString();
		logger(__("Product Import Error").': '.$error);
	}
}
