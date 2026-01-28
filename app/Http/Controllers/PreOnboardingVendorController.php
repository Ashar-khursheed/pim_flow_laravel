<?php

namespace App\Http\Controllers;

use App\Models\PreOnboardingVendor;
use App\Models\Country;
use App\Models\City;
use App\Models\Zipcode;
use App\Models\Category;
use Illuminate\Http\Request;

class PreOnboardingVendorController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/pre-onboarding-vendors",
	 *     summary="Get Pre Onboarding Vendor List",
	 *     description="Fetches a list of pre onboarding vendors. Supports search by each field.",
	 *     tags={"Pre Onboarding Vendors"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination. Starts from 1.", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", example="ABC", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="id", in="query", description="Search by vendor id", example="1", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="name", in="query", description="Search by vendor name", example="ABC", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="contact_person", in="query", description="Search by contact person", example="John", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="email", in="query", description="Search by email", example="abc@example.com", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="phone_number", in="query", description="Search by phone number", example="9876543210", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="type", in="query", description="Search by type", example="manufacturer", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="shipping_days", in="query", description="Search by shipping days", example="3-5", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="credit_limit", in="query", description="Search by credit limit", example="50000", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="credit_terms", in="query", description="Search by credit terms", example="Net 30", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="grade", in="query", description="Search by grade", example="A", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "email", "contact_person", "phone_number", "type", "shipping_days", "credit_limit", "credit_terms", "grade", "created_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		/* Apply search filters */
		$searchableColumns = [
			'id', 'name', 'contact_person', 'email', 'phone_number',
			'type', 'shipping_days', 'credit_limit', 'credit_terms', 'grade'
		];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'credit_limit', 'net_terms']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = PreOnboardingVendor::query();

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
				'id', 'name', 'contact_person', 'email', 'phone_number', 'country_id', 'category_ids', 'type', 'dropshipping', 'shipping_days', 'credit_limit', 'credit_terms', 'grade', 'product_demand_level', 'created_by', 'created_at'
			]);

			/* Transform records */
			$records->transform(function ($record) {
				/* product_demand_level count */
				$decoded = json_decode($record->product_demand_level, true);
				$record->product_demand_level_count = is_array($decoded) ? count($decoded) : 0;
				unset($record->product_demand_level);

				/* categories from category_ids */
				$categoryIds = array_filter(explode(',', $record->category_ids));
				$categories = Category::whereIn('id', $categoryIds)->pluck('name')->toArray();
				$record->categories = implode(' | ', $categories);
				unset($record->category_ids);

				/* country name */
				$record->country_name = $record->country->name ?? null;
				unset($record->country_id, $record->country);

				/* dropshipping label */
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
	 *     path="/api/pre-onboarding-vendors",
	 *     summary="Create a new pre onboarding vendor",
	 *     description="Creates a new pre onboarding vendor.",
	 *     tags={"Pre Onboarding Vendors"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "contact_person", "email", "phone_number", "country_id", "category_ids", "type"},
	 *
	 *             @OA\Property(property="name", type="string", example="Samsung"),
	 *             @OA\Property(property="contact_person", type="string", example="John Doe"),
	 *             @OA\Property(property="email", type="string", format="email", example="contact@samsung.com"),
	 *             @OA\Property(property="phone_number", type="string", example="+1234567890"),
	 *             @OA\Property(property="country_id", type="integer", example=1),

	 *             @OA\Property(
	 *                 property="city_ids",
	 *                 type="array",
	 *                 @OA\Items(type="integer"),
	 *                 example={1,2,3}
	 *             ),
	 *             @OA\Property(
	 *                 property="zipcode_ids",
	 *                 type="array",
	 *                 @OA\Items(type="integer"),
	 *                 example={101,102}
	 *             ),
	 *             @OA\Property(
	 *                 property="category_ids",
	 *                 type="array",
	 *                 @OA\Items(type="integer"),
	 *                 example={10,11}
	 *             ),
	 *             @OA\Property(property="type", type="string", example="direct"),
	 *             @OA\Property(property="dropshipping", type="boolean", example=true),
	 *             @OA\Property(property="shipping_days", type="string", example="5-7 days"),
	 *             @OA\Property(property="credit_terms", type="string", example="Net 30"),
	 *             @OA\Property(property="credit_limit", type="string", example="50000"),
	 *             @OA\Property(property="grade", type="string", example="A")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */

	public function store(Request $request)
	{
		/* Validate request */
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'contact_person' => 'required|string|max:255',
			'email' => 'required|email:strict',
			'phone_number' => 'required',
			'country_id' => 'required|integer|exists:countries,id',

			'city_ids' => 'nullable|array',
			'city_ids.*' => 'integer|exists:cities,id',

			'zipcode_ids' => 'nullable|array',
			'zipcode_ids.*' => 'integer|exists:zipcodes,id',

			'category_ids' => 'required|array',
			'category_ids.*' => 'integer|exists:categories,id',

			'type' => 'required',
			'dropshipping' => 'nullable|boolean',
			'shipping_days' => 'nullable|string|max:255',
			'credit_terms' => 'nullable|string|max:255',
			'credit_limit' => 'nullable|string|max:255',
			'grade' => 'nullable|string|max:255',
		]);

		/* Create the vendor */
		$vendor = PreOnboardingVendor::create([
			'name' => $validated['name'],
			'contact_person' => $validated['contact_person'],
			'email' => $validated['email'],
			'phone_number' => $validated['phone_number'],
			'country_id' => $validated['country_id'],
			'city_ids' => isset($validated['city_ids']) ? implode(',', $validated['city_ids']) : null,
			'zipcode_ids' => isset($validated['zipcode_ids']) ? implode(',', $validated['zipcode_ids']) : null,
			'category_ids' => isset($validated['category_ids']) ? implode(',', $validated['category_ids']) : null,
			'type' => $validated['type'],
			'dropshipping' => $validated['dropshipping'] ?? null,
			'shipping_days' => $validated['shipping_days'] ?? null,
			'credit_terms' => $validated['credit_terms'] ?? null,
			'credit_limit' => $validated['credit_limit'] ?? null,
			'grade' => $validated['grade'] ?? null,
			'created_by' => auth()->id(),
		]);


		return response()->json([
			'success' => true,
			'message' => __("msg_create"),
			'data'    => $vendor
		], 201);
	}

	/**
	 * @OA\Get(
	 *     path="/api/pre-onboarding-vendors/{pre_onboarding_vendor_id}",
	 *     summary="Get pre onboarding vendor details",
	 *     description="Fetches pre onboarding vendor details based on the given pre onboarding vendor ID.",
	 *     tags={"Pre Onboarding Vendors"},
	 *     @OA\Parameter(
	 *         name="pre_onboarding_vendor_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the pre onboarding vendor",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$record = PreOnboardingVendor::find($id);
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

		/* category_ids => array of category objects */
		$categoryIds = array_filter(explode(',', $record->category_ids));
		$categories = Category::whereIn('id', $categoryIds)->get(['id', 'name']);
		$record->categories = $categories;
		unset($record->category_ids);

		$record->product_demand_level = $record->product_demand_level && json_validate($record->product_demand_level) ? json_decode($record->product_demand_level, true) : [];

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_dtl"),
			'data' => $record
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/pre-onboarding-vendors/{id}",
	 *     summary="Update an existing pre onboarding vendor",
	 *     description="Updates an pre onboarding vendor's details.",
	 *     tags={"Pre Onboarding Vendors"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the pre onboarding vendor to update",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "contact_person", "email", "phone_number", "country_id", "category_ids", "type"},
	 *             @OA\Property(property="name", type="string", example="Samsung"),
	 *             @OA\Property(property="contact_person", type="string", example="John Doe"),
	 *             @OA\Property(property="email", type="string", example="john@example.com"),
	 *             @OA\Property(property="phone_number", type="string", example="+123456789"),
	 *             @OA\Property(property="country_id", type="integer", example=1),
	 *             @OA\Property(property="category_ids", type="array", @OA\Items(type="integer"), example={1,2,3}),
	 *             @OA\Property(property="type", type="string", example="direct"),
	 *             @OA\Property(
	 *                 property="city_ids",
	 *                 type="array",
	 *                 @OA\Items(type="integer"),
	 *                 example={1, 2, 3}
	 *             ),
	 *             @OA\Property(
	 *                 property="zipcode_ids",
	 *                 type="array",
	 *                 @OA\Items(type="integer"),
	 *                 example={101, 102}
	 *             ),
	 *             @OA\Property(property="dropshipping", type="boolean", example=true),
	 *             @OA\Property(property="shipping_days", type="string", example="5-7 days"),
	 *             @OA\Property(property="credit_terms", type="string", example="Net 30"),
	 *             @OA\Property(property="credit_limit", type="string", example="50000"),
	 *             @OA\Property(property="grade", type="string", example="A"),
	 *             @OA\Property(
	 *                 property="product_demand_level",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="product_name", type="string", example="LED TV"),
	 *                     @OA\Property(property="primary_keyword", type="string", example="smart tv"),
	 *                     @OA\Property(property="search_volume", type="integer", example=50000),
	 *                     @OA\Property(property="supplier_price", type="number", format="float", example=300.50),
	 *                     @OA\Property(property="competitor_price_online", type="number", format="float", example=350.00),
	 *                     @OA\Property(property="competitor_price_offline", type="number", format="float", example=340.00),
	 *                     @OA\Property(property="margin_auto_calculate", type="number", format="float", example=49.50)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{
		$vendor = PreOnboardingVendor::find($id);
		if (!$vendor) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		/* Validate request */
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'contact_person' => 'required|string|max:255',
			'email' => 'required|email:strict',
			'phone_number' => 'required',
			'country_id' => 'required|integer|exists:countries,id',

			'city_ids' => 'nullable|array',
			'city_ids.*' => 'integer|exists:cities,id',

			'zipcode_ids' => 'nullable|array',
			'zipcode_ids.*' => 'integer|exists:zipcodes,id',

			'category_ids' => 'required|array',
			'category_ids.*' => 'integer|exists:categories,id',

			'type' => 'required',
			'dropshipping' => 'nullable|boolean',
			'shipping_days' => 'nullable|string|max:255',
			'credit_terms' => 'nullable|string|max:255',
			'credit_limit' => 'nullable|string|max:255',
			'grade' => 'nullable|string|max:255',
			'product_demand_level' => 'nullable|array',
		]);

		/* Update fields */
		$vendor->name = $validated['name'];
		$vendor->contact_person = $validated['contact_person'];
		$vendor->email = $validated['email'];
		$vendor->phone_number = $validated['phone_number'];
		$vendor->country_id = $validated['country_id'];
		$vendor->category_ids = implode(',', $validated['category_ids']);
		$vendor->type = $validated['type'];
		$vendor->dropshipping = $validated['dropshipping'] ?? null;
		$vendor->shipping_days = $validated['shipping_days'] ?? null;
		$vendor->credit_terms = $validated['credit_terms'] ?? null;
		$vendor->credit_limit = $validated['credit_limit'] ?? null;
		$vendor->grade = $validated['grade'] ?? null;

		/* Save city_ids and zipcode_ids as comma-separated strings */
		$vendor->city_ids = isset($validated['city_ids']) ? implode(',', $validated['city_ids']) : null;
		$vendor->zipcode_ids = isset($validated['zipcode_ids']) ? implode(',', $validated['zipcode_ids']) : null;

		/* Save product_demand_level as JSON */
		$vendor->product_demand_level = isset($validated['product_demand_level'])
		? json_encode($validated['product_demand_level'])
		: null;

		$vendor->save();

		return response()->json([
			'success' => true,
			'message' => __("msg_update"),
			'data' => $vendor
		]);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/pre-onboarding-vendors/{id}",
	 *     summary="Delete a pre onboarding vendor",
	 *     description="Deletes a pre onboarding vendor.",
	 *     operationId="deletePreOnboardingVendor",
	 *     tags={"Pre Onboarding Vendors"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the pre onboarding vendor to delete",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$record = PreOnboardingVendor::find($id);

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
}
