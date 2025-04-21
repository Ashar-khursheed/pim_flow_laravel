<?php

namespace App\Http\Controllers;

use App\Models\PreOnboardingVendor;
use Illuminate\Http\Request;

class PreOnboardingVendorController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/pre-onboarding-vendors",
	 *     summary="Get Pre Onboarding Vendor List",
	 *     description="Fetches a list of pre onboarding vendors.",
	 *     tags={"Pre Onboarding Vendors"},
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
		$records = PreOnboardingVendor::query();

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$page = (int) $request->input('page');
			$length = (int) $request->input('length');
			$totalRecords = $records->count();
			$totalPages = ceil($totalRecords / $length);

			$records = $records->offset(($page - 1) * $length)->limit($length)->get();
		} else {
			$records = $records->get([]);
			$totalRecords = $records->count();
		}

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_list"),
			'data' => $records,
			'total_pages' => $totalPages ?? 1,
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
	 *             required={"name", "country_id"},
	 *             @OA\Property(property="name", type="string", example="Samsung"),
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
	 *             @OA\Property(property="dropshipping", type="boolean", example=true),
	 *             @OA\Property(property="shipping_days", type="string", example="5-7 days"),
	 *             @OA\Property(property="credit_terms", type="string", example="Net 30"),
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
			'country_id' => 'required|integer|exists:countries,id',
			'city_ids' => 'nullable|array',
			'city_ids.*' => 'integer|exists:cities,id',
			'zipcode_ids' => 'nullable|array',
			'zipcode_ids.*' => 'integer|exists:zipcodes,id',
			'dropshipping' => 'nullable|boolean',
			'shipping_days' => 'nullable|string|max:255',
			'credit_terms' => 'nullable|string|max:255',
			'grade' => 'nullable|string|max:255',
		]);

		/* Create the vendor */
		$vendor = PreOnboardingVendor::create([
			'name' => $validated['name'],
			'country_id' => $validated['country_id'],
			'city_ids' => isset($validated['city_ids']) ? implode(',', $validated['city_ids']) : null,
			'zipcode_ids' => isset($validated['zipcode_ids']) ? implode(',', $validated['zipcode_ids']) : null,
			'dropshipping' => $validated['dropshipping'],
			'shipping_days' => $validated['shipping_days'],
			'credit_terms' => $validated['credit_terms'],
			'grade' => $validated['grade'],
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

		$record->city_ids = $record->city_ids ? explode(',', $record->city_ids) : [];
		$record->zipcode_ids = $record->zipcode_ids ? explode(',', $record->zipcode_ids) : [];
		$record->description = json_validate($record->description) ? json_decode($record->description, true) : [];

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
	 *     description="Updates an pre onboarding vendor's details, including name, code, type, required status, and validations.",
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
	 *             required={"name", "country_id"},
	 *             @OA\Property(property="name", type="string", example="Samsung"),
	 *             @OA\Property(property="country_id", type="integer", example=1),
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
	 *                 example={}
	 *             ),
	 *             @OA\Property(property="dropshipping", type="boolean", example=true),
	 *             @OA\Property(property="shipping_days", type="string", example="5-7 days"),
	 *             @OA\Property(property="credit_terms", type="string", example="Net 30"),
	 *             @OA\Property(property="grade", type="string", example="A"),
	 *
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
			'country_id' => 'required|integer|exists:countries,id',
			'city_ids' => 'nullable|array',
			'city_ids.*' => 'integer|exists:cities,id',
			'zipcode_ids' => 'nullable|array',
			'zipcode_ids.*' => 'integer|exists:zipcodes,id',
			'dropshipping' => 'nullable|boolean',
			'shipping_days' => 'nullable|string|max:255',
			'credit_terms' => 'nullable|string|max:255',
			'grade' => 'nullable|string|max:255',
			'product_demand_level' => 'nullable|array',
		]);

		/* Update fields */
		$vendor->name = $validated['name'];
		$vendor->country_id = $validated['country_id'];
		$vendor->dropshipping = $validated['dropshipping'] ?? null;
		$vendor->shipping_days = $validated['shipping_days'] ?? null;
		$vendor->credit_terms = $validated['credit_terms'] ?? null;
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
			'data'    => $vendor
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

		/* Check if attribute is attached to any attribute group */
		// if ($record->vendor()->exists()) {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => __("err_pre_vendor_association")
		// 	], 400);
		// }

		/* Proceed with deletion */
		$record->delete();

		return response()->json([
			'success' => true,
			'message' => __("msg_dlt")
		], 200);
	}
}
