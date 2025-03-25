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

    public function generateReviewsAndFAQs($productDescription)
    {
        $prompt = "Generate a JSON-formatted response with 5 customer reviews and 5 FAQs for the following product. 

STRICT REQUIREMENTS:
1. Reviews must have:
   - Realistic customer names
   - Valid-looking email formats
   - Detailed, positive comments
   - Star ratings between 4.5 and 5.0 (use whole number 5)
2. FAQs must be relevant to the product
3. Respond ONLY with the exact JSON format specified

Product Description: $productDescription

Expected JSON Format:
{
  \"reviews\": [
    {
      \"customer_name\": \"John Doe\",
      \"customer_email\": \"johndoe@example.com\",
      \"comment\": \"Detailed positive review about the product's features\",
      \"stars\": 5
    }
    // 4 more reviews following this exact structure
  ],
  \"faqs\": [
    {
      \"question\": \"Is the product water-resistant?\",
      \"answer\": \"Comprehensive answer about water resistance\"
    }
    // 4 more FAQs following this exact structure
  ]
}

IMPORTANT: Provide ONLY the JSON. No additional text or explanation.";

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->version,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => $this->model,
                'max_tokens' => 2048,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            if (!$response->successful()) {
                return ['error' => 'API request failed. Response: ' . $response->body()];
            }

            $data = $response->json();
            if (isset($data['error'])) {
                return ['error' => 'Claude AI API Error: ' . $data['error']['message']];
            }

            return [
                'status' => 'success',
                'data' => $data
            ];
        } catch (\Exception $e) {
            return ['error' => 'API Request Failed: ' . $e->getMessage()];
        }
    }
}