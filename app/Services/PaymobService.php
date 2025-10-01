<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymobService
{
    protected $secretKey;
    protected $publicKey;
    protected $integrationId;
    protected $baseUrl;

    public function __construct()
    {
        // Use Intention API (new API)
        $this->baseUrl = config('services.paymob.base_url', 'https://uae.paymob.com/v1');
        $this->secretKey = config('services.paymob.secret_key'); // sk_test_xxx or sk_live_xxx
        $this->publicKey = config('services.paymob.public_key'); // are_pk_test_xxx or are_pk_live_xxx
        $this->integrationId = config('services.paymob.integration_id'); // Card integration ID
    }

    /**
     * Create Payment Intention (for Pixel SDK)
     * This is what the frontend needs
     */
    public function createIntention($amount, $billingData, $items = [])
    {
        try {
            $amountCents = (int) round($amount * 100); // Convert to minor units (cents/fils)

            // Default items if not provided
            if (empty($items)) {
                $items = [[
                    'name'        => 'Order Payment',
                    'amount'      => $amountCents,
                    'description' => 'Checkout payment',
                    'quantity'    => 1,
                ]];
            }

            // Ensure all item amounts are in minor units
            foreach ($items as &$item) {
                if (!isset($item['amount'])) {
                    $item['amount'] = $amountCents;
                }
                $item['amount'] = (int) round($item['amount']);
            }

            $payload = [
                'amount'          => $amountCents,
                'currency'        => 'EGP', // or 'AED', 'SAR', 'KWD', etc.
                'payment_methods' => [(int) $this->integrationId], // MUST be array of integers
                'items'           => $items,
                'billing_data'    => [
                    'first_name'   => $billingData['first_name'] ?? 'NA',
                    'last_name'    => $billingData['last_name'] ?? 'NA',
                    'email'        => $billingData['email'] ?? 'test@example.com',
                    'phone_number' => $billingData['phone_number'] ?? '+201000000000',
                    'apartment'    => $billingData['apartment'] ?? 'NA',
                    'floor'        => $billingData['floor'] ?? 'NA',
                    'street'       => $billingData['street'] ?? 'NA',
                    'building'     => $billingData['building'] ?? 'NA',
                    'shipping_method' => $billingData['shipping_method'] ?? 'NA',
                    'postal_code'  => $billingData['postal_code'] ?? 'NA',
                    'city'         => $billingData['city'] ?? 'Cairo',
                    'country'      => $billingData['country'] ?? 'EG',
                    'state'        => $billingData['state'] ?? 'NA',
                ],
                'customer' => [
                    'first_name'   => $billingData['first_name'] ?? 'NA',
                    'last_name'    => $billingData['last_name'] ?? 'NA',
                    'email'        => $billingData['email'] ?? 'test@example.com',
                    'phone_number' => $billingData['phone_number'] ?? '+201000000000',
                ],
                // Optional: Add special reference ID
                'special_reference' => 'ORDER_' . uniqid(),
            ];

            Log::info('Creating Paymob Intention', [
                'amount' => $amountCents,
                'integration_id' => $this->integrationId
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->secretKey,
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/intention/', $payload);

            if ($response->failed()) {
                Log::error('Paymob Intention failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => $payload
                ]);
                throw new \Exception("Paymob Intention failed: " . $response->body());
            }

            $data = $response->json();
            
            if (!isset($data['client_secret'])) {
                Log::error('No client_secret in response', ['response' => $data]);
                throw new \Exception('No client_secret returned from Paymob');
            }

            Log::info('Intention created successfully', [
                'intention_id' => $data['id'] ?? null,
                'client_secret' => substr($data['client_secret'], 0, 20) . '...'
            ]);

            return $data;

        } catch (\Exception $e) {
            Log::error('Paymob Intention error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get transaction details
     */
    public function getTransaction($transactionId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->secretKey,
            ])->get($this->baseUrl . "/intention/{$transactionId}");

            if ($response->failed()) {
                throw new \Exception('Failed to get transaction details');
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Failed to get transaction', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get public key for frontend
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }
}