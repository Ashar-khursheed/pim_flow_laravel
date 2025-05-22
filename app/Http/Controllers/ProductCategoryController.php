<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductCategoryController extends Controller
{

    /**
     * @OA\Put(
     *     path="/api/products/{id}/categories",
     *     summary="Update categories for a specific product",
     *     description="This endpoint updates the categories associated with a specific product by detaching old categories and attaching new ones.",
     *     tags={"Products Category Update"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the product to update categories for",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"category_ids"},
     *             @OA\Property(
     *                 property="category_ids",
     *                 type="array",
     *                 @OA\Items(type="integer", example=3),
     *                 description="Array of category IDs to attach to the product"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product categories updated successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product categories updated successfully."),
     *             @OA\Property(property="product_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="new_categories",
     *                 type="array",
     *                 @OA\Items(type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to update product categories.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to update product categories."),
     *             @OA\Property(property="error", type="string", example="SQLSTATE[23000]: Integrity constraint violation: ...")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */


    public function updateCategories(Request $request, $id)
    {
        $request->validate([
            'category_ids' => 'required|array',
            'category_ids.*' => 'integer|exists:ec_product_categories,id',
        ]);

        $product = Product::findOrFail($id);

        DB::beginTransaction();
        try {
            // Detach all existing categories
            $product->categories()->detach();

            // Attach new categories
            $product->categories()->attach($request->category_ids);

            DB::commit();
            return response()->json([
                'message' => 'Product categories updated successfully.',
                'product_id' => $product->id,
                'new_categories' => $request->category_ids,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update product categories.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    
}
