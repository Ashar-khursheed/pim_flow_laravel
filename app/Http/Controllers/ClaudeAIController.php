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
     *     path="/api/generate-reviews-faqs",
     *     summary="Generate reviews and FAQs based on product description",
     *     tags={"Reviews & FAQs"},
     *     security={{"bearerAuth": {}}},  
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_description"},
     *             @OA\Property(property="product_description", type="string", example="A high-quality smartwatch with heart rate monitoring and OLED display.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully generated reviews and FAQs",
     *         @OA\JsonContent(
     *             @OA\Property(property="reviews", type="array", @OA\Items(
     *                 @OA\Property(property="customer_name", type="string"),
     *                 @OA\Property(property="customer_email", type="string"),
     *                 @OA\Property(property="comment", type="string"),
     *                 @OA\Property(property="stars", type="number", format="float", minimum=4.5, maximum=5)
     *             )),
     *             @OA\Property(property="faqs", type="array", @OA\Items(
     *                 @OA\Property(property="question", type="string"),
     *                 @OA\Property(property="answer", type="string")
     *             ))
     *         )
     *     )
     * )
     */
    public function generateReviewsAndFAQs(Request $request)
    {
        $productDescription = $request->input('product_description');
    
        // Correct AI service call
        $aiResponse = $this->claudeService->generateReviewsAndFAQs($productDescription);
    
        if (isset($aiResponse['error'])) {
            return response()->json(['status' => 'error', 'message' => $aiResponse['error']], 500);
        }
    
        // Extract the generated text content
        $responseText = $aiResponse['data']['content'][0]['text'] ?? '';
    
        // Attempt to parse the JSON
        try {
            // Use a more robust JSON parsing method
            $parsedResponse = json_decode($responseText, true, 512, JSON_THROW_ON_ERROR);
            
            // Validate the parsed response
            if (!isset($parsedResponse['reviews']) || !isset($parsedResponse['faqs'])) {
                throw new \Exception('Invalid response format');
            }
    
            // Return the parsed JSON directly
            return response()->json([
                'status' => 'success',
                'reviews' => $parsedResponse['reviews'],
                'faqs' => $parsedResponse['faqs']
            ]);
        } catch (\Exception $e) {
            // Log the error and the response text for debugging
            Log::error('JSON Parsing Error', [
                'error' => $e->getMessage(),
                'response_text' => $responseText
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to parse AI response',
                'raw_response' => $responseText
            ], 500);
        }
    }
}