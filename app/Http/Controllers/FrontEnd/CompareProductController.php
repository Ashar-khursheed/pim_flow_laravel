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
            'product_id' => "required"
        ]);

        $alternateProduct = AlternateProduct::where('product_id', $request->input('product_id'))->orderBy('priority', 'asc')->get();
        $formattedProducts = $alternateProduct->map(function ($product) {

            $products = Product::with([
                'brand:id,name',
                'categories:id,name',
                'productAttributes.attributeDetails',
                'productAttributes.measurementUnit',
                'reviews:id'

            ])
                ->where('id', $product->product_alternate_id)
                ->select([
                    'id',
                    'name',
                    'sku',
                    'status',
                    'images',
                    'currency_id',
                    'barcode',
                ])
                ->first();

            $firstSupplier = $products->productSuppliers->first();

            foreach ($products->productAttributes as $attr) {
                $product_attributes[] = [
                    'attribute_id' => $attr->attribute_id,
                    'attribute_name' => $attr->attributeDetails->name ?? null,
                    'attribute_value' => $attr->attribute_value,
                    'measurement_unit_id' => $attr->measurement_unit_id,
                    'measurement_unit_name' => $attr->measurementUnit->name ?? null,
                ];
            }
            return [
                'id' => $products->id,
                'product_name' => $products->name,
                'product_sku' => $products->sku,
                'product_status' => $products->status,
                'product_images' => is_array($products->images) ? $product->images : (is_array($decoded = json_decode($products->images, true)) ? $decoded : null),
                'vendor_sku' => $firstSupplier->vendor_sku ?? null,
                'price' => $firstSupplier ? (float) $firstSupplier->price : null,
                'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
                'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
                'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
                'best_price' => $firstSupplier ? (float) $firstSupplier->price : null,
                'per_unit_price' => $product->per_unit_price,
                'vendor_id' => $firstSupplier->vendor_id ?? null,
                'map' => $firstSupplier ? (float) $firstSupplier->map : null,
                'inventory' => $firstSupplier->inventory ?? null,
                'in_stock' => $firstSupplier->in_stock ?? null,
                'delivery_days' => $firstSupplier->delivery_days ?? null,
                'return_policy' => $firstSupplier->return_policy ?? null,
                'free_shipping' => $firstSupplier->free_shipping ?? null,
                'totalReviews' => $products->reviews?->count() ?? 0,
                'avgRating' => $products->reviews?->count() > 0 ? $products->reviews->avg('star') : null,
                'warranty_information' => $firstSupplier->warranty_information ?? null,

                'alt_id' => $product->id,
                'alt_status' => $product->status,
                'product_alternate_id' => $product->product_alternate_id,
                'priority' => $product->priority,
                'similarity' => $product->similarity,
                'order' => $product->order,
                'alt_created' => $product->created_at,
                'alt_updated_by' => $product->updated_by,
                'alt_created_by' => $product->created_by,
                'alt_rejected_by' => $product->rejected_by,
                'reason' => $product->reason,
                'brand' => $product->brand ? $product->brand->name : null,
                'product_attributes' => $product_attributes,
                'categories' => $products->categories->pluck('name'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $formattedProducts,

        ]);
    }
}
