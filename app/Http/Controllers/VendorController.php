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
use App\Models\VendorContact;

use App\Jobs\ImportVendorJob;
use App\Services\CsvImporterService;
use App\Repository\ExcelRepository;
use App\Services\ExcelImporterService;

use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/vendors",
	 *     summary="Get Vendor List",
	 *     description="Fetches a list of vendors. Supports search by each field.",
	 *     tags={"Vendors"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", example="ABC", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="id", in="query", description="Search by vendor id", example="1", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="name", in="query", description="Search by vendor name", example="ABC", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="email", in="query", description="Search by email", example="vendor@example.com", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="contact_person", in="query", description="Search by contact person", example="John Doe", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="mobile_number", in="query", description="Search by mobile number", example="1234567890", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="landline_number", in="query", description="Search by landline number", example="0111234567", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="website_link", in="query", description="Search by website link", example="http://example.com", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="type", in="query", description="Search by type", example="manufacturer", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="business_licence_number", in="query", description="Search by business licence number", example="LIC1234", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "email", "contact_person", "mobile_number", "landline_number", "website_link", "type", "business_licence_number", "credit_limit", "net_terms", "created_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		/* Dynamic search filters */
		$searchableColumns = [
			'id', 'name', 'email', 'contact_person', 'mobile_number', 'landline_number', 'website_link', 'type', 'business_licence_number'
		];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'credit_limit', 'net_terms']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Vendor::query();

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$recordsQuery->with(['country:id,name', 'creator:id,first_name,last_name']);

			/* Apply global or column-specific filters */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						$q->orWhere($col, 'LIKE', '%' . $search . '%');
					}
				});
			} else {
				foreach ($searchableColumns as $col) {
					if ($request->filled($col)) {
						$recordsQuery->where($col, 'LIKE', '%' . $request->input($col) . '%');
					}
				}
			}

			/* Apply sorting */
			$recordsQuery->orderBy($sortBy, $sortDir);

			/* Clone query for counting */
			$totalRecords = (clone $recordsQuery)->count();
			$length = (int) $request->input('length');
			$totalPages = (int) ceil($totalRecords / $length);

			$page = (int) $request->input('page');
			/* If requested page exceeds total pages (after search), fallback to page 1 */
			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get([
				'id', 'name', 'country_id', 'zipcode', 'email', 'contact_person', 'mobile_number', 'landline_number', 'dropshipping', 'website_link', 'type', 'warehouse_locations', 'credit_limit', 'net_terms', 'logo_url', 'business_licence_number', 'created_by', 'created_at'
			]);

			/* Add country_name and created_by */
			$records->transform(function ($record) {
				$record->country_name = $record->country->name ?? null;
				unset($record->country_id, $record->country);

				$record->dropshipping = $record->dropshipping == 1 ? 'Yes' : 'No';

				$record->created_by = $record->creator->name ?? null;
				unset($record->creator);

				return $record;
			});
		} else {
			$records = $recordsQuery->orderBy('name', 'asc')->get([
				'id', 'name'
			]);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

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
	 *                 required={"name", "country_id"},
	 *
	 *                 @OA\Property(property="name", type="string", example="ABC Supplier"),
	 *                 @OA\Property(property="country_id", type="integer", example=1),
	 *
	 *                 @OA\Property(
	 *                     property="contacts",
	 *                     type="array",
	 *                     @OA\Items(
	 *                         type="object",
	 *                         required={"type", "name"},
	 *                         @OA\Property(property="type", type="string", example="Marketing"),
	 *                         @OA\Property(property="name", type="string", example="John Doe"),
	 *                         @OA\Property(property="mobile_number", type="string", example="9876543210"),
	 *                         @OA\Property(property="email", type="string", format="email", example="contact@example.com")
	 *                     ),
	 *                     description="List of vendor contacts (type, name, mobile, email)"
	 *                 ),
	 *
	 *                 @OA\Property(
	 *                     property="website_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={10, 11}
	 *                 ),
	 *                 @OA\Property(property="address", type="string", example="ABC STreet Houston"),
	 *                 @OA\Property(property="city_id", type="integer", example=1, description="City ID"),
	 *                 @OA\Property(property="zipcode", type="string", example="11211"),
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

		// $request->contacts = is_array($request->contacts) ? $request->contacts : json_decode($request->contacts, true);
		if (!is_array($request->contacts)) {
			$decoded = json_decode($request->contacts, true);

			/* If the decoded array contains stringified JSON objects, decode them too */
			if (is_array($decoded)) {
				foreach ($decoded as &$item) {
					if (is_string($item)) {
						$maybeObject = json_decode($item, true);
						if (json_last_error() === JSON_ERROR_NONE && is_array($maybeObject)) {
							$item = $maybeObject;
						}
					}
				}
				$request->merge(['contacts' => $decoded]);
			}
		}

		// dd($request->all());
		$validated = $request->validate([
			'name' => 'required|string|unique:vendors,name',
			'country_id' => 'required|integer|exists:countries,id',
			// 'email' => 'required|email:strict|unique:vendors,email',
			// 'contact_person' => 'required|string',
			// 'landline_number' => 'nullable|string',
			// 'mobile_number' => 'nullable|string',

			'contacts' => 'required|array|min:1',
			'contacts.*.type' => 'required|string',
			'contacts.*.name' => 'required|string',
			'contacts.*.mobile_number' => 'nullable|string',
			'contacts.*.email' => 'nullable|email:strict',

			'website_ids' => 'nullable|array',
			'website_ids.*' => 'integer|exists:websites,id',

			'address' => 'nullable|string',
			'city_id' => 'nullable|integer|exists:cities,id',
			'zipcode' => 'nullable|string',

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
		unset($data['contacts']);

		$preOnboardingVendorID = $validated['pre_onboarding_vendor_id'] ?? null;
		unset($data['pre_onboarding_vendor_id']);

		/* Handle File Uploads to S3 */
		$data['logo_url'] = uploadImageToWebpS3FromFile($request, 'logo', env('STORAGE_ENV') . '/vendors/logos');
		$data['tax_certificate_url'] = $this->uploadPdfToS3FromFile($request, 'tax_certificate', env('STORAGE_ENV') . '/vendors/tax_certificates');
		$data['business_licence_url'] = $this->uploadPdfToS3FromFile($request, 'business_licence', env('STORAGE_ENV') . '/vendors/business_licences');
		unset($data['logo'], $data['tax_certificate'], $data['business_licence']);

		/* Implode array fields with | */
		$data['website_ids'] = isset($data['website_ids']) ? implode(',', $data['website_ids']) : null;
		$data['warehouse_locations'] = isset($data['warehouse_locations']) ? implode('|', $data['warehouse_locations']) : null;

		$data['created_by'] = auth()->id();

		$vendor = Vendor::create($data);
		if ($vendor) {
			/* Save contacts (unique by type and name) */
			foreach ($validated['contacts'] as $contact) {
				$existingContact = VendorContact::where('vendor_id', $vendor->id)
				->where('type', $contact['type'])
				->where('name', $contact['name'])
				->first();

				if (!$existingContact) {
					$contact['vendor_id'] = $vendor->id;
					VendorContact::create($contact);
				}
			}

			/* Clean up pre-onboarding */
			if ($preOnboardingVendorID) {
				$record = PreOnboardingVendor::find($preOnboardingVendorID);
				if ($record) {
					$record->delete();
				}
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
		$fieldsToExplode = ['website_ids', 'warehouse_locations'];

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

	private function uploadPdfToS3FromFile(Request $request, string $key, string $pathPrefix)
	{
		if (!$request->hasFile($key) || !$request->file($key)->isValid()) {
			return null;
		}

		try {
			$file = $request->file($key);
			if ($file->getClientOriginalExtension() !== 'pdf') {
				return null;
			}

			$filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
			$uniqueName = $filename . '_' . time() . '.pdf';
			$path = "{$pathPrefix}/{$uniqueName}";

			Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

			return Storage::disk('s3')->url($path);
		} catch (\Exception $e) {
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
		$record = Vendor::with([
			'country:id,name',
			'city:id,name'
		])->find($id);
		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		$record->website_ids = $record->website_ids ? explode(',', $record->website_ids) : [];
		$record->websites = Website::whereIn('id', $record->website_ids)->select('id', 'name')->get();
		unset($record->website_ids);

		$record->warehouse_locations = $record->warehouse_locations ? explode('|', $record->warehouse_locations) : [];

		/* Contacts (new structure) */
		$record->load(['vendorContacts' => function ($q) {
			$q->select('vendor_id', 'type', 'name', 'email', 'mobile_number');
		}]);

		$record->contacts = $record->vendorContacts;
		unset($record->vendorContacts);

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
	 *                 required={"_method", "name", "country_id"},
	 *                 @OA\Property(property="_method", type="string", example="PUT"),
	 *
	 *                 @OA\Property(property="name", type="string", example="ABC Supplier"),
	 *                 @OA\Property(property="country_id", type="integer", example=1),
	 *
	 *                 @OA\Property(
	 *                     property="contacts",
	 *                     type="array",
	 *                     @OA\Items(
	 *                         type="object",
	 *                         required={"type", "name"},
	 *                         @OA\Property(property="type", type="string", example="Marketing"),
	 *                         @OA\Property(property="name", type="string", example="John Doe"),
	 *                         @OA\Property(property="mobile_number", type="string", example="9876543210"),
	 *                         @OA\Property(property="email", type="string", format="email", example="contact@example.com")
	 *                     ),
	 *                     description="List of vendor contacts (type, name, mobile, email)"
	 *                 ),
	 *
	 *                 @OA\Property(
	 *                     property="website_ids",
	 *                     type="array",
	 *                     @OA\Items(type="integer"),
	 *                     example={10, 11}
	 *                 ),
	 *                 @OA\Property(property="address", type="string", example="ABC STreet Houston"),
	 *                 @OA\Property(property="city_id", type="integer", example=1, description="City ID"),
	 *                 @OA\Property(property="zipcode", type="string", example="11211"),
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

		$this->preprocessVendorRequest($request);

		if (!is_array($request->contacts)) {
			$decoded = json_decode($request->contacts, true);

			/* If the decoded array contains stringified JSON objects, decode them too */
			if (is_array($decoded)) {
				foreach ($decoded as &$item) {
					if (is_string($item)) {
						$maybeObject = json_decode($item, true);
						if (json_last_error() === JSON_ERROR_NONE && is_array($maybeObject)) {
							$item = $maybeObject;
						}
					}
				}
				$request->merge(['contacts' => $decoded]);
			}
		}

		$validated = $request->validate([
			'name' => 'required|string|unique:vendors,name,' . $id,
			'country_id' => 'required|integer|exists:countries,id',

			// 'email' => 'required|email:strict|unique:vendors,email,' . $id,
			// 'contact_person' => 'required|string',
			// 'landline_number' => 'nullable|string',
			// 'mobile_number' => 'nullable|string',

			'contacts' => 'required|array|min:1',
			'contacts.*.type' => 'required|string',
			'contacts.*.name' => 'required|string',
			'contacts.*.mobile_number' => 'nullable|string',
			'contacts.*.email' => 'nullable|email:strict',

			'website_ids' => 'nullable|array',
			'website_ids.*' => 'integer|exists:websites,id',

			'address' => 'nullable|string',
			'city_id' => 'nullable|integer|exists:cities,id',
			'zipcode' => 'nullable|string',

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
		unset($data['contacts']);

		/* Handle File Uploads to S3 */
		if ($request->hasFile('logo')) {
			$data['logo_url'] = uploadImageToWebpS3FromFile($request, 'logo', env('STORAGE_ENV') . '/vendors/logos');
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
		$data['warehouse_locations'] = isset($data['warehouse_locations']) ? implode('|', $data['warehouse_locations']) : null;

		$data['updated_by'] = auth()->id();

		$vendor->update($data);

		$vendor->vendorContacts()->delete();

		foreach ($validated['contacts'] as $contact) {
			$vendor->vendorContacts()->create([
				'type' => $contact['type'],
				'name' => $contact['name'],
				'mobile_number' => $contact['mobile_number'] ?? null,
				'email' => $contact['email'] ?? null,
			]);
		}

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

		/* Delete vendor contacts first */
		$record->vendorContacts()->delete();

		/* Then delete the vendor */
		$record->delete();

		return response()->json([
			'success' => true,
			'message' => __("msg_dlt")
		], 200);
	}

	/**
	 * @OA\Post(
	 *     path="/api/vendors/import",
	 *     summary="Import vendors from an excel file",
	 *     tags={"Vendors"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="xlsx file (.xlsx) max 2MB")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Imported successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request, ExcelImporterService $excelImporter)
	{
		/* Validate request data */
		$request->validate([
			'upload_file' => 'required|file|mimes:xlsx,xls|max:2048',
		]);

		try {
			$vendorFileFormatArray = [
				'Id' => 'id',
				'Name' => 'name',
				'Country' => 'country',
				'Email' => 'email',
				'Contact Person' => 'contact_person',
				'Landline Number' => 'landline_number',
				'Mobile Number' => 'mobile_number',
				'Address' => 'address',
				'City' => 'city',
				'Zipcode' => 'zipcode',
				'Dropshipping' => 'dropshipping',
				'Website Link' => 'website_link',
				'Domain' => 'domain',
				'Type' => 'type',
				'Credit Limit' => 'credit_limit',
				'Net Terms(In Days)' => 'net_terms',
				'Logo URL' => 'logo_url',
				'Tax Certificate URL' => 'tax_certificate_url',
				'Business Licence Number' => 'business_licence_number',
				'Business Licence URL' => 'business_licence_url'
			];

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$vendorFileFormatArray,
				'Vendor', /* Module name */
				config('app.website') . '_VENDOR', /* Job name */
				'Import Vendors', /* Batch name */
				ImportVendorJob::class
			);

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);
		} catch(\Exception $exception) {
			$error[] = 'Error: ' . $exception->getMessage();
			$error[] = 'File: ' . $exception->getFile();
			$error[] = 'Line: ' . $exception->getLine();
			return response()->json([
				'success' => false,
				'message' => $error
			]);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/vendors/export",
	 *     summary="Export vendor data to Excel",
	 *     tags={"Vendors"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"range_from", "range_to"},
	 *             @OA\Property(property="range_from", type="integer", minimum=1, example=1, description="Starting range (must be >= 1)"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and at most 2000 more)")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success",
	 *         @OA\MediaType(mediaType="application/json")
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request, ExcelRepository $excelRepo)
	{
		/* Validate request data */
		$request->validate([
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 2000),
		]);

		/* Fetch records with related data */
		$records = Vendor::with([
			'country:id,name',
			'city:id,name'
		])->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->orderBy('id', 'asc')
		->get();

		if ($records->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No records exist.'
			]);
		}

		/* Define Excel headers */
		$excelHeaders = [
			'Id',
			'Name',
			'Country',
			'Email',
			'Contact Person',
			'Landline Number',
			'Mobile Number',
			'Address',
			'City',
			'Zipcode',
			'Dropshipping',
			'Website Link',
			'Domain',
			'Type',
			'Credit Limit',
			'Net Terms(In Days)',
			'Logo URL',
			'Tax Certificate URL',
			'Business Licence Number',
			'Business Licence URL',
		];

		/* Prepare spreadsheet */
		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Vendors');

		/* Set headers */
		$excelRepo->setHeader($sheet, $excelHeaders);

		/* Fill data rows */
		$rowIndex = 2;
		foreach ($records as $record) {
			/* Process city names from comma-separated IDs */

			$excelRepo->writeRow($sheet, [
				$record->id,
				$record->name,
				$record->country->name ?? '',
				$record->email,
				$record->contact_person,
				$record->landline_number,
				$record->mobile_number,
				$record->address,
				$record->city->name ?? '',
				$record->zipcode,
				$record->dropshipping,
				$record->website_link,
				$record->domain ? ($record->domain === 'Horeca' ? 1 : 2) : null,
				$record->type ? ($record->type === 'direct' ? 1 : 2) : null,
				$record->credit_limit,
				$record->net_terms,
				$record->logo_url,
				$record->tax_certificate_url,
				$record->business_licence_number,
				$record->business_licence_url,
			], $rowIndex++);
		}

		$fileName = 'vendors_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}
}
