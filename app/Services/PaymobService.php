<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaymobService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('paymob.base_url', 'https://accept.paymob.com/api');
        $this->apiKey = config('paymob.api_key');
    }

    public function authenticate()
    {
        $response = Http::post("{$this->baseUrl}/auth/tokens", [
            "api_key" => $this->apiKey
        ]);

        return $response->json('token');
    }

    public function createOrder($authToken, $amountCents, $merchantOrderId)
    {
        $response = Http::post("{$this->baseUrl}/ecommerce/orders", [
            "auth_token" => $authToken,
            "delivery_needed" => "false",
            "amount_cents" => $amountCents,
            "currency" => "EGP",
            "merchant_order_id" => $merchantOrderId,
            "items" => []
        ]);

        return $response->json();
    }

    public function getPaymentKey($authToken, $orderId, $amountCents, $billingData)
    {
        $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", [
            "auth_token" => $authToken,
            "amount_cents" => $amountCents,
            "expiration" => 3600,
            "order_id" => $orderId,
            "billing_data" => $billingData,
            "currency" => "EGP",
            "integration_id" => config('paymob.integration_id'),
            "lock_order_when_paid" => "false"
        ]);

        return $response->json('token');
    }

    public function payWithCard($paymentToken, $cardData)
    {
        $response = Http::post("{$this->baseUrl}/acceptance/payments/pay", [
            "source" => [
                "identifier" => $cardData['card_number'],  // e.g. 5123456789012346
                "subtype" => "CARD",
                "expiry_month" => $cardData['expiry_month'], // "05"
                "expiry_year" => $cardData['expiry_year'],   // "25"
                "cvn" => $cardData['cvv']                    // "123"
            ],
            "payment_token" => $paymentToken
        ]);

        return $response->json();
    }
}
