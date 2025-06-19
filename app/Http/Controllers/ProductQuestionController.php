<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\FrontEnd\ProductQuestion;

class ProductQuestionController extends Controller
{
  
  /**
     * @OA\Get(
     *     path="/api/product-questions/{product_id}",
     *     tags={"Product Questions"},
     *      security={{"bearerAuth": {}}},
     *     summary="Get all questions for a product",
     *     @OA\Parameter(
     *         name="product_id",
     *         in="path",
     *         required=true,
     *         description="ID of the product",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of product questions",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="customer_id", type="integer", example=45),
     *                 @OA\Property(property="product_id", type="integer", example=123),
     *                 @OA\Property(property="question", type="string", example="Is this dishwasher safe?"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-06-19T12:34:56Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-06-19T12:34:56Z"),
     *                 
     *                 @OA\Property(
     *                     property="customer",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=45),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="name", type="string", example="Electric Kettle"),
     *                     @OA\Property(property="sku", type="string", example="KETTLE-001"),
     *                     
     *                     @OA\Property(
     *                         property="brand",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="name", type="string", example="Philips")
     *                     ),
     *                     
     *                     @OA\Property(
     *                         property="vendor",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=22),
     *                         @OA\Property(property="name", type="string", example="Vendor Store")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */


     public function index($product_id)
     {
         $questions = ProductQuestion::with([
                 'customer:id,id,name,email',
                 'product:id,id,name,sku,brand_id,vendor_id',
                 'product.brand:id,id,name',
                 'product.vendor:id,id,name'
             ])
             ->where('product_id', $product_id)
             ->latest()
             ->get();
     
         return response()->json($questions);
     }
     
}
