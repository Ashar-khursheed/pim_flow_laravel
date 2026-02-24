<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Faq;
use App\Models\Product;
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
	public function getFaqsByProduct($productInput): JsonResponse
	{
		/* Resolve product by ID or slug */
		if (is_numeric($productInput)) {
			$product = Product::with('faqs')->find((int) $productInput);
		} else {
			$product = Product::with('faqs')
			->whereHas('seoUrl', function ($q) use ($productInput) {
				$q->where('url', $productInput);
			})
			->first();
		}

		/* Check if product exists */
		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found.',
			], 404);
		}

		return response()->json([
			'success' => true,
			'message' => 'Product FAQs retrieved successfully',
			'data' => $product->faqs
		], 200);
	}
}
