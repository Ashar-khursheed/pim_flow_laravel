<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\Vendor;
use App\Models\Country;
use App\Models\City;
use App\Models\Zipcode;
use App\Models\Website;
use App\Models\PreOnboardingVendor;
use App\Models\TransactionLog;

use App\Jobs\ImportVendorJob;

class VendorController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/vendors",
	 *     summary="Get Vendor List",
	 *     description="Fetches a list of vendors.",
	 *     tags={"Vendors"},
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination. Starts from 1.",
	 *         required=true,
	 *         example=1,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="length",
	 *         in="query",
	 *         description="Number of records per page.",
	 *         required=true,
	 *         example=20,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$recordsQuery = Vendor::with(['country:id,name', 'creator:id,first_name,last_name']);

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$page = (int) $request->input('page');
			$length = (int) $request->input('length');
			$totalRecords = $recordsQuery->count();
			$totalPages = ceil($totalRecords / $length);

			$records = $recordsQuery->offset(($page - 1) * $length)
			->limit($length)
			->orderBy('id', 'desc')
			->get([
				'id', 'name', 'country_id', 'email', 'contact_person', 'mobile_number', 'landline_number', 'dropshipping', 'website_link', 'type', 'warehouse_locations', 'credit_limit', 'net_terms', 'logo_url', 'business_licence_number', 'created_by', 'created_at'
			]);
		} else {
			$records = $recordsQuery->orderBy('name', 'asc')->get([
				'id', 'name'
			]);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		/* Add product_demand_level_count and category objects */
		$records->transform(function ($record) {
			$record->country_name = $record->country->name;
			unset($record->country_id);
			unset($record->country);

			$record->dropshipping = $record->dropshipping == 1 ? 'Yes' : 'No';

			$record->created_by = $record->creator->name;
			unset($record->creator);
			return $record;
		});

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_list"),
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/vendors",
	 *     summary="Create a new vendor",
	 *     tags={"Vendors"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "country_id", "email", "contact_person"},
	 *
	 *                 @OA\Property(property="name", type="string", example="ABC Supplier"),
	 *                 @OA\Property(property="country_id", type="integer", example=1),
	 *                 @OA\Property(property="email", type="string", format="email", example="vendor@example.com"),
	 *                 @OA\Property(property="contact_person", type="string", example="John Doe"),
	 *                 @OA\Property(property="landline_number", type="string", example="0123456789"),
	 *                 @OA\Property(property="mobile_number", type="string", example="9876543210"),
	 *
	 *                 @OA\Property(
	 *                     property="website_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={10, 11}
	 *                 ),
	 *                 @OA\Property(
	 *                     property="city_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={1, 2, 3}
	 *                 ),
	 *                 @OA\Property(
	 *                     property="zipcode_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={101, 102}
	 *                 ),
	 *
	 *                 @OA\Property(property="dropshipping", type="boolean", example=true),
	 *                 @OA\Property(property="website_link", type="string", example="https://example.com"),
	 *                 @OA\Property(property="domain", type="string", enum={"Horeca", "Rapid Supplies"}, example="Horeca"),
	 *                 @OA\Property(property="type", type="string", enum={"direct", "indirect"}, example="direct"),
	 *
	 *                 @OA\Property(
	 *                     property="warehouse_locations",
	 *                     type="array",
	 *                     @OA\Items(type="string"),
	 *                     example={"Dubai", "Sharjah"}
	 *                 ),
	 *
	 *                 @OA\Property(property="credit_limit", type="string", example="5000"),
	 *                 @OA\Property(property="net_terms", type="string", example="Net 30"),
	 *                 @OA\Property(property="business_licence_number", type="string", example="LIC1234567"),
	 *
	 *                 @OA\Property(property="logo", type="file", format="binary", description="Vendor logo (.webp, .png)"),
	 *                 @OA\Property(property="tax_certificate", type="file", format="binary", description="Tax Certificate (.pdf)"),
	 *                 @OA\Property(property="business_licence", type="file", format="binary", description="Business Licence (.pdf)")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Vendor created successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function store(Request $request)
	{
		$this->preprocessVendorRequest($request);
		$validated = $request->validate([
			'name' => 'required|string|unique:vendors,name',
			'country_id' => 'required|integer|exists:countries,id',
			'email' => 'required|email|unique:vendors,email',
			'contact_person' => 'required|string',
			'landline_number' => 'nullable|string',
			'mobile_number' => 'nullable|string',

			'website_ids' => 'nullable|array',
			'website_ids.*' => 'integer|exists:websites,id',

			'city_ids' => 'nullable|array',
			'city_ids.*' => 'integer|exists:cities,id',

			'zipcode_ids' => 'nullable|array',
			'zipcode_ids.*' => 'integer|exists:zipcodes,id',

			'city_ids' => 'nullable|array',
			'city_ids.*' => 'integer',
			'zipcode_ids' => 'nullable|array',
			'zipcode_ids.*' => 'integer',
			'dropshipping' => 'boolean',
			'website_link' => 'nullable|string',
			'domain' => 'in:Horeca,Rapid Supplies',
			'type' => 'in:direct,indirect',
			'warehouse_locations' => 'nullable|array',
			'warehouse_locations.*' => 'string',
			'credit_limit' => 'nullable|string',
			'net_terms' => 'nullable|string',
			'business_licence_number' => 'nullable|string',

			'logo' => 'nullable|file|mimes:webp,png',
			'tax_certificate' => 'nullable|file|mimes:pdf',
			'business_licence' => 'nullable|file|mimes:pdf',
		]);

		$data = $validated;
		$preOnboardingVendorID = $validated['pre_onboarding_vendor_id'] ?? null;
		unset($data['pre_onboarding_vendor_id']);

		/* Handle File Uploads to S3 */
		$data['logo_url'] = $this->uploadImageToWebpS3FromFile($request, 'logo', env('STORAGE_ENV') . '/vendors/logos');
		$data['tax_certificate_url'] = $this->uploadPdfToS3FromFile($request, 'tax_certificate', env('STORAGE_ENV') . '/vendors/tax_certificates');
		$data['business_licence_url'] = $this->uploadPdfToS3FromFile($request, 'business_licence', env('STORAGE_ENV') . '/vendors/business_licences');
		unset($data['logo'], $data['tax_certificate'], $data['business_licence']);

		/* Implode array fields with | */
		$data['website_ids'] = isset($data['website_ids']) ? implode(',', $data['website_ids']) : null;
		$data['city_ids'] = isset($data['city_ids']) ? implode(',', $data['city_ids']) : null;
		$data['zipcode_ids'] = isset($data['zipcode_ids']) ? implode(',', $data['zipcode_ids']) : null;
		$data['warehouse_locations'] = isset($data['warehouse_locations']) ? implode('|', $data['warehouse_locations']) : null;

		$data['created_by'] = auth()->id();

		$vendor = Vendor::create($data);
		if ($vendor && isset($preOnboardingVendorID)) {
			$record = PreOnboardingVendor::find($preOnboardingVendorID);
			if ($record) {
				$record->delete();
			}
		}

		return response()->json([
			'success' => true,
			'message' => __("msg_create"),
			'data'    => $vendor
		], 201);
	}

	private function preprocessVendorRequest(Request $request): void
	{
		$fieldsToExplode = ['website_ids', 'city_ids', 'zipcode_ids', 'warehouse_locations'];

		foreach ($fieldsToExplode as $field) {
			if ($request->filled($field) && is_string($request->$field)) {
				$values = collect(explode(',', $request->$field))
				->map(fn($item) => trim($item))
				->filter()
				->unique()
				->values()
				->toArray();
				$request->merge([$field => $values]);
			}
		}

		$request->merge([
			'dropshipping' => filter_var($request->input('dropshipping'), FILTER_VALIDATE_BOOLEAN),
		]);
	}

	private function uploadImageToWebpS3FromFile(Request $request, string $key, string $pathPrefix)
	{
		if (!$request->hasFile($key) || !$request->file($key)->isValid()) {
			return null;
		}

		try {
			$file = $request->file($key);
			$image = imagecreatefromstring(file_get_contents($file->getRealPath()));
			if (!$image) {
				Log::error('Failed to create image from file.');
				return null;
			}

			if (!imageistruecolor($image)) {
				imagepalettetotruecolor($image);
			}

			ob_start();
			imagewebp($image);
			$webpData = ob_get_clean();
			imagedestroy($image);

			$filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
			$uniqueName = $filename . '_' . time() . '.webp';
			$path = "{$pathPrefix}/{$uniqueName}";

			Storage::disk('s3')->put($path, $webpData);

			return Storage::disk('s3')->url($path);
		} catch (\Exception $e) {
			Log::error('uploadImageToWebpS3FromFile error: ' . $e->getMessage());
			return null;
		}
	}

	private function uploadPdfToS3FromFile(Request $request, string $key, string $pathPrefix)
	{
		if (!$request->hasFile($key) || !$request->file($key)->isValid()) {
			return null;
		}

		try {
			$file = $request->file($key);
			if ($file->getClientOriginalExtension() !== 'pdf') {
				Log::warning("Rejected non-PDF upload for key '{$key}'");
				return null;
			}

			$filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
			$uniqueName = $filename . '_' . time() . '.pdf';
			$path = "{$pathPrefix}/{$uniqueName}";

			Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

			return Storage::disk('s3')->url($path);
		} catch (\Exception $e) {
			Log::error("uploadPdfToS3FromFile error ({$key}): " . $e->getMessage());
			return null;
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/vendors/{vendor_id}",
	 *     summary="Get vendor details",
	 *     description="Fetches vendor details based on the given vendor ID.",
	 *     tags={"Vendors"},
	 *     @OA\Parameter(
	 *         name="vendor_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the vendor",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$record = Vendor::find($id);
		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		$record->country = Country::where('id', $record->country_id)->select('id', 'name')->first();
		unset($record->country_id);

		$record->city_ids = $record->city_ids ? explode(',', $record->city_ids) : [];
		$record->cities = City::whereIn('id', $record->city_ids)->select('id', 'name')->get();
		unset($record->city_ids);

		$record->zipcode_ids = $record->zipcode_ids ? explode(',', $record->zipcode_ids) : [];
		$record->zipcodes = Zipcode::whereIn('id', $record->zipcode_ids)->select('id', 'zip_code')->get();
		unset($record->zipcode_ids);

		$record->website_ids = $record->website_ids ? explode(',', $record->website_ids) : [];
		$record->websites = Website::whereIn('id', $record->website_ids)->select('id', 'name')->get();
		unset($record->website_ids);

		$record->warehouse_locations = $record->warehouse_locations ? explode('|', $record->warehouse_locations) : [];

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_dtl"),
			'data' => $record
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/vendors/{id}",
	 *     summary="Update an existing vendor",
	 *     tags={"Vendors"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Vendor ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"_method", "name", "country_id", "email", "contact_person"},
	 *                 @OA\Property(property="_method", type="string", example="PUT"),
	 *
	 *                 @OA\Property(property="name", type="string", example="ABC Supplier"),
	 *                 @OA\Property(property="country_id", type="integer", example=1),
	 *                 @OA\Property(property="email", type="string", format="email", example="vendor@example.com"),
	 *                 @OA\Property(property="contact_person", type="string", example="John Doe"),
	 *                 @OA\Property(property="landline_number", type="string", example="0123456789"),
	 *                 @OA\Property(property="mobile_number", type="string", example="9876543210"),
	 *
	 *                 @OA\Property(
	 *                     property="website_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={10, 11}
	 *                 ),
	 *                 @OA\Property(
	 *                     property="city_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={1, 2, 3}
	 *                 ),
	 *                 @OA\Property(
	 *                     property="zipcode_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={101, 102}
	 *                 ),
	 *
	 *                 @OA\Property(property="dropshipping", type="boolean", example=true),
	 *                 @OA\Property(property="website_link", type="string", example="https://example.com"),
	 *                 @OA\Property(property="domain", type="string", enum={"Horeca", "Rapid Supplies"}, example="Horeca"),
	 *                 @OA\Property(property="type", type="string", enum={"direct", "indirect"}, example="direct"),
	 *
	 *                 @OA\Property(
	 *                     property="warehouse_locations",
	 *                     type="array",
	 *                     @OA\Items(type="string"),
	 *                     example={"Dubai", "Sharjah"}
	 *                 ),
	 *
	 *                 @OA\Property(property="credit_limit", type="string", example="5000"),
	 *                 @OA\Property(property="net_terms", type="string", example="Net 30"),
	 *                 @OA\Property(property="business_licence_number", type="string", example="LIC1234567"),
	 *
	 *                 @OA\Property(property="logo", type="file", format="binary", description="Vendor logo (.webp, .png)"),
	 *                 @OA\Property(property="tax_certificate", type="file", format="binary", description="Tax Certificate (.pdf)"),
	 *                 @OA\Property(property="business_licence", type="file", format="binary", description="Business Licence (.pdf)")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Vendor updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     @OA\Response(response=404, description="Vendor not found")
	 * )
	 */
	public function update(Request $request, $id)
	{
		$vendor = Vendor::find($id);
		if (!$vendor) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		// dd($vendor->email, $request->email);

		$this->preprocessVendorRequest($request);

		$validated = $request->validate([
			'name' => 'required|string|unique:vendors,name,' . $id,
			'country_id' => 'required|integer|exists:countries,id',
			'email' => 'required|email|unique:vendors,email,' . $id,
			'contact_person' => 'required|string',
			'landline_number' => 'nullable|string',
			'mobile_number' => 'nullable|string',

			'website_ids' => 'nullable|array',
			'website_ids.*' => 'integer|exists:websites,id',

			'city_ids' => 'nullable|array',
			'city_ids.*' => 'integer|exists:cities,id',

			'zipcode_ids' => 'nullable|array',
			'zipcode_ids.*' => 'integer|exists:zipcodes,id',

			'dropshipping' => 'boolean',
			'website_link' => 'nullable|string',
			'domain' => 'in:Horeca,Rapid Supplies',
			'type' => 'in:direct,indirect',
			'warehouse_locations' => 'nullable|array',
			'warehouse_locations.*' => 'string',
			'credit_limit' => 'nullable|string',
			'net_terms' => 'nullable|string',
			'business_licence_number' => 'nullable|string',

			'logo' => 'nullable|file|mimes:webp,png',
			'tax_certificate' => 'nullable|file|mimes:pdf',
			'business_licence' => 'nullable|file|mimes:pdf',
		]);

		$data = $validated;

		/* Handle File Uploads to S3 */
		if ($request->hasFile('logo')) {
			$data['logo_url'] = $this->uploadImageToWebpS3FromFile($request, 'logo', env('STORAGE_ENV') . '/vendors/logos');
		}
		if ($request->hasFile('tax_certificate')) {
			$data['tax_certificate_url'] = $this->uploadPdfToS3FromFile($request, 'tax_certificate', env('STORAGE_ENV') . '/vendors/tax_certificates');
		}
		if ($request->hasFile('business_licence')) {
			$data['business_licence_url'] = $this->uploadPdfToS3FromFile($request, 'business_licence', env('STORAGE_ENV') . '/vendors/business_licences');
		}

		unset($data['logo'], $data['tax_certificate'], $data['business_licence']);

		/* Implode array fields with | */
		$data['website_ids'] = isset($data['website_ids']) ? implode(',', $data['website_ids']) : null;
		$data['city_ids'] = isset($data['city_ids']) ? implode(',', $data['city_ids']) : null;
		$data['zipcode_ids'] = isset($data['zipcode_ids']) ? implode(',', $data['zipcode_ids']) : null;
		$data['warehouse_locations'] = isset($data['warehouse_locations']) ? implode('|', $data['warehouse_locations']) : null;

		$data['updated_by'] = auth()->id();

		$vendor->update($data);

		return response()->json([
			'success' => true,
			'message' => __("msg_update"),
			'data'    => $vendor
		], 200);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/vendors/{id}",
	 *     summary="Delete a vendor",
	 *     description="Deletes a vendor.",
	 *     operationId="deleteVendor",
	 *     tags={"Vendors"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the vendor to delete",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$record = Vendor::find($id);

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			], 404);
		}

		/* Proceed with deletion */
		$record->delete();

		return response()->json([
			'success' => true,
			'message' => __("msg_dlt")
		], 200);
	}

	/**
	 * @OA\Post(
	 *     path="/api/vendors/import",
	 *     summary="Import vendors from an Excel file",
	 *     tags={"Vendors"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="CSV file (.csv) max 5MB")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request)
	{
		try {
			/* Validate request data */
			$request->validate([
				'upload_file' => 'required|file|mimes:csv,txt|max:5120',
			]);

			$file = $request->file('upload_file');

			$vendorFileFormatArray = [
				'Id' => 'id',
				'Name' => 'name',
				'Country' => 'country',
				'Email' => 'email',
				'Contact Person' => 'contact_person',
				'Landline Number' => 'landline_number',
				'Mobile Number' => 'mobile_number',
				'Cities(Separated By |)' => 'cities',
				'Dropshipping' => 'dropshipping',
				'Website Link' => 'website_link',
				'Domain' => 'domain',
				'Type' => 'type',
				'Credit Limit' => 'credit_limit',
				'Net Terms' => 'net_terms',
				'Logo URL' => 'logo_url',
				'Tax Certificate URL' => 'tax_certificate_url',
				'Business Licence Number' => 'business_licence_number',
				'Business Licence URL' => 'business_licence_url'
			];

			$requiredRowCount = count($vendorFileFormatArray);
			$requiredHeaderArray = array_keys($vendorFileFormatArray);

			$data = [];
			/* Open the CSV file and read its content */
			if (($handle = fopen($file, "r")) !== false) {
				/* Read the header */
				if (($header = fgetcsv($handle, 0, ",", '"', "\\")) !== false) {
					$header = array_map('trim', $header);

					if ($missingColumns = array_diff($requiredHeaderArray, $header)) {
						$columns = implode(', ', array_values($missingColumns));
						$missingCount = count($missingColumns);
						fclose($handle);
						return response()->json([
							'success' => false,
							'message' => $missingCount > 1 ? "The uploaded file has an incorrect header. $columns columns are missing." : "The uploaded file has an incorrect header. $columns column is missing."
						]);
					}
				}

				/* Continue reading and processing rows */
				$rowIndex = 2;
				while (($row = fgetcsv($handle, 0, ",", '"', "\\")) !== false) {
					/* Fix unquoted fields and escape special characters */
					$row = array_map(function ($value) {
						/* Add quotes around multiline fields */
						if (strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
							$value = '"' . str_replace('"', '""', $value) . '"';
						}

						/* Check if the value is UTF-8 encoded */
						if (!mb_check_encoding($value, 'UTF-8')) {
							/* Attempt to convert to UTF-8, fallback to ISO-8859-1 if detection fails */
							$value = @mb_convert_encoding($value, 'UTF-8', 'auto') ?: utf8_encode($value);
						}

						/* Remove invalid characters and trim spaces */
						$value = preg_replace('/[^\x20-\x7E\xA0-\xFF]/u', '', $value);
						return trim($value);
					}, $row);

					/* Skip blank rows */
					if (array_filter($row)) {
						if (count($row) != $requiredRowCount) {
							$message = "The data in row $rowIndex is not compatible for import.";

							return response()->json([
								'success' => false,
								'message' => $message
							]);
						}
						$data[] = $row;
					}
					$rowIndex++;
				}
				fclose($handle);
			}

			/* Get the total record count */
			$totalRecords = count($data);
			if ($totalRecords == 0) {
				return response()->json([
					'success' => false,
					'message' => "The uploaded CSV file does not contain any records. Please ensure the file has valid data and try again."
				]);
			}

			/* Chunk the data into manageable portions (e.g., 100 rows per chunk) */
			$chunkSize = 100;
			$chunks = array_chunk($data, $chunkSize);

			/* Start import process */
			$batch = Bus::batch([])
			->before(function (Batch $batch) use ($totalRecords) {
				$descArray = [
					"Total Count" => $totalRecords,
					"Success Count" => 0,
					"Failed Count" => 0,
					"Errors" => []
				];
				/* Save transaction log */
				$log = new TransactionLog();
				$log->module = "Vendor";
				$log->action = "Import";
				$log->identifier = $batch->id;
				$log->status = 'In-progress';
				$log->description = json_encode($descArray, JSON_UNESCAPED_UNICODE);
				$log->created_by = auth()->id() ?? null;
				$log->created_at = now();
				$log->save();
			})
			->finally(function (Batch $batch) {
				$log = TransactionLog::where('identifier', $batch->id)->first();
				TransactionLog::where('id', $log->id)->update([
					'status' => 'Completed',
				]);
			})
			->name("Vendor Import")
			->dispatch();

			/* Add jobs to the batch for processing chunks */
			foreach ($chunks as $chunk) {
				$data = [
					'vendorFileFormatArray' => $vendorFileFormatArray,
					'header' => $header,
					'chunk' => $chunk,
					'userId' => auth()->id()
				];

				$batch->options['queue'] = 'JOB4';
				$batch->add(new ImportVendorJob($data));
			}

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);
		} catch(\Exception $exception) {
			return response()->json([
				'success' => false,
				'message' => $exception->getMessage()
			]);
		}
	}
}
