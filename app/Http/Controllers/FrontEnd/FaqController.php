<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Faq;
use OpenApi\Annotations as OA;


class FaqController extends Controller
{

        /**
     * @OA\Get(
     *     path="/api/frontend/faqs/product/{product_id}",
     *     operationId="getFaqsByProduct",
     *     tags={"Frontend - FAQs"},
     *     summary="Get FAQs by Product ID",
     *     description="Fetches a list of published FAQs related to a specific product.",
     *     @OA\Parameter(
     *         name="product_id",
     *         in="path",
     *         description="ID of the product",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with FAQ data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="question", type="string", example="What is the warranty period?"),
     *                     @OA\Property(property="answer", type="string", example="The product has a 1-year warranty."),
     *                     @OA\Property(property="product_id", type="integer", example=123)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found or no FAQs available"
     *     )
     * )
     */

    public function getFaqsByProduct($product_id): JsonResponse
    {
        $faqs = Faq::where('product_id', $product_id)
            ->where('status', 'published')
            ->get(['id', 'question', 'answer', 'product_id']);

        return response()->json([
            'success' => true,
            'data' => $faqs,
        ]);
    }
}
