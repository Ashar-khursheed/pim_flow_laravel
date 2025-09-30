<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaymobService
{
    protected $baseUrl;
    protected $apiKey;
    protected $integrationId;

    public function __construct()
    {
        $this->baseUrl = config('services.paymob.base_url');
        $this->apiKey = config('services.paymob.api_key');
        $this->integrationId = config('services.paymob.integration_id');
    }

    /**
     * Authenticate and get auth token
     */
    public function authenticate()
    {
        $response = Http::post($this->baseUrl . '/auth/tokens', [
            'api_key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            throw new \Exception("Paymob authentication failed: " . $response->body());
        }

        return $response['token'];
    }

    /**
     * Create order
     */
    public function createOrder($authToken, $amountCents, $merchantOrderId)
    {
        $response = Http::post($this->baseUrl . '/ecommerce/orders', [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency' => 'EGP',
            'merchant_order_id' => $merchantOrderId,
            'items' => [],
        ]);

        return $response->json();
    }

    /**
     * Get payment key
     */
    public function getPaymentKey($authToken, $orderId, $amountCents, $billingData)
    {
        $response = Http::post($this->baseUrl . '/acceptance/payment_keys', [
            'auth_token' => $authToken,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $orderId,
            'billing_data' => $billingData,
            'currency' => 'EGP',
            'integration_id' => $this->integrationId,
        ]);

        if ($response->failed()) {
            throw new \Exception("Paymob payment key failed: " . $response->body());
        }

        return $response['token'];
    }

    /**
     * Pay with card (direct card details)
     */
    public function payWithCard($paymentToken, $cardData)
    {
        $response = Http::post($this->baseUrl . '/acceptance/payments/pay', [
            'source' => [
                'identifier' => $cardData['card_number'],
                'subtype' => 'CARD',
                'expiry_month' => $cardData['expiry_month'],
                'expiry_year' => $cardData['expiry_year'],
                'cvn' => $cardData['cvv'],
            ],
            'payment_token' => $paymentToken,
        ]);

        return $response->json();
    }

    public function createIntention($authToken, $orderId, $amountCents, $billingData)
    {
        $response = Http::withToken($authToken)->post($this->baseUrl . '/acceptance/payment_intents', [
            'amount_cents' => $amountCents,
            'currency' => 'AED',
            'order_id' => $orderId,
            'billing_data' => $billingData,
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
        ]);

        return $response->json();
    }
}
