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
     * Pay with card (using card details directly)
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

    /**
     * Create payment intention (for UAE endpoint)
     */
    public function createIntention($amount, $currency, $billingData, $items = [])
    {
        $secretKey = config('services.paymob.secret_key'); // sk_test_xxx
        $amountCents = (int) round($amount * 100); // convert to minor units

        $items = $items ?: [[
            'name'        => 'Order Payment',
            'amount'      => $amountCents,
            'description' => 'Checkout payment',
            'quantity'    => 1,
        ]];

        foreach ($items as &$item) {
            $item['amount'] = (int) round($item['amount'] * 100);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Token ' . $secretKey,
            'Content-Type'  => 'application/json',
        ])->post('https://uae.paymob.com/v1/intention/', [
            'amount'          => $amountCents,
            'currency'        => $currency,
            'payment_methods' => [
                (int) env('PAYMOB_CARD_INTEGRATION_ID'),
                (int) env('PAYMOB_TAMARA_INTEGRATION_ID'),
            ],
            'items'           => $items,
            'billing_data'    => $billingData,
            'customer' => [
                'first_name'   => $billingData['first_name'],
                'last_name'    => $billingData['last_name'],
                'email'        => $billingData['email'] ?? null,
                'phone_number' => $billingData['phone_number'] ?? null,
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception("Paymob Intention failed: " . $response->body());
        }

        return $response->json();
    }


       public function payWithGooglePay($googlePayToken)
    {
        $secretKey = config('services.paymob.secret_key');

        $response = \Http::withHeaders([
            'Authorization' => 'Token ' . $secretKey,
            'Content-Type'  => 'application/json',
        ])->post('https://uae.paymob.com/v1/intention/google_pay/', [
            'token' => $googlePayToken,
            'integration_id' => env('PAYMOB_GOOGLEPAY_INTEGRATION_ID'),
        ]);

        if ($response->failed()) {
            throw new \Exception("Paymob Google Pay failed: " . $response->body());
        }

        return $response->json();
    }


}
