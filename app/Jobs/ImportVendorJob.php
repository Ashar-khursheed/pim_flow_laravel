<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

use App\Models\TransactionLog;
use App\Models\Country;
use App\Models\City;
use App\Models\Vendor;

class ImportVendorJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	protected $header;
	protected $chunk;
	protected $userId;
	protected $vendorFileFormatArray;

	public function __construct(array $data)
	{
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->userId = $data['userId'];
		$this->vendorFileFormatArray = $data['fileFormatArray'];
	}

	public function handle()
	{
		$countryIDNames = Country::pluck('name', 'id')->all();
		$cityIDNames = City::pluck('name', 'id')->all();
		$vendorNames = Vendor::pluck('name', 'id')->all();
		$vendorEmails = Vendor::pluck('email', 'id')->all();

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
				$rowError[] = "The data in this row is not compatible for import.";
				$rowError[] = "Column count: ".count($this->header);
				$rowError[] = "Row data count: ".count($row);
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError)
				];
				$failed++;
				continue;
			}

			foreach ($this->vendorFileFormatArray as $headerKey => $variableName) {
				if (array_key_exists($headerKey, $rowData)) {
					${$variableName} = trim($rowData[$headerKey]);
				}
			}

			/* Required data validation */
			if (empty($name) || empty($country) || empty($email) || empty($contact_person)) {
				if (empty($name)) {
					$rowError[] = 'Name is missing';
				}
				if (empty($country)) {
					$rowError[] = 'Country is missing';
				}
				if (empty($email)) {
					$rowError[] = 'Email is missing';
				}
				if (empty($contact_person)) {
					$rowError[] = 'Contact person is missing';
				}
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			if (!empty($id)) {
				$vendor = Vendor::find($id);
				if (!$vendor) {
					$rowError[] = 'Vendor does not exist with the given ID.';
					$errorArray[] = [
						"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
						"Error" => implode(' | ', $rowError),
					];
					$failed++;
					continue;
				}

				/* Check if name is changed and is already taken by another vendor */
				if (!empty($vendor->name) && $vendor->name !== $name && in_array($name, $vendorNames)) {
					$existingId = array_search($name, $vendorNames);
					$rowError[] = "Vendor name already exists with ID: $existingId.";
					$errorArray[] = [
						"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
						"Error" => implode(' | ', $rowError),
					];
					$failed++;
					continue;
				}

				/* Check if email is changed and is already taken by another vendor */
				if (!empty($vendor->email) && $vendor->email !== $email && in_array($email, $vendorEmails)) {
					$existingId = array_search($email, $vendorEmails);
					$rowError[] = "Vendor email already exists with ID: $existingId.";
					$errorArray[] = [
						"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
						"Error" => implode(' | ', $rowError),
					];
					$failed++;
					continue;
				}
			} else {
				$vendor = new Vendor();

				/* Check if Name already exists in the database */
				if (!empty($name) && in_array($name, $vendorNames)) {
					$existingId = array_search($name, $vendorNames);
					$rowError[] = "Vendor name already exists with ID: $existingId.";
					$errorArray[] = [
						"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
						"Error" => implode(' | ', $rowError),
					];
					$failed++;
					continue;
				}

				/* Check if Email already exists in the database */
				if (!empty($email) && in_array($email, $vendorEmails)) {
					$existingId = array_search($email, $vendorEmails);
					$rowError[] = "Vendor email already exists with ID: $existingId.";
					$errorArray[] = [
						"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
						"Error" => implode(' | ', $rowError),
					];
					$failed++;
					continue;
				}
			}

			if (!empty($country)) {
				$country = trim($country);
				$matchedID = array_search($country, $countryIDNames);

				if ($matchedID !== false) {
					$country_id = $matchedID;
				} else {
					$rowError[] = "Country \"$country\" does not exist.";
				}
			}

			if (!empty($cities)) {
				$cityArray = explode('|', $cities);
				$cityIDs = [];

				foreach ($cityArray as $city) {
					$city = trim($city);
					$matchedID = array_search($city, $cityIDNames);

					if ($matchedID !== false) {
						$cityIDs[] = $matchedID;
					} else {
						$rowError[] = "City \"$city\" does not exist.";
					}
				}

				if (empty($rowError)) {
					$city_ids = implode(',', $cityIDs);
				}
			}

			if ($dropshipping !== '' && (!is_numeric($dropshipping) || !in_array($dropshipping, [0, 1]))) {
				$rowError[] = "Dropshipping should be numeric and either 1 for Yes, or 0 for No.";
			} else {
				$dropshipping = $dropshipping !== '' ? (int) $dropshipping : null;
			}

			if ($domain !== '' && (!is_numeric($domain) || !in_array($domain, [1, 2]))) {
				$rowError[] = "Domain should be numeric and either 1 for 'Horeca', or 2 for 'Rapid Supplies'.";
			} else {
				$domain = ($domain == 1) ? 'Horeca' : 'Rapid Supplies';
			}

			if ($type !== '' && (!is_numeric($type) || !in_array($type, [1, 2]))) {
				$rowError[] = "Type should be numeric and either 1 for 'direct', or 2 for 'indirect'.";
			} else {
				$type = ($type == 1) ? 'direct' : 'indirect';
			}

			if (!$rowError) {
				$fileFields = [
					'Logo URL' => $logo_url,
					'Tax Certificate URL' => $tax_certificate_url,
					'Business Licence URL' => $business_licence_url
				];
				foreach ($fileFields as $fieldName => $fieldValue) {
					if (!empty($fieldValue) && Str::startsWith($fieldValue, ['http://', 'https://'])) {
						/* Skip if already on HorecaStore S3 */
						$uploadedUrl = $fieldValue;
						if (!Str::startsWith($fieldValue, env('AWS_URL'))) {
							$fileExtension = strtolower(pathinfo(parse_url($fieldValue, PHP_URL_PATH), PATHINFO_EXTENSION));

							if ($fieldName === 'Logo URL') {
								if (!in_array($fileExtension, ['png', 'webp'])) {
									$rowError[] = "Only PNG or WEBP files are allowed for {$fieldName}. Provided file: '{$fieldValue}'";
									continue;
								}
								$uploadedUrl = $this->uploadImageFromURL($fieldValue, env('STORAGE_ENV') . '/vendors/logos');

							} else if ($fieldName === 'Tax Certificate URL' || $fieldName === 'Business Licence URL') {
								if ($fileExtension !== 'pdf') {
									$rowError[] = "Only PDF files are allowed for {$fieldName}. Provided file: '{$fieldValue}'";
									continue;
								}

								$folder = ($fieldName === 'Tax Certificate URL') ? '/vendors/tax_certificates' : '/vendors/business_licences';

								$pdfType = ($fieldName === 'Tax Certificate URL') ? 'tax_certificate' : 'business_licence';

								$uploadedUrl = $this->uploadPdfFromURL($fieldValue, $pdfType, env('STORAGE_ENV') . $folder);
							}
						}

						if ($fieldName === 'Logo URL') {
							$logo_url = $uploadedUrl;
						} else if ($fieldName === 'Tax Certificate URL') {
							$tax_certificate_url = $uploadedUrl;
						} else if ($fieldName === 'Business Licence URL') {
							$business_licence_url = $uploadedUrl;
						}
					}
				}
			}

			if ($rowError) {
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			DB::beginTransaction();

			try {
				/*************/
				DB::commit();

				$vendor->name = $name;
				$vendor->country_id = $country_id;
				$vendor->email = $email;
				$vendor->contact_person = $contact_person;
				$vendor->landline_number = $landline_number;
				$vendor->mobile_number = $mobile_number;
				$vendor->address = $address;
				$vendor->city_ids = $city_ids;
				$vendor->zipcode = $zipcode;
				$vendor->dropshipping = $dropshipping;
				$vendor->website_link = $website_link;
				$vendor->domain = $domain;
				$vendor->type = $type;
				$vendor->credit_limit = $credit_limit;
				$vendor->net_terms = $net_terms;
				$vendor->logo_url = $logo_url;
				$vendor->tax_certificate_url = $tax_certificate_url;
				$vendor->business_licence_number = $business_licence_number;
				$vendor->business_licence_url = $business_licence_url;
				$vendor->created_by = $this->userId;
				$vendor->created_at = now();
				$vendor->updated_at = now();
				$vendor->save();

				$vendorNames[$vendor->id] = $name;
				$vendorEmails[$vendor->id] = $email;

				$success++;
			} catch (\Exception $e) {
				DB::rollBack();

				$rowError[] = 'Error processing row: ' . $e->getMessage();
				$rowError[] = 'File: ' . $e->getFile();
				$rowError[] = 'Line: ' . $e->getLine();
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
			}
		}

		/* Update Transaction Log */
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$descArray["Success Count"] = $descArray["Success Count"] + $success;
		$descArray["Failed Count"] = $descArray["Failed Count"] + $failed;
		$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

		TransactionLog::where('id', $log->id)->update([
			'description' => json_encode($descArray),
		]);
	}

	private function uploadImageFromURL(string $url, string $pathPrefix)
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

		$fileNameWithQuery = basename(parse_url($url, PHP_URL_PATH));
		$fileName = preg_replace('/\?.*/', '', $fileNameWithQuery);
		$fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
		$fileExtension = 'webp'; // Convert all to WebP

		if (empty($fileBaseName)) {
			Log::error('Invalid file name extracted from URL: ' . $url);
			return null;
		}

		$fileExtension = 'webp';
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
			$originalPath = "{$pathPrefix}/{$fileBaseName}.{$fileExtension}";
			ob_start();
			imagewebp($image);
			$originalData = ob_get_clean();
			$s3Disk->put($originalPath, $originalData);
			$imageUrl = $s3Disk->url($originalPath);
			imagedestroy($image);

			return $imageUrl;
		} catch (\Exception $e) {
			Log::error('S3 Upload Error: ' . $e->getMessage());
			return null;
		}
	}

	private function uploadPdfFromURL($fileUrl, $pdfType, $pathPrefix)
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

		/* Generate a unique file name */
		$fileName = "{$pathPrefix}/{$pdfType}_" . time() . ".pdf";

		/* Upload to S3 */
		Storage::disk('s3')->put($fileName, $response->body());

		/* Generate the S3 file URL */
		return Storage::disk('s3')->url($fileName);
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