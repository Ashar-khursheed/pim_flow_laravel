<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Models\FrontEnd\AlternateProduct;
class CompareProductController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/compare-table-product",
     *     summary="Fetch product compare by ID",
     *     description="Accepts a single product ID and returns its details",
     *     tags={"Compare Products"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="product_id",
     *                 type="integer",
     *                 example=101,
     *                 description="Single product ID to compare"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=101),
     *                 @OA\Property(property="sku", type="string", example="SKU12345"),
     *                 @OA\Property(property="name", type="string", example="Test Product")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     )
     * )
     */
    public function getCompareTableProduct(Request $request)
    {
        $request->validate([
            'product_id' => "required|integer"
        ]);

        $mainProductId = trim($request->input('product_id'));

        // get alternates
        $alternateProduct = AlternateProduct::where('product_id', $mainProductId)
            ->orderBy('priority', 'asc')
            ->get();
        if ($alternateProduct->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'data' => [],
            ]);
        }

        $productIds = collect([$mainProductId])
            ->merge($alternateProduct->pluck('product_alternate_id'))
            ->unique()
            ->values();

        $products = Product::with([
            'brand:id,name',
            'categories:id,name',
            'productAttributes.attributeDetails',
            'productAttributes.measurementUnit',
            'reviews:id,product_id,star',
            'productSuppliers',
        ])
            ->whereIn('id', $productIds)
            ->select([
                'id',
                'name',
                'sku',
                'status',
                'images',
                'currency_id',
                'barcode'

            ])
            ->get()
            ->keyBy('id'); // for quick lookup

        $allProducts = collect([
            (object) [
                'product_alternate_id' => $mainProductId,
                'id' => null, // main product won't have alternate table fields
                'status' => null,
                'priority' => null,
                'similarity' => null,
                'order' => null,
                'created_at' => null,
                'updated_by' => null,
                'created_by' => null,
                'rejected_by' => null,
                'reason' => null,
            ],
        ])->merge($alternateProduct);
        $formattedProducts = $allProducts->map(function ($alt) use ($products, $mainProductId) {
            $product = $products->get($alt->product_alternate_id ?? $mainProductId);

            if (!$product) {
                return null;
            }

            $firstSupplier = $product->productSuppliers->first();

            // Format product attributes
            $product_attributes = $product->productAttributes->map(function ($attr) {
                return [
                    'attribute_id' => $attr->attribute_id,
                    'attribute_name' => $attr->attributeDetails->name ?? null,
                    'attribute_value' => $attr->attribute_value,
                    'measurement_unit_id' => $attr->measurement_unit_id,
                    'measurement_unit_name' => $attr->measurementUnit->name ?? null,
                ];
            });

            return [
                'id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_status' => $product->status,
                'product_images' => is_array($product->images)
                    ? $product->images
                    : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null),

                // Supplier info
                'vendor_sku' => $firstSupplier->vendor_sku ?? null,
                'price' => $firstSupplier?->price ? (float) $firstSupplier->price : null,
                'sale_price' => $firstSupplier?->sale_price ? (float) $firstSupplier->sale_price : null,
                'original_price' => $firstSupplier?->price ? (float) $firstSupplier->price : null,
                'front_sale_price' => $firstSupplier?->sale_price ? (float) $firstSupplier->sale_price : null,
                'best_price' => $firstSupplier?->price ? (float) $firstSupplier->price : null,
                'per_unit_price' => $product->per_unit_price ?? null,
                'vendor_id' => $firstSupplier->vendor_id ?? null,
                'map' => $firstSupplier?->map ? (float) $firstSupplier->map : null,
                'inventory' => $firstSupplier->inventory ?? null,
                'in_stock' => $firstSupplier->in_stock ?? null,
                'delivery_days' => $firstSupplier->delivery_days ?? null,
                'return_policy' => $firstSupplier->return_policy ?? null,
                'free_shipping' => $firstSupplier->free_shipping ?? null,
                'totalReviews' => $product->reviews->count(),
                'avgRating' => $product->reviews->count() > 0 ? $product->reviews->avg('star') : null,
                'warranty_information' => $firstSupplier->warranty_information ?? null,

                // alternate fields
                'alt_id' => $alt->id,
                'alt_status' => $alt->status,
                'product_alternate_id' => $alt->product_alternate_id ?? $mainProductId,
                'priority' => $alt->priority,
                'similarity' => $alt->similarity,
                'order' => $alt->order,
                'alt_created' => $alt->created_at,
                'alt_updated_by' => $alt->updated_by,
                'alt_created_by' => $alt->created_by,
                'alt_rejected_by' => $alt->rejected_by,
                'reason' => $alt->reason,

                'brand' => $product->brand?->name,
                'product_attributes' => $product_attributes,
                'categories' => $product->categories->pluck('name'),
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'message' => 'Product & alternates fetched successfully',
            'data' => $formattedProducts,
        ]);

    }

}
