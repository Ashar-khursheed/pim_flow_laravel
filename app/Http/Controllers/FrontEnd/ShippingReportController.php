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
     * @OA\Post(
     *     path="/api/find-shipping-charges",
     *     summary="Product shipping charges",
     *     description="Fetch products with their shipping charges by product ID(s) or slug.",
     *     tags={"Products Shipping Report"},
     *     
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="product_id",
     *                 type="array",
     *                 description="Filter by multiple product IDs",
     *                 @OA\Items(type="integer", example=101)
     *             ),
     *             @OA\Property(
     *                 property="slug",
     *                 type="string",
     *                 description="Filter by product slug",
     *                 example=""
     *             )
     *         )
     *     ),
     *      
     *     @OA\Response(
     *         response=200,
     *         description="Successful product shipping charge report",
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
     *                     @OA\Property(property="shipping_charge", type="number", format="float", example=49.99)
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
            $product_id = $request->input('product_id');
            $slug = $request->input('slug');
            $query = Product::with([
                'slug:id,key,reference_id',
                'productSuppliers',
                'seoProductUrl:id,relational_id,relational_type,url',


            ])->select(['id', 'name', 'sku', 'status', 'gen_type', 'approved']);

            // Filter by product_id
            if (!empty($product_id) && is_array($product_id)) {
                $query->wherein('id', $product_id);
            }

            // Filter by slug
            if ($slug != null) {
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

            $products = $query->orderBy('id', 'asc')->get();

            if (!$products->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product id not found',
                ], 404);
            }
            // Format response
            $formattedProducts = $products->map(function ($product) {
                $firstSupplier = $product->productSuppliers->first();
                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
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