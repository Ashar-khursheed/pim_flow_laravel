<?php

namespace App\Http\Controllers;
use App\Models\Attribute;
use App\Models\ProductTitleFormula;
use Illuminate\Http\Request;

class ProductTitleFormulaController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/product-title-formula",
	 *     summary="Get a list of product title formulas",
	 * 	   security={{"bearerAuth":{}}},
	 *     description="Returns a paginated list with optional search and sorting.",
	 *     tags={"Product Title Formula"},
	 *     @OA\Parameter(name="search", in="query", required=false, description="Search term", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", required=false, description="Sort by field", @OA\Schema(type="string", enum={"id", "category_id", "created_by", "locked"})),
	 *     @OA\Parameter(name="sort_order", in="query", required=false, description="Sort order", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page", @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="List of formulas")
	 * )
	 */

	//  public function index(Request $request)
	// {
	// 	$query = ProductTitleFormula::with(['category', 'creator']);

	// 	// Search
	// 	if ($search = $request->input('search')) {
	// 		$query->where(function ($q) use ($search) {
	// 			$q->whereHas('category', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
	// 			->orWhereHas('creator', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
	// 			->orWhere('attribute_id', 'like', "%{$search}%"); // still searching by JSON field
	// 		});
	// 	}

	// 	// Sorting
	// 	$sortBy = $request->input('sort_by', 'id');
	// 	$sortOrder = $request->input('sort_order', 'desc');
	// 	if (in_array($sortBy, ['id', 'category_id', 'created_by', 'locked']) && in_array($sortOrder, ['asc', 'desc'])) {
	// 		$query->orderBy($sortBy, $sortOrder);
	// 	}

	// 	// Pagination
	// 	$perPage = $request->input('per_page', 10);
	// 	$data = $query->paginate($perPage);

	// 	// Transform data to show attribute names
	// 	$transformed = $data->getCollection()->map(function ($item) {
	// 		return [
	// 			'id' => $item->id,
	// 			'category' => $item->category?->name,
	// 			'created_by' => $item->creator?->name,
	// 			'locked' => $item->locked,
	// 			'attribute_names' => collect($item->attribute_id) // already cast to array in model
	// 				->map(fn($id) => Attribute::find($id)?->name)
	// 				->filter()
	// 				->values(),
	// 			'created_at' => $item->created_at,
	// 			'updated_at' => $item->updated_at,
	// 		];
	// 	});

	// 	return response()->json([
	// 		'data' => $transformed,
	// 		'current_page' => $data->currentPage(),
	// 		'last_page' => $data->lastPage(),
	// 		'per_page' => $data->perPage(),
	// 		'total' => $data->total(),
	// 	]);
	// }
	public function index(Request $request)
{
	$query = ProductTitleFormula::with(['category', 'creator']);

	// Apply search
	if ($search = $request->input('search')) {
		$query->where(function ($q) use ($search) {
			$q->whereHas('category', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
				->orWhereHas('creator', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
				->orWhere('attribute_id', 'like', "%{$search}%");
		});
	}

	// Sorting
	$sortBy = $request->input('sort_by', 'id');
	$sortOrder = $request->input('sort_order', 'desc');
	if (in_array($sortBy, ['id', 'category_id', 'created_by', 'locked']) && in_array($sortOrder, ['asc', 'desc'])) {
		$query->orderBy($sortBy, $sortOrder);
	}

	// Fetch all results
	$formulas = $query->get();

	$grouped = $formulas->groupBy('category_id')->map(function ($items, $categoryId) {
		$categoryName = optional($items->first()->category)->name;

		// Unique attribute IDs
		$attributeIds = $items->pluck('attribute_id')->flatten()->unique()->filter();
		$attributeNames = Attribute::whereIn('id', $attributeIds)->pluck('name')->toArray();

		// Unique creators
		$creatorNames = $items->pluck('creator.name')->unique()->filter()->values();
		$lock = $items->pluck('locked')->unique()->filter()->values();
		$created_at= $items->pluck('created_at')->unique()->filter()->values();
		return [
			'category_id' => $categoryId,
			'category_name' => $categoryName,
			'attribute_names' => implode(', ', $attributeNames),
			'created_by' => $creatorNames,
			'locked' => $lock,			// can return as array or implode
			// If you want comma-separated creators instead:
			// 'created_by' => implode(', ', $creatorNames->toArray()),
		];
	})->values();

	return response()->json([
		'data' => $grouped,
	]);
}





	/**
	 * @OA\Get(
	 *     path="/api/product-title-formula/{id}",
	 *     summary="Get a product title formula with category-wide attribute names",
	 *     description="Returns a product title formula and all unique attribute names used by any formula within the same category.",
	 *     security={{"bearerAuth":{}}},
	 *     tags={"Product Title Formula"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="The ID of the product title formula",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response with formula details and category-wide attributes",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="id", type="integer", example=9),
	 *             @OA\Property(property="category_id", type="integer", example=3),
	 *             @OA\Property(property="category_name", type="string", example="Mobile Phones"),
	 *             @OA\Property(property="created_by", type="string", example="Admin"),
	 *             @OA\Property(
	 *                 property="attribute_names",
	 *                 type="string",
	 *                 example="Color, Brand, Battery Life, Screen Size",
	 *                 description="Comma-separated list of attribute names used by all formulas in this category"
	 *             ),
	 *             @OA\Property(property="locked", type="boolean", example=false),
	 *             @OA\Property(property="created_at", type="string", format="date-time", example="2025-06-15T11:22:00Z"),
	 *             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-06-24T13:45:00Z")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Formula not found"
	 *     )
	 * )
	 */

	 public function show($id)
	 {
		 // Load the requested formula with its category and creator
		 $formula = ProductTitleFormula::with(['category', 'creator'])->findOrFail($id);
	 
		 // Get all formulas under the same category
		 $formulasInSameCategory = ProductTitleFormula::where('category_id', $formula->category_id)->get();
	 
		 // Collect unique attribute IDs across all formulas in this category
		 $attributeIds = $formulasInSameCategory->pluck('attribute_id')
			 ->flatten()
			 ->unique()
			 ->filter();
	 
		 // Get attribute names
		 $attributeNames = Attribute::whereIn('id', $attributeIds)->pluck('name')->toArray();
	 
		 return response()->json([
			 'id' => $formula->id,
			 'category_id' => $formula->category_id,
			 'category_name' => $formula->category?->name,
			 'created_by' => $formula->creator?->name,
			 'attribute_names' => implode(', ', $attributeNames),
			 'locked' => $formula->locked,
			 'created_at' => $formula->created_at,
			 'updated_at' => $formula->updated_at,
		 ]);
	 }
	 
	 

	/**
	 * @OA\Post(
	 *     path="/api/product-title-formula",
	 *     summary="Create product title formulas (one per attribute ID)",
	 *     tags={"Product Title Formula"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"attribute_ids"},
	 *             @OA\Property(
	 *                 property="attribute_ids",
	 *                 type="array",
	 *                 @OA\Items(type="integer", example=1)
	 *             ),
	 *             @OA\Property(property="category_id", type="integer", example=47),
	 *             @OA\Property(property="locked", type="boolean", example=true),
	 *             @OA\Property(property="created_by", type="integer", example=1)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product title formulas created successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product title formulas created successfully."),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(property="attribute_id", type="integer", example=3),
	 *                     @OA\Property(property="category_id", type="integer", example=47),
	 *                     @OA\Property(property="locked", type="boolean", example=true),
	 *                     @OA\Property(property="created_by", type="integer", example=1),
	 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-06-24T08:12:11.000000Z"),
	 *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-06-24T08:12:11.000000Z")
	 *                 )
	 *             )
	 *         )
	 *     )
	 * )
	 */
	public function store(Request $request)
	{
		$validated = $request->validate([
			'attribute_ids' => 'required|array',
			'attribute_ids.*' => 'integer|exists:attributes,id',
			'category_id' => 'nullable|exists:categories,id',
			'locked' => 'boolean',
			'created_by' => 'nullable|integer',
		]);

		$createdFormulas = [];

		foreach ($validated['attribute_ids'] as $attributeId) {
			$createdFormulas[] = ProductTitleFormula::create([
				'attribute_id' => $attributeId,
				'category_id' => $validated['category_id'] ?? null,
				'locked' => $validated['locked'] ?? false,
				'created_by' => $validated['created_by'] ?? null,
			]);
		}

		return response()->json([
			'message' => 'Product title formulas created successfully.',
			'data' => $createdFormulas,
		], 201);
	}

	/**
	 * @OA\Put(
		*     path="/api/product-title-formula/{id}",
	    *     summary="Update product title formulas by category (replace existing with new)",
		*     tags={"Product Title Formula"},
		*     security={{"bearerAuth":{}}},
		*     @OA\RequestBody(
		*         required=true,
		*         @OA\JsonContent(
		*             required={"attribute_ids", "category_id"},
		*             @OA\Property(
		*                 property="attribute_ids",
		*                 type="array",
		*                 @OA\Items(type="integer", example=1)
		*             ),
		*             @OA\Property(property="category_id", type="integer", example=47),
		*             @OA\Property(property="locked", type="boolean", example=true),
		*             @OA\Property(property="created_by", type="integer", example=1)
		*         )
		*     ),
		*     @OA\Response(
		*         response=200,
		*         description="Product title formulas updated successfully",
		*         @OA\JsonContent(
		*             @OA\Property(property="message", type="string", example="Product title formulas updated successfully."),
		*             @OA\Property(
		*                 property="data",
		*                 type="array",
		*                 @OA\Items(
		*                     type="object",
		*                     @OA\Property(property="id", type="integer", example=1),
		*                     @OA\Property(property="attribute_id", type="integer", example=3),
		*                     @OA\Property(property="category_id", type="integer", example=47),
		*                     @OA\Property(property="locked", type="boolean", example=true),
		*                     @OA\Property(property="created_by", type="integer", example=1),
		*                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-06-24T08:12:11.000000Z"),
		*                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-06-24T08:12:11.000000Z")
		*                 )
		*             )
		*         )
		*     )
		* )
		*/
	public function update(Request $request, $id)
	{
		$validated = $request->validate([
			'attribute_ids' => 'required|array',
			'attribute_ids.*' => 'integer|exists:attributes,id',
			'category_id' => 'required|exists:categories,id',
			'locked' => 'boolean',
			'created_by' => 'nullable|integer',
		]);
	
		// Start DB transaction for atomicity
		DB::beginTransaction();
	
		try {
			// Delete existing formulas in the given category
			ProductTitleFormula::where('category_id', $validated['category_id'])->delete();
	
			// Insert new formulas
			$newFormulas = [];
			foreach ($validated['attribute_ids'] as $attributeId) {
				$newFormulas[] = ProductTitleFormula::create([
					'attribute_id' => $attributeId,
					'category_id' => $validated['category_id'],
					'locked' => $validated['locked'] ?? false,
					'created_by' => $validated['created_by'] ?? null,
				]);
			}
	
			DB::commit();
	
			return response()->json([
				'message' => 'Product title formulas updated successfully.',
				'data' => $newFormulas,
			]);
		} catch (\Exception $e) {
			DB::rollBack();
	
			return response()->json([
				'message' => 'Failed to update product title formulas.',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/product-title-formula/{id}",
	 *     summary="Delete a product title formula",
	 * 	   security={{"bearerAuth":{}}},
	 *     tags={"Product Title Formula"},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Formula ID", @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Deleted successfully"),
	 *     @OA\Response(response=404, description="Formula not found")
	 * )
	 */

	public function destroy($id)
	{
		$formula = ProductTitleFormula::findOrFail($id);
		$formula->delete();
		return response()->json(['message' => 'Deleted successfully']);
	}

	/**
	 * @OA\Post(
	 *     path="/api/product-title-formula/delete-multiple",
	 *     summary="Delete multiple product title formulas",
	 *     tags={"Product Title Formula"},
	 * 	   security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"ids"},
	 *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"))
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Records deleted"),
	 *     @OA\Response(response=422, description="Validation error")
	 * )
	*/

	public function destroyMultiple(Request $request)
	{
		$request->validate([
			'ids' => 'required|array',
			'ids.*' => 'integer|exists:product_title_formula,id',
		]);

		ProductTitleFormula::whereIn('id', $request->ids)->delete();

		return response()->json(['message' => 'Selected records deleted']);
	}
}
