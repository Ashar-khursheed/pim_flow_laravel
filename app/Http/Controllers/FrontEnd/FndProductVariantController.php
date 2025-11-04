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
                $childrenData = [];

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
                    $full_slug = $child->parent_category_url() . '/' .
                        $child->category_url() . '/' .
                        ($child->seoProductUrl->url ?? "");

                    $result[] = [

                        'attribute_id' => $attributeId,
                        'attribute_value' => $attrValue,
                        'attribute_name' => $attributeName,
                        'type' => $v['type'] ?? 'dropdown',
                        'label' => $v['labels'] ?? $attributeName,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Product variants retrieved successfully',
                'data' => $result

            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving variants',
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
     *         @OA\Schema(type="integer", example="7")
     *     ),
     *     @OA\Parameter(
     *         name="attribute_value",
     *         in="query",
     *         required=false,
     *         description="Filter by attribute value",
     *         @OA\Schema(type="string", example="Adjustable Stainless Steel Legs")
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
                'attribute_id' => 'required|integer|exists:attributes,id',
                'attribute_value' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attributeId = $request->attribute_id;
            $attributeValue = $request->attribute_value;

            // Step 1: Find product IDs that have this exact attribute_id and attribute_value
            $productIds = ProductAttribute::where('attribute_id', $attributeId)
                ->where('attribute_value', $attributeValue)
                ->pluck('product_id')
                ->unique()
                ->toArray();

            if (empty($productIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products found with this attribute combination',
                ], 404);
            }

            // Fetch all products at once (optimization)
            $products = Product::whereIn('id', $productIds)
                ->select('id', 'sku')
                ->get()
                ->keyBy('id'); // Key by ID for easy access

            // Fetch all SEO URLs at once (optimization)
            $seoUrls = SeoManagement::whereIn('relational_id', $productIds)
                ->pluck('url', 'relational_id');

            $result = [];

            foreach ($productIds as $productId) {
                // Get product from collection
                $product = $products->get($productId);

                if (!$product) {
                    continue; // Skip if product not found
                }

                // Get SEO URL
                $slug = $seoUrls[$product->id] ?? null;

                // Build full slug
                $parentSlug = $product->parent_category_url() ?? '';
                $categorySlug = $product->category_url() ?? '';
                $productSlug = $product->seoProductUrl->url ?? '';

                $full_slug = trim($parentSlug . '/' . $categorySlug . '/' . $productSlug, '/');

                $result[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'attribute_id' => $attributeId,
                    'attribute_value' => $attributeValue,
                    'slug' => $slug,
                    'full_slug' => $full_slug,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Product variants fetched successfully',
                'data' => $result

            ], 200);

        } catch (\Exception $e) {
            \Log::error('Product variant fetch error: ' . $e->getMessage(), [
                'attribute_id' => $request->attribute_id ?? null,
                'attribute_value' => $request->attribute_value ?? null,
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
