<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Models\ProductAttribute;
use App\Models\Attribute;
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
            'product_id' => 'required|integer',
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
            // ✅ Fixed: Removed .product_id from with() - it's a column, not a relationship
            $currentProduct = Product::with(['productAttributes'])
                ->find($productIds);

            if (!$currentProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // ✅ Get current product's attribute values for comparison
            $currentProductAttributes = $currentProduct->productAttributes
                ->pluck('attribute_value', 'attribute_id')
                ->toArray();

            // ✅ Remove dd() for production
            // dd($currentProductAttributes);

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
            $childIds = collect($childIds)->merge([$productIds])->unique()->values()->toArray();

            // Early return if no children or variants
            if (empty($childIds) || empty($variants)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No children or variants found',
                    'data' => []
                ], 200);
            }

            // Fetch all child products at once
            $children = Product::whereIn('id', $childIds)
                ->select('id', 'sku')
                ->get();

            // Fetch all attribute names at once
            $attributeIds = array_column($variants, 'attribute_id');
            $attributes = Attribute::whereIn('id', $attributeIds)
                ->pluck('name', 'id');

            // Fetch all product attributes at once
            $productAttributes = ProductAttribute::whereIn('product_id', $childIds)
                ->whereIn('attribute_id', $attributeIds)
                ->get()
                ->groupBy('product_id');

            // Fetch all SEO URLs at once
            $seoUrls = SeoManagement::whereIn('relational_id', $childIds)
                ->pluck('url', 'relational_id');

            $result = [];

            foreach ($variants as $v) {
                $attributeId = $v['attribute_id'];
                $attributeName = $attributes[$attributeId] ?? null;

                if (!$attributeName) {
                    continue; // Skip if attribute not found
                }

                // Track unique attribute values for this specific attribute
                $seenAttributeValues = [];

                foreach ($children as $child) {
                    // Get attribute value for this child and attribute
                    $attrValue = $productAttributes->get($child->id)?->firstWhere('attribute_id', $attributeId)?->attribute_value ?? null;

                    // Skip if no attribute value or if we've already seen this value
                    if (empty($attrValue) || isset($seenAttributeValues[$attrValue])) {
                        continue;
                    }

                    // Mark this attribute value as seen
                    $seenAttributeValues[$attrValue] = true;

                    $slug = $seoUrls[$child->id] ?? null;

                    $isSelected = isset($currentProductAttributes[$attributeId])
                        && $currentProductAttributes[$attributeId] == $attrValue;

                    $result[] = [
                        // 'product_id' => $child->id,
                        'attribute_id' => $attributeId,
                        'attribute_value' => $attrValue,
                        'attribute_name' => $attributeName,
                        'type' => $v['type'] ?? 'dropdown',
                        'label' => $v['labels'] ?? $attributeName,
                        'selected' => $isSelected,
                        // 'slug' => $slug,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Product variants retrieved successfully',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Product variant error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving variants',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }




    }

    /**
     * @OA\Post(
     *     path="/api/frontend/attribute-product-variants",
     *     summary="Get list of Frontend Attribute Product Variants",
     *     tags={"Frontend Product variants"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="attribute",
     *                     type="array",
     *                     description="Array of attribute objects",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="attribute_id", type="integer", example=7),
     *                         @OA\Property(property="attribute_value", type="string", example="Leveling Legs")
     *                     )
     *                 ),
     *                 example={
     *                     "attribute": {
     *                         {"attribute_id": 7, "attribute_value": "Leveling Legs"},
     *                         {"attribute_id": 401, "attribute_value": "Gas"},
     *                         {"attribute_id": 48, "attribute_value": "82 lb"},
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product Variants retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */

    // public function getAttributeByProduct(Request $request)
    // {
    //     try {
    //         // ✅ Validate incoming data
    //         $validator = Validator::make($request->all(), [
    //             'attribute' => 'required|array',
    //             'attribute.*.attribute_id' => 'required|integer',
    //             'attribute.*.attribute_value' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Validation failed',
    //                 'errors' => $validator->errors()
    //             ], 422);
    //         }

    //         $attributes = $request->input('attribute', []);

             
    //         $productIds = null;

    //         // For each attribute, intersect with products that have it
    //         foreach ($attributes as $index => $attr) {
    //             $matchingIds = ProductAttribute::where('attribute_id', $attr['attribute_id'])
    //                 ->where('attribute_value', $attr['attribute_value'])
    //                 ->pluck('product_id');

    //             // If first iteration, initialize productIds
    //             if ($index === 0) {
    //                 $productIds = $matchingIds;
    //             } else {
    //                 // Keep only products that exist in both sets (intersection)
    //                 $productIds = $productIds->intersect($matchingIds);
    //             }

    //             // Early exit if no products remain
    //             if ($productIds->isEmpty()) {
    //                 break;
    //             }
    //         }

    //         // $productIds = $productIds ? $productIds->values()->toArray() : [];
    //         $productIds = $productIds ? $productIds->values()->toArray() : [];

    //         // Filter ONLY products that appear in product_variant table as parent_id
    //         $validProductIds = \DB::table('product_variants')
    //             ->whereIn('parent_id', $productIds)
    //             ->pluck('parent_id')
    //             ->unique()
    //             ->toArray();

    //         // Keep only valid products
    //         $productIds = $validProductIds;

    //         if (empty($productIds)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No products found with variants',
    //             ], 404);
    //         }
            
    //         $products = Product::whereIn('id', $productIds)
    //             ->select('id', 'sku')
    //             ->get()
    //             ->keyBy('id');

    //         $seoUrls = SeoManagement::whereIn('relational_id', $productIds)
    //             ->pluck('url', 'relational_id');

    //         $result = [];

    //         foreach ($productIds as $productId) {
    //             $product = $products->get($productId);
    //             if (!$product)
    //                 continue;

    //             $slug = $seoUrls[$product->id] ?? null;

    //             // Build complete SEO slug
    //             $parentSlug = $product->parent_category_url() ?? '';
    //             $categorySlug = $product->category_url() ?? '';
    //             $productSlug = $product->seoProductUrl->url ?? '';

    //             $fullSlug = trim($parentSlug . '/' . $categorySlug . '/' . $productSlug, '/');

    //             $result[] = [
    //                 'product_id' => $product->id,
    //                 'sku' => $product->sku,
    //                 'slug' => $slug,
    //                 'full_slug' => $fullSlug,
    //             ];
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Product variants fetched successfully',
    //             'data' => $result,
    //         ], 200);

    //     } catch (\Exception $e) {
    //         \Log::error('Product variant fetch error: ' . $e->getMessage(), [
    //             'request' => $request->all(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to fetch product variants',
    //             'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
    //         ], 500);
    //     }

    // }
    public function getAttributeByProduct(Request $request)
    {
        try {
            // ✅ Validate incoming data
            $validator = Validator::make($request->all(), [
                'parent_id' => 'required|integer', // 🔥 Required
                'attribute' => 'required|array',
                'attribute.*.attribute_id' => 'required|integer',
                'attribute.*.attribute_value' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $parentId = $request->parent_id;
            $attributes = $request->input('attribute', []);

            // 🔥 Fetch parent product
            $parentProduct = Product::find($parentId);

            if (!$parentProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent product not found',
                ], 404);
            }

            // 🔥 Decode child IDs
            $childIds = is_array($parentProduct->child_ids)
                ? $parentProduct->child_ids
                : json_decode($parentProduct->child_ids, true);

            if (empty($childIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent product has no variants (child IDs empty)',
                ], 404);
            }

            // ⭐ STEP 1: Filter by attribute matching
            $productIds = null;

            foreach ($attributes as $index => $attr) {
                $matchingIds = ProductAttribute::where('attribute_id', $attr['attribute_id'])
                    ->where('attribute_value', $attr['attribute_value'])
                    ->pluck('product_id');

                if ($index === 0) {
                    $productIds = $matchingIds;
                } else {
                    $productIds = $productIds->intersect($matchingIds);
                }

                if ($productIds->isEmpty()) {
                    break;
                }
            }

            $productIds = $productIds ? $productIds->values()->toArray() : [];

            // ⭐ STEP 2: Keep only products that exist in product_variants table
           $variantChildIds = \DB::table('product_variants')
            ->where('parent_id', $parentId)   // important: parent must match current product
            ->whereIn('child_id', $productIds) // and child must match selected attributes
            ->pluck('child_id')
            ->toArray();


            // ⭐ STEP 3: Keep only products that belong to the selected parent's child_ids
            $productIds = array_values(array_intersect($variantChildIds, $childIds));


            if (empty($productIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No matching variant found for this product',
                ], 404);
            }

            // Fetch products
            $products = Product::whereIn('id', $productIds)
                ->select('id', 'sku')
                ->get()
                ->keyBy('id');

            $seoUrls = SeoManagement::whereIn('relational_id', $productIds)
                ->pluck('url', 'relational_id');

            $result = [];

            foreach ($productIds as $pid) {
                $product = $products->get($pid);
                if (!$product) continue;

                $slug = $seoUrls[$product->id] ?? null;

                $fullSlug = trim(
                    ($product->parent_category_url() ?? '') . '/' .
                    ($product->category_url() ?? '') . '/' .
                    ($product->seoProductUrl->url ?? ''),
                    '/'
                );

                $result[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'slug' => $slug,
                    'full_slug' => $fullSlug,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Product variants fetched successfully',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Product variant fetch error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product variants',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/product-variants-by-attribute",
     *     summary="Get list of Frontend Attribute all Product Variants",
     *     tags={"Frontend Product variants"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="attribute",
     *                     type="array",
     *                     description="Array of attribute objects",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="attribute_id", type="integer", example=7),
     *                         @OA\Property(property="attribute_value", type="string", example="Leveling Legs")
     *                     )
     *                 ),
     *                 example={
     *                     "attribute": {
     *                         {"attribute_id": 7, "attribute_value": "Leveling Legs"},
     *                         {"attribute_id": 401, "attribute_value": "Gas"},
     *                         {"attribute_id": 48, "attribute_value": "82 lb"},
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product Variants retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */

    public function getAttributeByProductVariant(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'attribute' => 'required|array',
                'attribute.*.attribute_id' => 'required|integer',
                'attribute.*.attribute_value' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attributes = $request->input('attribute', []);


            $firstAttr = array_shift($attributes);

            // Get initial product IDs from ProductAttribute
            $productIds = ProductAttribute::where('attribute_id', $firstAttr['attribute_id'])
                ->where('attribute_value', $firstAttr['attribute_value'])
                ->pluck('product_id');

            // For each remaining attribute, intersect with products that have it
            foreach ($attributes as $attr) {
                $matchingIds = ProductAttribute::where('attribute_id', $attr['attribute_id'])
                    ->where('attribute_value', $attr['attribute_value'])
                    ->pluck('product_id');

                // Keep only products that exist in both sets (intersection)
                $productIds = $productIds->intersect($matchingIds);
            }

            if ($productIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products found with all these attributes',
                ], 404);
            }

            //Filter products that have variants with the specified attribute IDs
            $attributeIds = collect($request->input('attribute'))->pluck('attribute_id')->toArray();

            // Only get products that exist in ProductVariant with matching attribute IDs
            $validProductIds = ProductVariant::whereIn('parent_id', $productIds->toArray())
                ->pluck('parent_id')
                ->unique();

            if ($validProductIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product variants found with these attribute IDs',
                ], 404);
            }

            //Eager-load related data for optimization
            $products = Product::whereIn('id', $validProductIds)
                ->select('id', 'sku')
                ->get()
                ->keyBy('id');

            $seoUrls = SeoManagement::whereIn('relational_id', $validProductIds)
                ->pluck('url', 'relational_id');

            // Fetch all product attributes for the valid products
            $productAttributes = ProductAttribute::whereIn('product_id', $validProductIds)
                ->whereIn('attribute_id', $attributeIds)
                ->select('product_id', 'attribute_id', 'attribute_value')
                ->get()
                ->groupBy('product_id');

            $result = [];

            foreach ($validProductIds as $productId) {
                $product = $products->get($productId);
                if (!$product)
                    continue;

                $slug = $seoUrls[$productId] ?? null;

                // Build complete SEO slug
                $parentSlug = $product->parent_category_url() ?? '';
                $categorySlug = $product->category_url() ?? '';
                $productSlug = $product->seoProductUrl->url ?? '';

                $fullSlug = trim($parentSlug . '/' . $categorySlug . '/' . $productSlug, '/');

                // Get attributes for this product
                $productAttrs = $productAttributes->get($productId, collect())->map(function ($attr) {
                    return [
                        'attribute_id' => $attr->attribute_id,
                        'attribute_value' => $attr->attribute_value,
                    ];
                })->values()->toArray();

                $result[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'slug' => $slug,
                    'full_slug' => $fullSlug,
                    'attributes' => $productAttrs,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Product variants fetched successfully',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Product variant fetch error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product variants',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
}
