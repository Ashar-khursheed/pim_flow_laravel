<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class ClaudeAIService
{
    protected $apiUrl;
    protected $apiKey;
    protected $model;
    protected $version;

    public function __construct()
    {
        $this->apiUrl = config('services.claude.api_url');
        $this->apiKey = config('services.claude.api_key');
        $this->model = config('services.claude.model');
        $this->version = config('services.claude.version');
    }

    // public function generateReviews($productDescription)
    // {
    //     $prompt = "Generate a JSON response with 5 customer reviews for the following product. 
    //     STRICT REQUIREMENTS:
    //     - Realistic customer names
    //     - Valid-looking email formats
    //     - Detailed, positive comments
    //     - Star ratings between 4.5 and 5.0
    //     Product Description: $productDescription

    //     Expected JSON Format:
    //     {
    //       \"reviews\": [
    //         { \"customer_name\": \"John Doe\", \"customer_email\": \"johndoe@example.com\", \"comment\": \"Detailed review\", \"stars\": 5 }
    //       ]
    //     }";

    //     return $this->sendRequest($prompt);
    // }

//     public function generateReviews($productDescription)
// {
//     $prompt = "Generate a JSON response with 5 customer reviews for the following product.
//     STRICT REQUIREMENTS:
//     - 2 reviews in English, 2 in Arabic, and 1 in Russian.
//     - Use realistic names that match the language and region.
//     - Valid-looking email formats.
//     - Detailed, positive comments.
//     - Star ratings between 4.5 and 5.0.
//     - Ensure correct language usage for each review.
    
//     Product Description: $productDescription

//     Expected JSON Format:
//     {
//       \"reviews\": [
//         { \"customer_name\": \"John Doe\", \"customer_email\": \"johndoe@example.com\", \"comment\": \"Great product!\", \"stars\": 5, \"language\": \"English\" },
//         { \"customer_name\": \"Ahmed Al-Farsi\", \"customer_email\": \"ahmed.farsi@example.com\", \"comment\": \"منتج رائع!\", \"stars\": 4.8, \"language\": \"Arabic\" },
//         { \"customer_name\": \"Ivan Petrov\", \"customer_email\": \"ivan.petrov@example.com\", \"comment\": \"Отличный товар!\", \"stars\": 4.9, \"language\": \"Russian\" }
//       ]
//     }";

//     return $this->sendRequest($prompt);
// }
  public function generateReviews($productDescription)
  {
      $prompt = "Generate a JSON response with 3 to 5 customer reviews for the following product.
      STRICT REQUIREMENTS:
      - Reviews should be in a mix of English, Arabic, and Russian.
      - Use realistic names based on the region and language.
      - Valid-looking email formats.
      - Short and natural-sounding comments (not too generic or robotic).
      - Star ratings must be **only** 3, 4, or 5 (no decimals).
      - Ensure correct language usage for each review.
      - The number of reviews should be random between 3 and 5.

      Product Description: $productDescription

      Expected JSON Format:
      {
        \"reviews\": [
          { \"customer_name\": \"Emily Carter\", \"customer_email\": \"emily.carter@example.com\", \"comment\": \"Absolutely love it!\", \"stars\": 5, \"language\": \"English\" },
          { \"customer_name\": \"يوسف الحمادي\", \"customer_email\": \"y.hamadi@example.com\", \"comment\": \"جودة ممتازة وسعر مناسب!\", \"stars\": 4, \"language\": \"Arabic\" },
          { \"customer_name\": \"Анна Смирнова\", \"customer_email\": \"anna.smirnova@example.com\", \"comment\": \"Прекрасное качество, рекомендую!\", \"stars\": 3, \"language\": \"Russian\" }
        ]
      }";

      return $this->sendRequest($prompt);
  }




    public function generateFAQs($productDescription)
    {
        $prompt = "Generate a JSON response with 5 relevant FAQs for the following product.
Product Description: $productDescription

Expected JSON Format:
{
  \"faqs\": [
    { \"question\": \"Is it water-resistant?\", \"answer\": \"Yes, it is water-resistant up to 50m.\" }
  ]
}";

        return $this->sendRequest($prompt);
    }

    public function generateBenefitsAndFeatures($productDescription)
    {
        // Ensure proper JSON encoding of product description
        $productDescription = json_encode($productDescription, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
        $prompt = "Based only on the following product description, generate exactly 10 benefits and 10 features. Prioritize benefits and features that highlight the product's strongest ranking factors, best CPR, and most valuable keywords. Each benefit should be a concise heading (max 3 words), and each feature should be a short paragraph (max 30 words), ensuring the most competitive and high-impact elements appear first.:
        {
          \"benefits_features\": [
            {
              \"benefit\": \"Concise Benefit Title\",
              \"feature\": \"Detailed feature description (max 30 words)\"
            }
          ]
        }
    
        Product Description: $productDescription
    
        CRITICAL INSTRUCTIONS:
        1. Extract benefits and features DIRECTLY from the provided product description
        2. ALWAYS respond in the EXACT JSON format above
        3. Benefits: Max 3 words, capture key product advantages
        4. Features: Max 30 words, explain specific product capabilities
        5. Prioritize most unique and competitive aspects
        6. DO NOT add ANY additional text before or after JSON
        7. STRICTLY follow the specified output structure
    
        PROCESS:
        - Carefully read entire product description
        - Identify most compelling product attributes
        - Create concise, impactful benefits and features
        - Ensure 100% JSON compliance";
    
        return $this->sendRequest($prompt);
    }
    
    
    private function sendRequest($prompt)
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->version,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => $this->model,
                'max_tokens' => 2048,
                'messages' => [['role' => 'user', 'content' => $prompt]]
            ]);

            if (!$response->successful()) {
                return ['error' => 'API request failed. Response: ' . $response->body()];
            }

            $data = $response->json();
            if (isset($data['error'])) {
                return ['error' => 'Claude AI API Error: ' . $data['error']['message']];
            }

            return ['status' => 'success', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => 'API Request Failed: ' . $e->getMessage()];
        }
    }
}
