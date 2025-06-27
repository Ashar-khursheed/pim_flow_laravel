<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\FrontEnd\ProductQuestion;

class ProductQuestionController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/product-questions",
     *     tags={"Frontend-Product Questions"},
     *     summary="Submit a question for a product",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "question"},
     *             @OA\Property(property="product_id", type="integer", example=123),
     *             @OA\Property(property="question", type="string", example="Is this dishwasher safe?")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Question submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question submitted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="customer_id", type="integer", example=45),
     *                 @OA\Property(property="product_id", type="integer", example=123),
     *                 @OA\Property(property="question", type="string", example="Is this dishwasher safe?"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:ec_products,id',
            'question' => 'required|string',
        ]);

        $question = ProductQuestion::create([
            'email' => $request->email,
            'product_id' => $request->product_id,
            'question' => $request->question,
        ]);

        return response()->json(['message' => 'Question submitted successfully', 'data' => $question], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/product-questions/{product_id}",
     *     tags={"Frontend-Product Questions"},
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
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="customer", type="object",
     *                     @OA\Property(property="id", type="integer", example=45),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    public function index($product_id)
    {
        $questions = ProductQuestion::with('email')
            ->where('product_id', $product_id)
            ->latest()
            ->get();

        return response()->json($questions);
    }
}
