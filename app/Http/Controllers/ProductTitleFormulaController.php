<?php

namespace App\Http\Controllers;

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

	 public function index(Request $request)
{
    $query = ProductTitleFormula::with(['category', 'creator']);

    // Search
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search) {
            $q->whereHas('category', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
              ->orWhereHas('creator', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
              ->orWhere('attribute_id', 'like', "%{$search}%"); // still searching by JSON field
        });
    }

    // Sorting
    $sortBy = $request->input('sort_by', 'id');
    $sortOrder = $request->input('sort_order', 'desc');
    if (in_array($sortBy, ['id', 'category_id', 'created_by', 'locked']) && in_array($sortOrder, ['asc', 'desc'])) {
        $query->orderBy($sortBy, $sortOrder);
    }

    // Pagination
    $perPage = $request->input('per_page', 10);
    $data = $query->paginate($perPage);

    // Transform data to show attribute names
    $transformed = $data->getCollection()->map(function ($item) {
        return [
            'id' => $item->id,
            'category' => $item->category?->name,
            'created_by' => $item->creator?->name,
            'locked' => $item->locked,
            'attribute_names' => collect($item->attribute_id) // already cast to array in model
                ->map(fn($id) => Attribute::find($id)?->name)
                ->filter()
                ->values(),
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    });

    return response()->json([
        'data' => $transformed,
        'current_page' => $data->currentPage(),
        'last_page' => $data->lastPage(),
        'per_page' => $data->perPage(),
        'total' => $data->total(),
    ]);
}




	/**
	 * @OA\Get(
	 *     path="/api/product-title-formula/{id}",
	 *     summary="Get a single product title formula",
	 * 	   security={{"bearerAuth":{}}},
	 *     tags={"Product Title Formula"},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Formula ID", @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Formula data"),
	 *     @OA\Response(response=404, description="Formula not found")
	 * )
	 */

	 public function show($id)
	 {
		 $formula = ProductTitleFormula::with(['category', 'creator'])->findOrFail($id);
	 
		 $attributeNames = collect(json_decode($formula->attribute_ids))
			 ->map(function ($attrId) {
				 return Attribute::find($attrId)?->name;
			 })
			 ->filter()
			 ->values();
	 
		 $response = [
			 'id' => $formula->id,
			 'category' => $formula->category?->name,
			 'created_by' => $formula->creator?->name,
			 'locked' => $formula->locked,
			 'attribute_names' => $attributeNames,
			 'created_at' => $formula->created_at,
			 'updated_at' => $formula->updated_at,
		 ];
	 
		 return response()->json($response);
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
	 *     summary="Update an existing product title formula",
	 *     tags={"Product Title Formula"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Formula ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="attribute_id", type="integer", example=2),
	 *             @OA\Property(property="category_id", type="integer", example=47),
	 *             @OA\Property(property="locked", type="boolean", example=true),
	 *             @OA\Property(property="created_by", type="integer", example=1)
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Updated successfully"),
	 *     @OA\Response(response=404, description="Formula not found"),
	 *     @OA\Response(response=422, description="Validation error")
	 * )
	 */
	public function update(Request $request, $id)
	{
		$formula = ProductTitleFormula::findOrFail($id);

		$validated = $request->validate([
			'attribute_id' => 'sometimes|integer|exists:attributes,id',
			'category_id' => 'nullable|exists:categories,id',
			'locked' => 'boolean',
			'created_by' => 'nullable|integer',
		]);

		$formula->update($validated);

		return response()->json([
			'message' => 'Product title formula updated successfully.',
			'data' => $formula,
		]);
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
