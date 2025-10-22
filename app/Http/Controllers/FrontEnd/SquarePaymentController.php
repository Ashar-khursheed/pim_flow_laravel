<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Environments;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\Money;
use Square\Types\Currency;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use Square\Environment;

// CORRECT imports for Payment Links
use Square\Models\CreatePaymentLinkRequest;

class SquarePaymentController extends Controller
{
    protected SquareClient $client;

    public function __construct()
    {
        $this->client = new SquareClient(
            token: env('SQUARE_ACCESS_TOKEN'),
            options: [
                'baseUrl' => Environments::Production->value,
            ]
        );
        
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/payment-square",
     *     summary="Create a payment using Square",
     *     description="Receives a source_id (card token) and amount to process payment via Square API.",
     *     tags={"Square Payment"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"source_id", "amount"},
     *             @OA\Property(
     *                 property="source_id",
     *                 type="string",
     *                 example="cnon:card-nonce-ok",
     *                 description="Card token generated from Square Web Payments SDK"
     *             ),
     *             @OA\Property(
     *                 property="amount",
     *                 type="number",
     *                 format="float",
     *                 example=10.00,
     *                 description="Amount in USD"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="payment",
     *                 type="object",
     *                 description="Square payment object returned after success"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Payment failed or validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="errors",
     *                 type="array",
     *                 @OA\Items(type="string", example="Card declined")
     *             )
     *         )
     *     )
     * )
     */
    // public function createPayment(Request $request)
    // {
    //     // Validate access token
    //     $token = env('SQUARE_ACCESS_TOKEN');
    //     if (!$token) {
    //         return response()->json([
    //             'success' => false,
    //             'errors' => ['Square access token not found in environment'],
    //         ], 500);
    //     }

    //     // Validate request input
    //     $request->validate([
    //         'source_id' => 'required|string',
    //         'amount' => 'required|numeric|min:0.01',
    //     ]);

    //     try {
    //         // Follow the official Square documentation approach exactly
    //         $response = $this->client->payments->create(
    //             new CreatePaymentRequest([
    //                 'idempotencyKey' => (string) Str::uuid(),
    //                 'amountMoney' => new Money([
    //                     'amount' => (int)($request->amount * 100),
    //                     'currency' => Currency::Usd->value,
    //                 ]),
    //                 'sourceId' => $request->source_id,
    //             ])
    //         );

    //         // Check if the response has errors (errors property exists and is not empty)
    //         if (empty($response->getErrors())) {
    //             // Success - payment was created
    //             $payment = $response->getPayment();
                
    //             return response()->json([
    //                 'success' => true,
    //                 'payment' => [
    //                     'id' => $payment->getId(),
    //                     'status' => $payment->getStatus(),
    //                     'amount' => $payment->getAmountMoney()->getAmount() / 100, // Convert back to dollars
    //                     'currency' => $payment->getAmountMoney()->getCurrency(),
    //                     'created_at' => $payment->getCreatedAt(),
    //                     'receipt_url' => $payment->getReceiptUrl(),
    //                 ]
    //             ]);
    //         }

    //         // Has errors - payment failed
    //         $errors = [];
    //         foreach ($response->getErrors() as $error) {
    //             $errors[] = $error->getCode() . ': ' . $error->getDetail();
    //         }

    //         return response()->json([
    //             'success' => false,
    //             'errors' => $errors,
    //         ], 400);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'errors' => ['Error: ' . $e->getMessage()],
    //             'debug_info' => [
    //                 'exception_class' => get_class($e),
    //                 'file' => $e->getFile() . ':' . $e->getLine(),
    //             ]
    //         ], 500);
    //     }
    // }
    public function createPayment(Request $request)
    {
        // Validate access token
        $token = env('SQUARE_ACCESS_TOKEN');
        if (!$token) {
            return response()->json([
                'success' => false,
                'errors' => ['Square access token not found in environment'],
            ], 500);
        }

        // Validate request input
        $request->validate([
            'source_id' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
           'idempotency_key' => 'nullable|string', // Make this required
        ]);

        try {
            // Use the idempotency key from the frontend - THIS IS CRITICAL
            $idempotencyKey = $request->idempotency_key ?? (string) Str::uuid();
            
            // Optional: Log for debugging (remove in production)
            \Log::info("Processing payment with idempotency key: " . $idempotencyKey);

            $response = $this->client->payments->create(
                new CreatePaymentRequest([
                    'idempotencyKey' => $idempotencyKey, // Use frontend key
                    'amountMoney' => new Money([
                        'amount' => (int)($request->amount * 100),
                        'currency' => Currency::Usd->value,
                    ]),
                    'sourceId' => $request->source_id,
                ])
            );

            // Check if the response has errors
            if (empty($response->getErrors())) {
                // Success - payment was created
                $payment = $response->getPayment();
                
                \Log::info("Payment successful: " . $payment->getId());
                
                return response()->json([
                    'success' => true,
                    'payment' => [
                        'id' => $payment->getId(),
                        'status' => $payment->getStatus(),
                        'amount' => $payment->getAmountMoney()->getAmount() / 100,
                        'currency' => $payment->getAmountMoney()->getCurrency(),
                        'created_at' => $payment->getCreatedAt(),
                        'receipt_url' => $payment->getReceiptUrl(),
                    ]
                ]);
            }

            // Has errors - payment failed
            $errors = [];
            foreach ($response->getErrors() as $error) {
                $errorMessage = $error->getCode() . ': ' . $error->getDetail();
                $errors[] = $errorMessage;
                \Log::error("Square payment error: " . $errorMessage);
            }

            return response()->json([
                'success' => false,
                'errors' => $errors,
            ], 400);

        } catch (\Square\Exceptions\ApiException $e) {
            // Handle Square-specific API exceptions
            \Log::error("Square API Exception: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'errors' => ['Square API Error: ' . $e->getMessage()],
            ], 500);
            
        } catch (\Exception $e) {
            \Log::error("General payment exception: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'errors' => ['Payment processing error: ' . $e->getMessage()],
                'debug_info' => [
                    'exception_class' => get_class($e),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]
            ], 500);
        }
    }
 /**
     * Create a payment link for an order using Square Checkout API
     */
    public function createPaymentLink(\App\Models\FrontEnd\Order $order)
    {
        try {
            // Check if Square is properly configured
            $locationId = env('SQUARE_LOCATION_ID');
            $accessToken = env('SQUARE_ACCESS_TOKEN');
            
            if (!$locationId || !$accessToken) {
                throw new \Exception('Square credentials not configured: Location ID or Access Token missing');
            }

            \Log::info('Creating payment link for order: ' . $order->id . ' with location: ' . $locationId);

            // Use cURL method as it's more reliable than SDK for payment links
            return $this->createPaymentLinkCurl($order);

        } catch (\Exception $e) {
            \Log::error('Square payment link error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Alternative method using cURL for payment links (if SDK doesn't work)
     */
    public function createPaymentLinkCurl($order)
    {
        try {
            $accessToken = env('SQUARE_ACCESS_TOKEN');
            $locationId = env('SQUARE_LOCATION_ID');
            
            if (!$accessToken || !$locationId) {
                throw new \Exception('Square credentials not configured');
            }

            $totalAmount = (int) round($order->pending_amount * 100);
            
            // Handle both real orders and test objects
            if (is_object($order) && isset($order->orderProducts)) {
                $product = $order->orderProducts->first();
                $itemName = $order->orderProducts->count() > 1 
                    ? "Order #" . $order->order_number . " (" . $order->orderProducts->count() . " items)"
                    : ($product->product->name ?? "Order #" . $order->order_number);
            } else {
                $itemName = "Order #" . $order->order_number;
            }

            $data = [
                'idempotency_key' => (string) Str::uuid(),
                'quick_pay' => [
                    'name' => $itemName,
                    'price_money' => [
                        'amount' => $totalAmount,
                        'currency' => 'USD'
                    ],
                    'location_id' => $locationId
                ],
                'checkout_options' => [
                    'redirect_url' => url('/payment-success?order_id=' . $order->id)
                ]
            ];

            \Log::info('Square payment link request data: ' . json_encode($data));

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://connect.squareup.com/v2/online-checkout/payment-links',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                    'Square-Version: 2025-07-16'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            \Log::info('Square API response code: ' . $httpCode);
            \Log::info('Square API response: ' . $response);

            if ($curlError) {
                throw new \Exception('cURL error: ' . $curlError);
            }

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if (isset($result['payment_link']['url'])) {
                    \Log::info('Payment link created successfully: ' . $result['payment_link']['url']);
                    return $result['payment_link']['url'];
                } else {
                    \Log::error('No payment link URL in response: ' . json_encode($result));
                    return null;
                }
            } else {
                \Log::error('Square API error (HTTP ' . $httpCode . '): ' . $response);
                return null;
            }

        } catch (\Exception $e) {
            \Log::error('Square payment link cURL error: ' . $e->getMessage());
            return null;
        }
    }
}