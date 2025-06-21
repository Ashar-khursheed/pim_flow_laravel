<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Environment;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;
use Illuminate\Support\Str;

class SquarePaymentController extends Controller
{
    protected $client;

    public function __construct()
    {
        $this->client = new SquareClient([
            'accessToken' => env('SQUARE_ACCESS_TOKEN'),
            'environment' => env('SQUARE_ENVIRONMENT') === 'production' ? Environment::PRODUCTION : Environment::SANDBOX,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/square/pay",
     *     summary="Process a payment using Square",
     *     description="Accepts a nonce from the frontend and creates a payment using Square's API.",
     *     tags={"Frontend-Square Payment"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nonce", "amount"},
     *             @OA\Property(property="nonce", type="string", example="cnon:card-nonce-ok", description="Card nonce generated from Square Web Payments SDK"),
     *             @OA\Property(property="amount", type="number", format="float", example=10.00, description="Payment amount in dollars")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="payment", type="object", description="Payment details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or Square API error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Internal server error message")
     *         )
     *     )
     * )
     */
    public function pay(Request $request)
    {
        $request->validate([
            'nonce' => 'required|string',
            'amount' => 'required|numeric|min:0.5',
        ]);

        $paymentsApi = $this->client->getPaymentsApi();

        $money = new Money();
        $money->setAmount((int) ($request->amount * 100)); // amount in cents
        $money->setCurrency('USD');

        $paymentRequest = new CreatePaymentRequest(
            $request->nonce,
            (string) Str::uuid(),
            $money
        );

        try {
            $response = $paymentsApi->createPayment($paymentRequest);

            if ($response->isSuccess()) {
                return response()->json([
                    'status' => 'success',
                    'payment' => $response->getResult()->getPayment(),
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'errors' => $response->getErrors(),
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}