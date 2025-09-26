<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Validator;
 
class FndProductVariantController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/product-variants",
     *     summary="Get list of Frontend Product Variants",
     *     tags={"Frontend Product variants"},
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         required=false,
     *         description="Filter by product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *      
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product Variants retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {          
        try {

            $validator = Validator::make($request->all(), [
                'product_id' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            // Ensure product_id is always an array
            $productIds = $request->product_id;

            if (empty($productIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product IDs provided'
                ], 422);
            }
            $variant = ProductVariant::with([
                'parentProduct:id,name,sku',
                'createdBy:id,username',
                'updatedBy:id,username'
            ])->where('parent_id', $productIds)->first();

            $childIds = json_decode($variant->child_ids, true) ?? [];
            $variants = json_decode($variant->variants, true) ?? [];

            // Load child products
            $children = \DB::table('ec_products')
                ->whereIn('id', $childIds)
                ->get(['id', 'name', 'sku']);

            // Load attributes for variants
            $attributeIds = collect($variants)->pluck('attribute_id')->filter()->all();
            $attributes = \DB::table('attributes')
                ->whereIn('id', $attributeIds)
                ->pluck('name', 'id');

            $variant->children = $children->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'sku' => $c->sku,
                ];
            });

            $variant->variants = collect($variants)->map(function ($v) use ($attributes) {
                return [
                    'attribute_id' => $v['attribute_id'],
                    'attribute_name' => $attributes[$v['attribute_id']] ?? null,
                    'label' => $v['labels'] ?? null,
                    'type' => $v['type'] ?? null,
                ];
            });

            $data = [

                'id' => $variant->id,
                'parent_id' => $variant->parent_id,
                'parent_name' => $variant->parentProduct?->name,
                'parent_sku' => $variant->parentProduct?->sku,                 
                'variants' => $variant->variants,
                'child' => $variant->children,

            ];



            return response()->json([
                'success' => true,
                'message' => 'Attributes fetched successfully',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attributes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
