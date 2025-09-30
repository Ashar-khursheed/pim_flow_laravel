<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymobService
{
    protected $apiKey;
    protected $integrationId;

    public function __construct()
    {
        $this->apiKey = env('PAYMOB_API_KEY');
        $this->integrationId = env('PAYMOB_INTEGRATION_ID');
    }

    /**
     * Authenticate and get auth token
     */
    public function authenticate()
    {
        $response = Http::post('https://accept.paymob.com/api/auth/tokens', [
            'api_key' => $this->apiKey,
        ])->json();

        Log::info('Paymob Auth Response:', $response);

        if (!isset($response['token'])) {
            throw new \Exception("Paymob authentication failed: " . json_encode($response));
        }

        return $response['token'];
    }

    /**
     * Create order
     */
    public function createOrder($authToken, $amountCents, $merchantOrderId)
    {
        $response = Http::post('https://accept.paymob.com/api/ecommerce/orders', [
            'auth_token'   => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency'     => 'EGP',
            'merchant_order_id' => $merchantOrderId,
            'items'        => [],
        ])->json();

        Log::info('Paymob Order Response:', $response);

        if (!isset($response['id'])) {
            throw new \Exception("Paymob order creation failed: " . json_encode($response));
        }

        return $response;
    }

    /**
     * Get payment key
     */
    public function getPaymentKey($authToken, $orderId, $amountCents, $billingData)
    {
        $response = Http::post('https://accept.paymob.com/api/acceptance/payment_keys', [
            'auth_token'    => $authToken,
            'amount_cents'  => $amountCents,
            'expiration'    => 3600,
            'order_id'      => $orderId,
            'billing_data'  => $billingData,
            'currency'      => 'EGP',
            'integration_id'=> $this->integrationId,
        ])->json();

        Log::info('Paymob Payment Key Response:', $response);

        if (!isset($response['token'])) {
            throw new \Exception("Paymob payment key request failed: " . json_encode($response));
        }

        return $response['token'];
    }

    /**
     * Pay with card (using card details directly)
     */
    public function payWithCard($paymentToken, $cardData)
    {
        $response = Http::post('https://accept.paymob.com/api/acceptance/payments/pay', [
            'source' => [
                'identifier'    => $cardData['card_number'],
                'subtype'       => 'CARD',
                'expiry_month'  => $cardData['expiry_month'],
                'expiry_year'   => $cardData['expiry_year'],
                'cvn'           => $cardData['cvv'],
            ],
            'payment_token' => $paymentToken,
        ])->json();

        Log::info('Paymob Card Payment Response:', $response);

        return $response;
    }
    public function createIntention($authToken, $orderId, $amountCents, $billingData)
    {
        $response = Http::withToken($authToken)->post($this->baseUrl . '/acceptance/payment_intents', [
            'amount_cents' => $amountCents,
            'currency' => 'EGP',
            'order_id' => $orderId,
            'billing_data' => $billingData,
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
        ]);

        return $response->json();
    }

}
