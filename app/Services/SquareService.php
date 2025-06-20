<?php

namespace App\Services;

use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;
use Square\Exceptions\ApiException;

class SquareService
{
    protected $client;

    public function __construct()
    {
        // Simple constructor - just pass the access token and environment as string
        $environment = env('SQUARE_ENV', 'sandbox'); // 'sandbox' or 'production'
        $this->client = new SquareClient(env('SQUARE_ACCESS_TOKEN'), $environment);
    }

    public function processPayment($nonce, $amount, $currency = 'USD')
    {
        $paymentsApi = $this->client->getPaymentsApi();

        // Create a Money object to represent the amount
        $money = new Money();
        $money->setAmount((int)($amount * 100)); // Convert dollars to cents
        $money->setCurrency($currency); // Set the appropriate currency

        // Create a unique idempotency key
        $idempotencyKey = uniqid('payment_');

        // Create a payment request
        $createPaymentRequest = new CreatePaymentRequest($nonce, $idempotencyKey);
        $createPaymentRequest->setAmountMoney($money); // Set the amount_money field

        try {
            // Execute the payment request
            $response = $paymentsApi->createPayment($createPaymentRequest);

            if ($response->isSuccess()) {
                return [
                    'success' => true,
                    'data' => $response->getResult(),
                ];
            } else {
                return [
                    'success' => false,
                    'errors' => $response->getErrors(),
                ];
            }
        } catch (ApiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ];
        }
    }

    public function getClient()
    {
        return $this->client;
    }
}