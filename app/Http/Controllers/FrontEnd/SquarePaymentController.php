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
use Square\Models\CreatePaymentLinkRequest;
use Square\Models\Order;
use Square\Models\OrderLineItem;


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
        ]);

        try {
            // Follow the official Square documentation approach exactly
            $response = $this->client->payments->create(
                new CreatePaymentRequest([
                    'idempotencyKey' => (string) Str::uuid(),
                    'amountMoney' => new Money([
                        'amount' => (int)($request->amount * 100),
                        'currency' => Currency::Usd->value,
                    ]),
                    'sourceId' => $request->source_id,
                ])
            );

            // Check if the response has errors (errors property exists and is not empty)
            if (empty($response->getErrors())) {
                // Success - payment was created
                $payment = $response->getPayment();
                
                return response()->json([
                    'success' => true,
                    'payment' => [
                        'id' => $payment->getId(),
                        'status' => $payment->getStatus(),
                        'amount' => $payment->getAmountMoney()->getAmount() / 100, // Convert back to dollars
                        'currency' => $payment->getAmountMoney()->getCurrency(),
                        'created_at' => $payment->getCreatedAt(),
                        'receipt_url' => $payment->getReceiptUrl(),
                    ]
                ]);
            }

            // Has errors - payment failed
            $errors = [];
            foreach ($response->getErrors() as $error) {
                $errors[] = $error->getCode() . ': ' . $error->getDetail();
            }

            return response()->json([
                'success' => false,
                'errors' => $errors,
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['Error: ' . $e->getMessage()],
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
            if (!$locationId) {
                throw new \Exception('Square location ID not configured in environment');
            }

            \Log::info('Creating payment link for order: ' . $order->id);

            // Calculate total amount for the order
            $totalAmount = (int) round($order->total_amount * 100); // Convert to cents

            // Option 1: Use Quick Pay (simpler approach - single item)
            if ($order->orderProducts->count() == 1) {
                $product = $order->orderProducts->first();
                
                $quickPay = new QuickPay(
                    $product->product->name,
                    new Money([
                        'amount' => $totalAmount,
                        'currency' => 'USD'
                    ]),
                    $locationId
                );

                $request = new CreatePaymentLinkRequest();
                $request->setIdempotencyKey((string) Str::uuid());
                $request->setQuickPay($quickPay);
                
                // Optional: Set checkout options
                $request->setCheckoutOptions([
                    'redirect_url' => url('/payment-success?order_id=' . $order->id)
                ]);

            } else {
                // Option 2: Use Order-based checkout (for multiple items)
                // This requires creating a Square Order object first
                throw new \Exception('Multiple items payment links not implemented yet. Use quick pay for single items.');
            }

            \Log::info('Sending request to Square Checkout API');
            $response = $this->client->getCheckoutApi()->createPaymentLink($request);

            if ($response->isSuccess()) {
                $paymentLink = $response->getResult()->getPaymentLink();
                $url = $paymentLink->getUrl();
                \Log::info('Payment link created successfully: ' . $url);
                return $url;
            }

            $errors = $response->getErrors();
            $errorMessage = '';
            foreach ($errors as $error) {
                $errorMessage .= $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail() . '; ';
            }
            
            \Log::error('Square API error: ' . $errorMessage);
            throw new \Exception($errorMessage);

        } catch (\Exception $e) {
            \Log::error('Square payment link error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Alternative method using cURL for payment links (if SDK doesn't work)
     */
    public function createPaymentLinkCurl(\App\Models\FrontEnd\Order $order)
    {
        try {
            $accessToken = env('SQUARE_ACCESS_TOKEN');
            $locationId = env('SQUARE_LOCATION_ID');
            
            if (!$accessToken || !$locationId) {
                throw new \Exception('Square credentials not configured');
            }

            $totalAmount = (int) round($order->total_amount * 100);
            $product = $order->orderProducts->first();

            $data = [
                'idempotency_key' => (string) Str::uuid(),
                'quick_pay' => [
                    'name' => $product->product->name ?? 'Order #' . $order->order_number,
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
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                return $result['payment_link']['url'] ?? null;
            } else {
                \Log::error('Square cURL error: ' . $response);
                return null;
            }

        } catch (\Exception $e) {
            \Log::error('Square payment link cURL error: ' . $e->getMessage());
            return null;
        }
    }
}
    // public function createPaymentLink(Order $order)
    // {
    //     try {
    //         $lineItems = [];

    //         foreach ($order->orderProducts as $item) {
    //             $lineItems[] = new OrderLineItem($item->quantity, [
    //                 'name' => $item->product->name,
    //                 'basePriceMoney' => new Money([
    //                     'amount' => (int) round($item->unit_price * 100),
    //                     'currency' => $item->product->currency->code ?? 'USD'
    //                 ])
    //             ]);
    //         }

    //         $orderObj = new Order(env('SQUARE_LOCATION_ID'));
    //         $orderObj->setLineItems($lineItems);

    //         $request = new CreatePaymentLinkRequest();
    //         $request->setOrder($orderObj);
    //         $request->setCheckoutOptions([
    //             'redirect_url' => url('/payment-success?order_id=' . $order->id)
    //         ]);

    //         $response = $this->client->getPaymentLinksApi()->createPaymentLink($request);

    //         if ($response->isSuccess()) {
    //             return $response->getResult()->getPaymentLink()->getUrl();
    //         }

    //         $errors = $response->getErrors();
    //         throw new \Exception($errors[0]->getDetail() ?? 'Failed to create payment link');

    //     } catch (\Exception $e) {
    //         \Log::error('Square payment link error: ' . $e->getMessage());
    //         return null;
    //     }
    // }
