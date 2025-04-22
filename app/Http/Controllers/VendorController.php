<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
			->get([
				'id', 'name', 'country_id', 'email', 'contact_person', 'mobile_number', 'landline_number', 'dropshipping', 'website_link', 'type', 'warehouse_locations', 'credit_limit', 'net_terms', 'logo_url', 'business_licence_number', 'created_by', 'created_at'
			]);
		} else {
			$records = $recordsQuery->get([
				'id', 'name', 'country_id', 'email', 'contact_person', 'mobile_number', 'landline_number', 'dropshipping', 'website_link', 'type', 'warehouse_locations', 'credit_limit', 'net_terms', 'logo_url', 'business_licence_number', 'created_by', 'created_at'
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
	 *                 @OA\Property(property="description", type="string", example="Wholesale electronics supplier."),
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
			'name' => 'required|string',
			'country_id' => 'required|integer|exists:countries,id',
			'email' => 'required|email|unique:vendors,email',
			'contact_person' => 'required|string',
			'landline_number' => 'nullable|string',
			'mobile_number' => 'nullable|string',
			'description' => 'nullable|string',

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
		/* Handle File Uploads to S3 */
		$data['logo_url'] = $this->uploadImageToWebpS3FromFile($request, 'logo', env('STORAGE_ENV') . '/vendors/logos');
		$data['tax_certificate_url'] = $this->uploadPdfToS3FromFile($request, 'tax_certificate', env('STORAGE_ENV') . '/vendors/tax_certificates');
		$data['business_licence_url'] = $this->uploadPdfToS3FromFile($request, 'business_licence', env('STORAGE_ENV') . '/vendors/business_licences');
		unset($data['logo'], $data['tax_certificate'], $data['business_licence']);

		/* Implode array fields with | */
		$data['website_ids'] = isset($data['website_ids']) ? implode('|', $data['website_ids']) : null;
		$data['city_ids'] = isset($data['city_ids']) ? implode('|', $data['city_ids']) : null;
		$data['zipcode_ids'] = isset($data['zipcode_ids']) ? implode('|', $data['zipcode_ids']) : null;
		$data['warehouse_locations'] = isset($data['warehouse_locations']) ? implode('|', $data['warehouse_locations']) : null;

		$data['created_by'] = auth()->id();

		$vendor = Vendor::create($data);

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
				$request->merge([$field => explode(',', $request->$field)]);
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
	 * Display the specified resource.
	 */
	public function show(Vendor $vendor)
	{
		//
	}

	/**
	 * @OA\Put(
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
	 *                 @OA\Property(property="name", type="string", example="Updated Vendor"),
	 *                 @OA\Property(property="country_id", type="integer", example=1),
	 *                 @OA\Property(property="email", type="string", format="email", example="updated@example.com"),
	 *                 @OA\Property(property="contact_person", type="string", example="Updated Person"),
	 *                 @OA\Property(property="landline_number", type="string", example="000111222"),
	 *                 @OA\Property(property="mobile_number", type="string", example="999888777"),
	 *                 @OA\Property(property="description", type="string", example="Updated description."),
	 *                 @OA\Property(property="dropshipping", type="boolean", example=true),
	 *                 @OA\Property(property="website_link", type="string", example="https://newsite.com"),
	 *                 @OA\Property(property="domain", type="string", enum={"Horeca", "Rapid Supplies"}, example="Rapid Supplies"),
	 *                 @OA\Property(property="type", type="string", enum={"direct", "indirect"}, example="indirect"),
	 *                 @OA\Property(property="credit_limit", type="string", example="10000"),
	 *                 @OA\Property(property="net_terms", type="string", example="Net 60"),
	 *                 @OA\Property(property="business_licence_number", type="string", example="UPDATEDLIC987"),

	 *                 @OA\Property(
	 *                     property="website_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={2, 3}
	 *                 ),
	 *                 @OA\Property(
	 *                     property="city_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={4, 5}
	 *                 ),
	 *                 @OA\Property(
	 *                     property="zipcode_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={200, 201}
	 *                 ),
	 *                 @OA\Property(
	 *                     property="warehouse_locations",
	 *                     type="array",
	 *                     @OA\Items(type="string"),
	 *                     example={"Abu Dhabi", "Ajman"}
	 *                 ),

	 *                 @OA\Property(property="logo", type="file", format="binary"),
	 *                 @OA\Property(property="tax_certificate", type="file", format="binary"),
	 *                 @OA\Property(property="business_licence", type="file", format="binary")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Vendor updated successfully"),
	 *     @OA\Response(response=404, description="Vendor not found")
	 * )
	 */
	public function update(Request $request, $id)
	{
		$vendor = Vendor::findOrFail($id);

		$this->preprocessVendorRequest($request);

		$validated = $request->validate([
			'name' => 'required|string',
			'country_id' => 'required|integer|exists:countries,id',
			'email' => 'required|email|unique:vendors,email,' . $vendor->id,
			'contact_person' => 'required|string',
			'landline_number' => 'nullable|string',
			'mobile_number' => 'nullable|string',
			'description' => 'nullable|string',

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

		/* File uploads */
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

		/* Implode array fields */
		$data['website_ids'] = isset($data['website_ids']) ? implode('|', $data['website_ids']) : null;
		$data['city_ids'] = isset($data['city_ids']) ? implode('|', $data['city_ids']) : null;
		$data['zipcode_ids'] = isset($data['zipcode_ids']) ? implode('|', $data['zipcode_ids']) : null;
		$data['warehouse_locations'] = isset($data['warehouse_locations']) ? implode('|', $data['warehouse_locations']) : null;

		$data['updated_by'] = auth()->id();

		$vendor->update($data);

		return response()->json([
			'success' => true,
			'message' => __("msg_update"),
			'data'    => $vendor
		]);
	}


	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Vendor $vendor)
	{
		//
	}
}
