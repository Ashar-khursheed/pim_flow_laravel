<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\ProductAttribute;
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

            $product = ProductVariant::with([
                'parentProduct:id,name,sku',
                'createdBy:id,username',
                'updatedBy:id,username'
            ])
                ->where('parent_id', $productIds)
                ->first();

            if (!$product) {
                return []; // or handle not found case
            }

            $productVariants = collect([$product])->map(function ($variant) {
                $childIds = json_decode($variant->child_ids, true) ?? [];
                $variants = json_decode($variant->variants, true) ?? [];

                // Fetch all child products
                $children = Product::whereIn('id', $childIds)
                    ->select('id', 'sku')
                    ->get();

                $result = [];

                foreach ($variants as $v) {
                    // Get attribute name
                    $attributeName = Attribute::where('id', $v['attribute_id'])->value('name');

                    // Map child product info
                    $childrenData = $children->map(function ($child) use ($v) {
                        $attrValue = ProductAttribute::where('product_id', $child->id)
                            ->where('attribute_id', $v['attribute_id'])
                            ->value('attribute_value');

                        $seo = SeoManagement::where('relational_id', $child->id)
                            ->select('url')
                            ->first();

                        $parentSlug = method_exists($child, 'parent_category_url')
                            ? $child->parent_category_url()
                            : null;

                        $childSlug = method_exists($child, 'category_url')
                            ? $child->category_url()
                            : null;

                        $fullSlug = trim(($parentSlug ? $parentSlug . '/' : '') . ($childSlug ?? '') . '/' . ($seo->url ?? ''), '/');

                        return [
                            'id' => $child->id,
                            'sku' => $child->sku,
                            'attribute_value' => $attrValue,
                            'slug' => $seo->url ?? null,
                            'parent_slug' => $parentSlug,
                            'child_slug' => $childSlug,
                            'full_slug' => $fullSlug,
                        ];
                    });

                    $result[] = [
                        'attribute_id' => $v['attribute_id'],
                        'attribute_name' => $attributeName,
                        'label' => $v['labels'] ?? $attributeName,
                        'type' => $v['type'] ?? 'dropdown',
                        'child' => $childrenData,
                    ];
                }
            
                return collect($result)
                    ->unique(function ($item) {
                        return $item['attribute_id'] . '_' . $item['label'];
                    })
                    ->values();
            })->flatten(1)->values();
            return response()->json([
                'success' => true,
                'message' => 'Attributes fetched successfully',
                'data' => $productVariants
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
