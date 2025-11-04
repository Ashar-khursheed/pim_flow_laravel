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


            // Early return if no children or variants
			if (empty($childIds) || empty($variants)) {
				return [];
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

					$childrenData[] = [
						'id' => $child->id,
						'sku' => $child->sku,
						'attribute_value' => $attrValue,
						'slug' => $slug,
						'parent_slug' => $child->parent_category_url(),
						'child_slug' => $child->category_url(),
						'full_slug' => $full_slug,
						'parent_id' => $variant->parent_id,
                        'variant_id' => $variant->id,
					];
				}

				// Only add if we have children data
				if (!empty($childrenData)) {
					$result[] = [
                        'variant_id' => $variant->id,
						'attribute_id' => $attributeId,
						'attribute_name' => $attributeName,
						'label' => $v['labels'] ?? $attributeName,
						'type' => $v['type'] ?? 'dropdown',
						'child' => $childrenData,
					];
				}
			}
             
            // // Load child products
            // $children = \DB::table('ec_products')
            //     ->whereIn('id', $childIds)
            //     ->get(['id', 'name', 'sku']);

            // // Load attributes
            // $attributeIds = collect($variants)->pluck('attribute_id')->filter()->all();
            // $attributes = \DB::table('attributes')
            //     ->whereIn('id', $attributeIds)
            //     ->pluck('name', 'id');

            // // Map variant attributes
            // $mappedVariants = collect($variants)->map(function ($v) use ($attributes) {
            //     return [
            //         'attribute_id' => $v['attribute_id'],
            //         'attribute_name' => $attributes[$v['attribute_id']] ?? null,
            //         'label' => $v['labels'] ?? ($attributes[$v['attribute_id']] ?? null),
            //         'type' => $v['type'] ?? 'dropdown',
            //     ];
            // });

            // // ✅ Ensure unique attribute_name + label combination
            // $uniqueVariants = $mappedVariants->unique(function ($item) {
            //     return $item['attribute_name'] . '-' . $item['label'];
            // })->values();

            // $data = [
            //     'variant_id' => $variant->id,
            //     'parent_id' => $variant->parent_id,
            //     'parent_name' => $variant->parentProduct?->name,
            //     'parent_sku' => $variant->parentProduct?->sku,
            //     'variants' => $uniqueVariants,
            //     'child' => $children->map(fn($c) => [
            //         'id' => $c->id,
            //         'name' => $c->name,
            //         'sku' => $c->sku,
            //     ]),
            // ];

            return response()->json([
                'success' => true,
                'message' => 'Product variants fetched successfully',
                'data' => $result
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

            // Early return if no children or variants
			if (empty($childIds) || empty($variants)) {
				return [];
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

					$childrenData[] = [
						'id' => $child->id,
						'sku' => $child->sku,
						'attribute_value' => $attrValue,
						'slug' => $slug,
						'parent_slug' => $child->parent_category_url(),
						'child_slug' => $child->category_url(),
						'full_slug' => $full_slug,
						'parent_id' => $variant->parent_id,
						'variant_id' => $variant->id,
					];
				}

				// Only add if we have children data
				if (!empty($childrenData)) {
					$result[] = [
						'attribute_id' => $attributeId,
						'attribute_name' => $attributeName,
						'label' => $v['labels'] ?? $attributeName,
						'type' => $v['type'] ?? 'dropdown',
						'child' => $childrenData,
						'variant_id' => $variant->id,
					];
				}
			}
            return response()->json([
                'success' => true,
                'message' => 'Product variants fetched successfully',
                'data' => $result
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
