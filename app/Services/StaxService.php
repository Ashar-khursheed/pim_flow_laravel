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
        $this->baseUrl = config('services.stax.base_url', 'https://apiprod.fattlabs.com');
        $this->apiKey = config('services.stax.api_key');

        if (empty($this->apiKey)) {
            Log::error('Stax API Key not configured');
            throw new \Exception('Stax API credentials not configured');
        }
    }

    /**
     * Process a charge using Stax API
     * Based on: https://api-docs.staxpayments.com/reference/create-a-charge
     * 
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function charge(array $data)
    {
        Log::info('StaxService::charge called', $data);

        // Validate required fields
        if (empty($data['payment_method'])) {
            throw new \Exception('Payment method ID is required');
        }

        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new \Exception('Valid amount is required');
        }

        // Build the payload according to Stax API specifications
        $payload = [
            'total' => (float) $data['amount'],
            'payment_method_id' => $data['payment_method'],
        ];

        // Add optional fields if provided
        if (!empty($data['pre_auth'])) {
            $payload['pre_auth'] = (bool) $data['pre_auth'];
        }

        // Add metadata
        if (!empty($data['meta'])) {
            $payload['meta'] = $data['meta'];
        } else {
            $payload['meta'] = [
                'tax' => 0,
                'subtotal' => (float) $data['amount'],
            ];
        }

        // Add customer information if provided
        if (!empty($data['customer'])) {
            $customer = $data['customer'];
            if (!empty($customer['firstname'])) $payload['firstname'] = $customer['firstname'];
            if (!empty($customer['lastname'])) $payload['lastname'] = $customer['lastname'];
            if (!empty($customer['email'])) $payload['email'] = $customer['email'];
            if (!empty($customer['phone'])) $payload['phone'] = $customer['phone'];
            if (!empty($customer['address_1'])) $payload['address_1'] = $customer['address_1'];
            if (!empty($customer['address_city'])) $payload['address_city'] = $customer['address_city'];
            if (!empty($customer['address_state'])) $payload['address_state'] = $customer['address_state'];
            if (!empty($customer['address_zip'])) $payload['address_zip'] = $customer['address_zip'];
            if (!empty($customer['address_country'])) $payload['address_country'] = $customer['address_country'];
        }

        Log::info('Stax API Request', [
            'url' => $this->baseUrl . '/charge',
            'payload' => $payload,
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl . '/charge', $payload);

            $statusCode = $response->status();
            $responseBody = $response->json();

            Log::info('Stax API Response', [
                'status' => $statusCode,
                'body' => $responseBody,
            ]);

            if ($response->successful()) {
                return $responseBody;
            }

            // Extract error message
            $errorMessage = $this->extractErrorMessage($responseBody);
            
            Log::error('Stax API Error', [
                'status' => $statusCode,
                'error' => $errorMessage,
                'response' => $responseBody,
            ]);

            throw new \Exception($errorMessage);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Stax Connection Error', ['error' => $e->getMessage()]);
            throw new \Exception('Unable to connect to payment gateway');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'payment') !== false || 
                strpos($e->getMessage(), 'Payment') !== false ||
                strpos($e->getMessage(), 'gateway') !== false) {
                throw $e;
            }
            
            Log::error('Stax Unexpected Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new \Exception('Payment processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Extract error message from API response
     */
    protected function extractErrorMessage($response): string
    {
        if (is_string($response)) {
            return $response;
        }

        if (is_array($response)) {
            // Check for common error fields
            if (!empty($response['message'])) {
                return $response['message'];
            }
            
            if (!empty($response['error'])) {
                if (is_string($response['error'])) {
                    return $response['error'];
                }
                if (is_array($response['error'])) {
                    if (!empty($response['error']['message'])) {
                        return $response['error']['message'];
                    }
                    return json_encode($response['error']);
                }
            }
            
            if (!empty($response['errors'])) {
                if (is_array($response['errors'])) {
                    $errors = [];
                    foreach ($response['errors'] as $field => $messages) {
                        if (is_array($messages)) {
                            $errors[] = implode(', ', $messages);
                        } else {
                            $errors[] = $messages;
                        }
                    }
                    return implode('; ', $errors);
                }
                return (string) $response['errors'];
            }

            // If response has data but no clear error
            if (!empty($response['data'])) {
                return 'Payment failed: ' . json_encode($response['data']);
            }
        }

        return 'Payment processing failed. Please try again.';
    }

    /**
     * Create or get customer
     */
    public function createCustomer(array $data)
    {
        $payload = [
            'firstname' => $data['firstname'] ?? '',
            'lastname' => $data['lastname'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
        ];

        // Add optional address fields
        if (!empty($data['address_1'])) $payload['address_1'] = $data['address_1'];
        if (!empty($data['address_2'])) $payload['address_2'] = $data['address_2'];
        if (!empty($data['address_city'])) $payload['address_city'] = $data['address_city'];
        if (!empty($data['address_state'])) $payload['address_state'] = $data['address_state'];
        if (!empty($data['address_zip'])) $payload['address_zip'] = $data['address_zip'];
        if (!empty($data['address_country'])) $payload['address_country'] = $data['address_country'];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl . '/customer', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception($this->extractErrorMessage($response->json()));

        } catch (\Exception $e) {
            Log::error('Stax Create Customer Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get transaction details
     */
    public function getTransaction(string $transactionId)
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl . '/transaction/' . $transactionId);

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
     */
    public function refund(string $transactionId, array $data)
    {
        $payload = [
            'total' => (float) $data['amount'],
        ];

        if (!empty($data['reason'])) {
            $payload['reason'] = $data['reason'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl . '/transaction/' . $transactionId . '/refund', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception($this->extractErrorMessage($response->json()));

        } catch (\Exception $e) {
            Log::error('Stax Refund Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Void a transaction
     */
    public function void(string $transactionId)
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl . '/transaction/' . $transactionId . '/void');

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception($this->extractErrorMessage($response->json()));

        } catch (\Exception $e) {
            Log::error('Stax Void Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}