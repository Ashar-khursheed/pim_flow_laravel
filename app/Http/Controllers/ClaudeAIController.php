<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ClaudeAIService;
use Illuminate\Support\Facades\Log;

class ClaudeAIController extends Controller
{
    protected $claudeService;

    public function __construct(ClaudeAIService $claudeService)
    {
        $this->claudeService = $claudeService;
    }

    /**
     * @OA\Post(
     *     path="/api/generate-reviews",
     *     summary="Generate customer reviews based on product description",
     *     tags={"Reviews"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_description"},
     *             @OA\Property(property="product_description", type="string", example="A smartwatch with heart rate monitoring and OLED display.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully generated reviews",
     *         @OA\JsonContent(
     *             @OA\Property(property="reviews", type="array", @OA\Items(
     *                 @OA\Property(property="customer_name", type="string"),
     *                 @OA\Property(property="customer_email", type="string"),
     *                 @OA\Property(property="comment", type="string"),
     *                 @OA\Property(property="stars", type="integer", minimum=4, maximum=5)
     *             ))
     *         )
     *     )
     * )
     */
    // public function generateReviews(Request $request)
    // {
    //     $productDescription = $request->input('product_description');
    //     return $this->handleAIResponse($this->claudeService->generateReviews($productDescription));
    // }
    public function generateReviews(Request $request)
    {
        $productDescription = $request->input('product_description');
        return $this->handleAIResponse($this->claudeService->generateReviews($productDescription));
    }
    
    /**
     * @OA\Post(
     *     path="/api/generate-faqs",
     *     summary="Generate FAQs based on product description",
     *     tags={"FAQs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_description"},
     *             @OA\Property(property="product_description", type="string", example="A smartwatch with heart rate monitoring and OLED display.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully generated FAQs",
     *         @OA\JsonContent(
     *             @OA\Property(property="faqs", type="array", @OA\Items(
     *                 @OA\Property(property="question", type="string"),
     *                 @OA\Property(property="answer", type="string")
     *             ))
     *         )
     *     )
     * )
     */
    public function generateFAQs(Request $request)
    {
        $productDescription = $request->input('product_description');
        return $this->handleAIResponse($this->claudeService->generateFAQs($productDescription));
    }

    /**
     * @OA\Post(
     *     path="/api/generate-benefits-features",
     *     summary="Generate benefits and features based on product description",
     *     tags={"Benefits & Features"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_description"},
     *             @OA\Property(property="product_description", type="string", example="A smartwatch with heart rate monitoring and OLED display.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully generated benefits and features",
     *         @OA\JsonContent(
     *             @OA\Property(property="benefits_features", type="array", @OA\Items(
     *                 @OA\Property(property="heading", type="string"),
     *                 @OA\Property(property="description", type="string")
     *             ))
     *         )
     *     )
     * )
     */
    public function generateBenefitsFeatures(Request $request)
    {
        $productDescription = $request->input('product_description');
    
        if (empty($productDescription)) {
            return response()->json(['status' => 'error', 'message' => 'Product description is required'], 400);
        }
    
        try {
            $aiResponse = $this->claudeService->generateBenefitsAndFeatures($productDescription);
    
            return $this->handleAIResponse($aiResponse);
        } catch (\Exception $e) {
            Log::error('AI Service Error', ['error' => $e->getMessage()]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while generating benefits and features.',
            ], 500);
        }
    }
    
    private function handleAIResponse($aiResponse)
    {
        if (isset($aiResponse['error'])) {
            return response()->json(['status' => 'error', 'message' => $aiResponse['error']], 500);
        }
    
        $responseText = $aiResponse['data']['content'][0]['text'] ?? '';
    
        if (empty($responseText)) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI response is empty or malformed.'
            ], 500);
        }
    
        try {
            $parsedResponse = json_decode($responseText, true, 512, JSON_THROW_ON_ERROR);
            return response()->json(['status' => 'success'] + $parsedResponse);
        } catch (\Exception $e) {
            Log::error('JSON Parsing Error', ['error' => $e->getMessage(), 'response_text' => $responseText]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to parse AI response',
                'raw_response' => $responseText
            ], 500);
        }
    }
    
}
