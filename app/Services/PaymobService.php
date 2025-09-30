<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
        ]);

        return $response['token'];
    }

    /**
     * Create order
     */
    public function createOrder($authToken, $amountCents, $merchantOrderId)
    {
        $response = Http::post('https://accept.paymob.com/api/ecommerce/orders', [
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
        $response = Http::post('https://accept.paymob.com/api/acceptance/payment_keys', [
            'auth_token' => $authToken,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $orderId,
            'billing_data' => $billingData,
            'currency' => 'EGP',
            'integration_id' => $this->integrationId,
        ]);

        return $response['token'];
    }

    /**
     * Pay with card (using card details directly)
     */
    public function payWithCard($paymentToken, $cardData)
    {
        $response = Http::post('https://accept.paymob.com/api/acceptance/payments/pay', [
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
}
