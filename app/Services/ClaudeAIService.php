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

    public function generateReviews($productDescription)
    {
        $prompt = "Generate a JSON response with 5 customer reviews for the following product. 
STRICT REQUIREMENTS:
- Realistic customer names
- Valid-looking email formats
- Detailed, positive comments
- Star ratings between 4.5 and 5.0
Product Description: $productDescription

Expected JSON Format:
{
  \"reviews\": [
    { \"customer_name\": \"John Doe\", \"customer_email\": \"johndoe@example.com\", \"comment\": \"Detailed review\", \"stars\": 5 }
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
        $prompt = "Based only on the following product description, generate exactly 10 benefits and 10 features.
Each benefit should be a concise heading (max 3 words), and each feature should be a short paragraph (max 30 words).
Strictly follow this format:

Example Format:
{
  \"benefits_features\": [
    { \"benefit\": \"Fast Performance\", \"feature\": \"This product ensures high-speed performance, reducing wait times and improving efficiency for seamless usage.\" },
    { \"benefit\": \"Long Battery\", \"feature\": \"The extended battery life allows continuous use for hours without needing frequent recharges.\" }
  ]
}

Product Description: $productDescription";

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
