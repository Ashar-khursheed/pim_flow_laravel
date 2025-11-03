<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Models\ProductAttribute;
use App\Models\Product;
use App\Models\SeoManagement;
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

        $productIds = $request->product_id;

        if (empty($productIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No product IDs provided'
            ], 422);
        }

        try {
            $variant = ProductVariant::with([
                'parentProduct:id,name,sku',
                'createdBy:id,username',
                'updatedBy:id,username'
            ])
                ->where('parent_id', $productIds)
                ->first();

            if (!$variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No variant found for this product',
                ], 404);
            }

            $childIds = json_decode($variant->child_ids, true) ?? [];
            $variants = json_decode($variant->variants, true) ?? [];

            // Load child products
            $children = \DB::table('ec_products')
                ->whereIn('id', $childIds)
                ->get(['id', 'name', 'sku']);

            // Load attributes
            $attributeIds = collect($variants)->pluck('attribute_id')->filter()->all();
            $attributes = \DB::table('attributes')
                ->whereIn('id', $attributeIds)
                ->pluck('name', 'id');

            // Map variant attributes
            $mappedVariants = collect($variants)->map(function ($v) use ($attributes) {
                return [
                    'attribute_id' => $v['attribute_id'],
                    'attribute_name' => $attributes[$v['attribute_id']] ?? null,
                    'label' => $v['labels'] ?? ($attributes[$v['attribute_id']] ?? null),
                    'type' => $v['type'] ?? 'dropdown',
                ];
            });

            // ✅ Ensure unique attribute_name + label combination
            $uniqueVariants = $mappedVariants->unique(function ($item) {
                return $item['attribute_name'] . '-' . $item['label'];
            })->values();

            $data = [
                'variant_id' => $variant->id,
                'parent_id' => $variant->parent_id,
                'parent_name' => $variant->parentProduct?->name,
                'parent_sku' => $variant->parentProduct?->sku,
                'variants' => $uniqueVariants,
                'child' => $children->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'sku' => $c->sku,
                ]),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Product variants fetched successfully',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product variants',
                'error' => $e->getMessage()
            ], 500);
        }



    }

    /**
     * @OA\Post(
     *     path="/api/frontend/attribute-product-variants",
     *     summary="Get list of Frontend Attribute Product Variants",
     *     tags={"Frontend Product variants"},
     *     @OA\Parameter(
     *         name="attribute_id",
     *         in="query",
     *         required=false,
     *         description="Filter by attribute ID",
     *         @OA\Schema(type="integer", example=7)
     *     ),
     *     @OA\Parameter(
     *         name="variant_id",
     *         in="query",
     *         required=false,
     *         description="Filter by variant ID",
     *         @OA\Schema(type="integer", example=5)
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
    public function getAttributeByProduct(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'attribute_id' => 'required|integer|exists:product_attributes,attribute_id',
                'variant_id' => 'required|integer|exists:product_variants,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attributeId = $request->attribute_id;
            $variantId = $request->variant_id;

            // Step 1: Find product IDs that have this attribute
            $productIds = ProductAttribute::where('attribute_id', $attributeId)
                ->pluck('product_id')
                ->toArray();

            // Step 2: Load variant and parent
            $variant = ProductVariant::with(['parentProduct:id,name,sku'])
                ->find($variantId);

            if (!$variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Variant not found',
                ], 404);
            }

            // Step 3: Decode children and variants
            $childIds = json_decode($variant->child_ids, true) ?? [];
            $variants = json_decode($variant->variants, true) ?? [];

            if (empty($childIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No child products found for this variant',
                ], 404);
            }

            // Step 4: Load only children that have matching attribute_id
            $children = Product::whereIn('id', $childIds)
                ->whereIn('id', $productIds)
                ->get(['id', 'name', 'sku']);

            // Step 5: Get attribute names
            $attributeIds = collect($variants)->pluck('attribute_id')->filter()->all();
            $attributes = \DB::table('attributes')
                ->whereIn('id', $attributeIds)
                ->pluck('name', 'id');

            // Step 6: Build attribute-value map from ProductAttribute
            $attributeValues = ProductAttribute::whereIn('product_id', $childIds)
                ->whereIn('attribute_id', $attributeIds)
                ->get(['product_id', 'attribute_id', 'attribute_value']);

            // Step 7: Map variant attributes and filter only those that exist in children
            $mappedVariants = collect($variants)->map(function ($v) use ($attributes, $attributeValues, $children) {
                $attributeName = $attributes[$v['attribute_id']] ?? null;

                // Find value used by any child product for this attribute
                $matchingValues = $attributeValues
                    ->where('attribute_id', $v['attribute_id'])
                    ->pluck('attribute_value')
                    ->unique()
                    ->values();

                return $matchingValues->map(fn($val) => [
                    'attribute_id' => $v['attribute_id'],
                    'attribute_name' => $attributeName,
                    'value' => $val,
                ]);
            })->flatten(1);

            // Step 8: Ensure unique attribute name + value pairs
            $uniqueVariants = $mappedVariants->unique(function ($item) {
                return $item['attribute_name'] . '-' . $item['value'];
            })->values();

            // Step 9: Final response structure
            $data = [
                'variant_id' => $variant->id,
                'parent_id' => $variant->parent_id,
                'parent_name' => $variant->parentProduct?->name,
                'parent_sku' => $variant->parentProduct?->sku,
                'variants' => $uniqueVariants,
                'children' => $children,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Product variants fetched successfully',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product variants',
                'error' => $e->getMessage()
            ], 500);
        }


    }


}
