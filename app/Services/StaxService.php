<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StaxService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.stax.base_url');
        $this->apiKey = config('services.stax.api_key');
    }

    /**
     * Process a charge using Stax API
     * 
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function charge(array $data)
    {
        // Validate required data
        if (empty($data['payment_method'])) {
            throw new \Exception('Payment method (token) is required');
        }

        if (empty($data['amount'])) {
            throw new \Exception('Amount is required');
        }

        // Prepare the payload for Stax API
        // The token from Stax.js is actually a payment_method_id
        $payload = [
            'total' => $data['amount'],
            'meta' => [
                'tax' => 0,
                'subtotal' => $data['amount'],
            ],
            'payment_method_id' => $data['payment_method'], // This is the token from frontend
        ];

        Log::info('Stax Charge Request', ['payload' => $payload]);

        try {
            // Make request to Stax API - correct endpoint is /charge
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/charge', $payload);

            Log::info('Stax Charge Response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            // Handle error response
            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? 'Unknown error occurred';
            
            throw new \Exception($errorMessage);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Stax API Request Failed', [
                'error' => $e->getMessage(),
                'response' => $e->response ? $e->response->json() : null
            ]);
            throw new \Exception('Payment processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a customer in Stax
     * 
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function createCustomer(array $data)
    {
        $payload = [
            'firstname' => $data['firstname'] ?? '',
            'lastname' => $data['lastname'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'address_1' => $data['address_1'] ?? '',
            'address_city' => $data['address_city'] ?? '',
            'address_state' => $data['address_state'] ?? '',
            'address_zip' => $data['address_zip'] ?? '',
            'address_country' => $data['address_country'] ?? 'USA',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/customer', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception($response->json()['message'] ?? 'Customer creation failed');

        } catch (\Exception $e) {
            Log::error('Stax Create Customer Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get transaction details
     * 
     * @param string $transactionId
     * @return array
     * @throws \Exception
     */
    public function getTransaction(string $transactionId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/transaction/' . $transactionId);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Transaction not found');

        } catch (\Exception $e) {
            Log::error('Stax Get Transaction Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Refund a transaction
     * 
     * @param string $transactionId
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function refund(string $transactionId, array $data)
    {
        $payload = [
            'total' => $data['amount'],
            'reason' => $data['reason'] ?? 'Customer request',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/transaction/' . $transactionId . '/refund', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Refund failed');

        } catch (\Exception $e) {
            Log::error('Stax Refund Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Tokenize payment method (if needed separately)
     * 
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function tokenize(array $data)
    {
        $payload = [
            'person_name' => $data['person_name'] ?? '',
            'card_number' => $data['card_number'] ?? '',
            'card_exp' => $data['card_exp'] ?? '',
            'card_cvv' => $data['card_cvv'] ?? '',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/tokenize', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Tokenization failed');

        } catch (\Exception $e) {
            Log::error('Stax Tokenize Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}