<?php

namespace App\Http\Controllers;

use App\Models\TempProduct;
use Illuminate\Http\Request;

class TempProductController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/temp-products",
	 *     summary="Get all temp-products with pagination and filters",
	 *     tags={"Temp Products"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="status", in="query", description="Filter by temp product status.", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "category", "brand", "vendor", "sku", "creator", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'name', 'category', 'brand', 'vendor', 'sku', 'creator'];
		$sortableColumns = ["id", "name", "category", "brand", "vendor", "sku", "creator", "created_at", "updated_at"];

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = TempProduct::query();

		/* Always apply status filter if present */
		if ($request->filled('status')) {
			$recordsQuery->whereHas('status', function ($q) use ($request) {
				$q->where('name', $request->status);
			});
		}

		$hasPagination = $request->filled('page') && $request->filled('length');

		if ($hasPagination) {
			/* Eager load */
			$recordsQuery->with([
				'category:id,name',
				'brand:id,name',
				'vendor:id,name',
				'status:id,name',
				'creator:id,name',
			]);

			/* Global search */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($search) {
					$q->orWhere('name', 'like', "%$search%")
					->orWhere('sku', 'like', "%$search%")
					->orWhereHas('category', fn($sub) => $sub->where('name', 'like', "%$search%"))
					->orWhereHas('brand', fn($sub) => $sub->where('name', 'like', "%$search%"))
					->orWhereHas('vendor', fn($sub) => $sub->where('name', 'like', "%$search%"))
					->orWhereHas('creator', fn($sub) => $sub->where('name', 'like', "%$search%"));
				});
			}

			/* Sorting */
			if (in_array($sortBy, ['category', 'brand', 'vendor', 'creator'])) {
				$recordsQuery->joinRelation($sortBy)
				->orderBy("$sortBy.name", $sortDir)
				->select('temp_products.*');
			} else {
				$recordsQuery->orderBy($sortBy, $sortDir);
			}

			/* Pagination */
			$length = (int) $request->input('length');
			$page = (int) $request->input('page');

			$totalRecords = (clone $recordsQuery)->count();
			$totalPages = (int) ceil($totalRecords / $length);

			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get();

			/* Transform */
			$records->transform(function ($record) {
				$record->category_name = $record->category->name ?? null;
				$record->brand_name = $record->brand->name ?? null;
				$record->vendor_name = $record->vendor->name ?? null;
				$record->status_name = $record->status->name ?? null;
				$record->created_by = $record->creator->name ?? null;

				unset($record->category, $record->brand, $record->vendor, $record->status, $record->creator);
				return $record;
			});
		} else {
			/* No pagination: only return id and name */
			$records = $recordsQuery->get(['id', 'name']);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/temp-products",
	 *     summary="Create a new Temp Product",
	 *     tags={"Temp Products"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "category_id", "brand_id", "vendor_id", "sku"},
	 *             @OA\Property(property="name", type="string", example="Red Cotton Shirt"),
	 *             @OA\Property(property="category_id", type="integer", example=1),
	 *             @OA\Property(property="brand_id", type="integer", example=2),
	 *             @OA\Property(property="vendor_id", type="integer", example=5),
	 *             @OA\Property(property="sku", type="string", example="RCSHIRT-001"),
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$request->validate([
			'name' => 'required|string|max:255',
			'category_id' => 'required|integer|exists:categories,id',
			'brand_id' => 'required|integer|exists:brands,id',
			'vendor_id' => 'required|integer|exists:vendors,id',
			'sku' => 'required|string|max:100|unique:temp_products,sku',
		]);

		/* Ensure only leaf-level category is allowed */
		if ($category->children()->exists()) {
			return response()->json([
				'success' => false,
				'message' => 'Only leaf-level categories (categories without children) can be selected.',
			], 422);
		}

		$tempProduct = TempProduct::create([
			'name' => $request->name,
			'category_id' => $request->category_id,
			'brand_id' => $request->brand_id,
			'vendor_id' => $request->vendor_id,
			'sku' => $request->sku,
			'status_id' => 1,
			'created_by' => auth()->id(),
		]);

		return response()->json([
			'success' => true,
			'message' => 'Temp Product created successfully.',
			'data' => $tempProduct
		], 201);
	}

	/**
	 * Display the specified resource.
	 */
	public function show(TempProduct $tempProduct)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, TempProduct $tempProduct)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(TempProduct $tempProduct)
	{
		//
	}
}
