<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\SeoManagement;
use App\Models\ProductSupplier;
class ShippingReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/find-shipping-charges",
     *     summary="Product shipping charges",
     *     description="Report of products display with id, sku, name, benefit features description, attribute count, price and graphics yes no reports published draft products.",
     *     tags={"Products Shipping Report"},
     *     
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Filter by product ID",
     *         required=false,
     *         @OA\Schema(type="integer", example="")
     *     ),
     *     @OA\Parameter(
     *         name="slug",
     *         in="query",
     *         description="Filter by product slug",
     *         required=false,
     *         @OA\Schema(type="string", example="")
     *     ),
     *      
     *     @OA\Response(
     *         response=200,
     *         description="Successful product benefit report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=101),
     *                     @OA\Property(property="sku", type="string", example="SKU12345"),
     *                     @OA\Property(property="name", type="string", example="Sample Product"),
     *                     @OA\Property(property="benefits", type="string", example="Lightweight, Durable"),
     *                     @OA\Property(property="attribute_count", type="integer", example=5),
     *                     @OA\Property(property="price", type="number", format="float", example=499.99),
     *                     @OA\Property(property="graphics", type="string", enum={"yes","no"}, example="yes"),
     *                     @OA\Property(property="status", type="string", enum={"all","publish","draft"}, example="publish")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid range values")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing token",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     * )
     */
    public function findShippingCharges(Request $request)
    {
        try {
            $query = Product::with([
                'slug:id,key,reference_id',
                'productSuppliers',
                'seoProductUrl:id,relational_id,relational_type,url',


            ])->select(['id', 'name', 'sku', 'status', 'gen_type', 'approved']);

            // Filter by product_id
            if ($request->has('product_id')) {
                $query->where('id', $request->product_id);
            }

            // Filter by slug
            if ($request->has('slug')) {

                $seoUrlCheck = SeoManagement::where('url', $request->slug)->first();

                if (!$seoUrlCheck) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product slug not found',
                    ], 404);
                }

                // Match product via relation
                $query->whereHas('seoProductUrl', function ($q) use ($request) {
                    $q->where('url', $request->slug);
                });
            }

            $products = $query->limit(5)->orderBy('id', 'asc')->get();

            // Format response
            $formattedProducts = $products->map(function ($product) {
                $firstSupplier = $product->productSuppliers->first();
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $firstSupplier ? (float) $firstSupplier->price : null,
                    'surcharge' => $firstSupplier ? (float) $firstSupplier->surcharge : null,
                    'cost_per_item' => $firstSupplier ? (float) $firstSupplier->cost_per_items : null,
                    'additional_cost' => $firstSupplier ? (float) $firstSupplier->additional_cost : null,
                    'shipping_charge' => $firstSupplier ? (float) $firstSupplier->shipping_charge : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedProducts
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 400);
        }

    }
}


