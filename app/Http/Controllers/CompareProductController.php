<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
class CompareProductController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/compare-table-product",
     *     summary="Fetch multiple products compare by IDs",
     *     description="Accepts an array of product IDs and returns their details",
     *     tags={"Compare Products"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="product_ids",
     *                 type="array",
     *                 description="Array of Product IDs",
     *                 @OA\Items(type="integer", example=101)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=101),
     *                     @OA\Property(property="sku", type="string", example="SKU12345"),
     *                     @OA\Property(property="name", type="string", example="Test Product")                      
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getCompareTableProduct(Request $request)
    {
        $ids = $request->input('product_ids', []);
        if (!$ids) {
            return response()->json([
                'success' => false,
                'message' => 'Compare Products ids required!',
                'data' => [],

            ]);
        }
        $products = Product::with([
            'alternateProducts',
            'brand:id,name',
            'categories:id,name'
        ])
            ->whereIn('id', $ids)
            ->select([
                'id',
                'name',
                'sku',
                'status',
                'images',
                'currency_id',
                'barcode',

            ])
            ->paginate(10);

        $formattedProducts = $products->map(function ($product) {
            $firstSupplier = $product->productSuppliers->first();

            return [
                'id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_status' => $product->status,
                'product_images' => $product->images,
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
                'warranty_information' => $firstSupplier->warranty_information ?? null,
                // If alternateProducts is a relation (many records), map them
                'alternates' => $product->alternateProducts->map(function ($alt) {
                    return [
                        'alt_id' => $alt->id,
                        'alt_status' => $alt->status,
                        'product_alternate_id' => $alt->product_alternate_id,
                        'priority' => $alt->priority,
                        'similarity' => $alt->similarity,
                        'order' => $alt->order,
                        'alt_created' => $alt->created_at,
                        'alt_updated_by' => $alt->updated_by,
                        'alt_created_by' => $alt->created_by,
                        'alt_rejected_by' => $alt->rejected_by,
                        'reason' => $alt->reason,
                    ];
                }),

                'brand' => $product->brand ? $product->brand->name : null,
                'categories' => $product->categories->pluck('name'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $formattedProducts,
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
            ],
        ]);
    }
}
