<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;
use Square\Exceptions\ApiException;
use Illuminate\Support\Str;

class SquarePaymentController extends Controller
{
    protected SquareClient $client;

    public function __construct()
    {
        $this->client = new SquareClient([
            'accessToken' => env('SQUARE_ACCESS_TOKEN'),
            'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
            'userAgentDetail' => 'laravel_square_payment_api', // optional
        ]);
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'idempotencyKey' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0.5',
            'currency' => 'nullable|string|size:3',
            'location_id' => 'nullable|string'
        ]);

        $token = $validated['token'];
        $idempotencyKey = $validated['idempotencyKey'] ?? (string) Str::uuid();
        $amount = $validated['amount'] ?? 100; // default $1.00 (in cents)
        $currency = $validated['currency'] ?? 'USD';
        $locationId = $validated['location_id'] ?? $this->getDefaultLocationId();

        $money = new Money([
            'amount' => (int) $amount,
            'currency' => strtoupper($currency),
        ]);

        try {
            $paymentRequest = new CreatePaymentRequest(
                $token,
                $idempotencyKey,
                $money
            );

            $paymentRequest->setLocationId($locationId);

            $response = $this->client->getPaymentsApi()->createPayment($paymentRequest);

            if ($response->isSuccess()) {
                return response()->json($response->getResult(), 200);
            }

            return response()->json([
                'errors' => $response->getErrors()
            ], 400);

        } catch (ApiException $e) {
            return response()->json([
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    private function getDefaultLocationId()
    {
        $locationResponse = $this->client->getLocationsApi()->listLocations();

        if ($locationResponse->isSuccess()) {
            $locations = $locationResponse->getResult()->getLocations();
            return $locations[0]->getId(); // Use first location
        }

        return null; // fallback or handle properly in production
    }
}
