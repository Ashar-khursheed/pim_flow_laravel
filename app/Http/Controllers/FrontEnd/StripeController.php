<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\PaymentManagement;
use App\Models\FrontEnd\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
class StripeController extends Controller
{

    /**
     * @OA\Post(
     *     path="/api/stripe/create-payment-intent",
     *     summary="Create Stripe Payment Intent",
     *     description="Creates a Stripe PaymentIntent for a one-time payment.",
     *     operationId="createPaymentIntent",
     *     tags={"Stripe"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "payment_method_id"},
     *             @OA\Property(property="amount", type="number", format="float", example=50.0, description="Payment amount in dollars"),
     *             @OA\Property(property="payment_method_id", type="string", example="pm_1234567890", description="Stripe Payment Method ID"),
     *             @OA\Property(property="currency", type="string", example="aed", description="Currency code"),
     *             @OA\Property(property="customer_info", type="object", description="Customer information")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment intent processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="client_secret", type="string", example="pi_3Nx4x4xxxx_secret_1234"),
     *             @OA\Property(property="requires_action", type="boolean", example=false),
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_3Nx4x4xxxx")
     *         )
     *     )
     * )
     */
    // public function createPaymentIntent(Request $request)
    // {
    //     // Validate input
    //     $request->validate([
    //         'amount' => 'required|numeric|min:1',
    //         'payment_method_id' => 'required|string',
    //         'currency' => 'sometimes|string|in:aed,usd',
    //         'customer_info' => 'sometimes|array'
    //     ]);

    //     // Set Stripe Secret Key
    //     Stripe::setApiKey(config('services.stripe.secret'));

    //     // try {
    //     //     // Create PaymentIntent
    //     //     $paymentIntent = PaymentIntent::create([
    //     //         'amount' => (int) round($request->amount * 100), // Convert to smallest unit and round
    //     //         'currency' => $request->currency ?? 'aed',
    //     //         'payment_method' => $request->payment_method_id,
    //     //         'confirmation_method' => 'manual',
    //     //         'confirm' => true,
    //     //         'return_url' => config('app.url') . '/payment/return',
    //     //         'description' => 'Order Payment - ' . now()->format('Y-m-d H:i:s'),
    //     //         'metadata' => [
    //     //             'customer_name' => $request->customer_info['name'] ?? '',
    //     //             'customer_email' => $request->customer_info['email'] ?? '',
    //     //             'order_timestamp' => now()->toISOString()
    //     //         ]
    //     //     ]);
    //         try {

    //     // STEP 1: Update Payment Method with Customer Billing Details
    //         // STEP 1: Create a Customer in Stripe
    //     $customer = \Stripe\Customer::create([
    //         'name' => $request->customer_info['name'] ?? null,
    //         'email' => $request->customer_info['email'] ?? null,
    //     ]);

    //     // STEP 2: Attach Payment Method to Customer
    //     \Stripe\PaymentMethod::retrieve($request->payment_method_id)
    //         ->attach(['customer' => $customer->id]);

    //     // STEP 3: Update PaymentMethod with billing details (now allowed)
    //     \Stripe\PaymentMethod::update(
    //         $request->payment_method_id,
    //         [
    //             'billing_details' => [
    //                 'name' => $request->customer_info['name'] ?? null,
    //                 'email' => $request->customer_info['email'] ?? null,
    //             ]
    //         ]
    //     );

    //     // STEP 4: Create PaymentIntent
    //     $paymentIntent = PaymentIntent::create([
    //         'amount' => (int) round($request->amount * 100),
    //         'currency' => $request->currency ?? 'aed',
    //         'payment_method' => $request->payment_method_id,
    //         'customer' => $customer->id,   // <-- THIS ensures Stripe shows name/email
    //         'confirmation_method' => 'manual',
    //         'confirm' => true,
    //         'return_url' => config('app.url') . '/payment/return',
    //         'description' => 'Order Payment - ' . now()->format('Y-m-d H:i:s'),
    //     ]);


    //         // Handle the payment intent status
    //         if ($paymentIntent->status === 'requires_action') {
    //             return response()->json([
    //                 'success' => true,
    //                 'requires_action' => true,
    //                 'client_secret' => $paymentIntent->client_secret,
    //                 'payment_intent_id' => $paymentIntent->id,
    //                 'payment_mode' => 'Credit Card',
    //                 'status' => 'requires_action',
    //                 'payment_method' => 'Stripe'
    //             ]);
    //         } else if ($paymentIntent->status === 'succeeded') {
    //             return response()->json([
    //                 'success' => true,
    //                 'requires_action' => false,
    //                 'payment_intent_id' => $paymentIntent->id,
    //                 'payment_mode' => 'Credit Card',
    //                 'status' => 'completed',
    //                 'payment_method' => 'Stripe',
    //                 'client_secret' => $paymentIntent->client_secret
    //             ]);
    //         } else {
    //             return response()->json([
    //                 'success' => false,
    //                 'error' => 'Payment failed with status: ' . $paymentIntent->status,
    //                 'payment_intent_status' => $paymentIntent->status
    //             ], 400);
    //         }

    //     } catch (\Stripe\Exception\CardException $e) {
    //         // Card was declined
    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getError()->message,
    //             'decline_code' => $e->getError()->decline_code ?? null
    //         ], 400);
    //     } catch (\Stripe\Exception\RateLimitException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => 'Too many requests made to the API too quickly'
    //         ], 429);
    //     } catch (\Stripe\Exception\InvalidRequestException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => 'Invalid parameters: ' . $e->getError()->message
    //         ], 400);
    //     } catch (\Stripe\Exception\AuthenticationException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => 'Authentication with Stripe\'s API failed'
    //         ], 401);
    //     } catch (\Stripe\Exception\ApiConnectionException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => 'Network communication with Stripe failed'
    //         ], 500);
    //     } catch (\Stripe\Exception\ApiErrorException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => 'Stripe API error: ' . $e->getError()->message
    //         ], 500);
    //     } catch (\Exception $e) {
    //         \Log::error('Stripe Payment Error: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString(),
    //             'request_data' => $request->all()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'error' => 'An unexpected error occurred. Please try again.'
    //         ], 500);
    //     }
    // }
public function createPaymentIntent(Request $request)
{
    $request->validate([
        'payment_method_id' => 'required|string',   /* ✅ Add kiya */
        'amount'            => 'required|numeric|min:1',
        'currency'          => 'sometimes|in:aed,usd',
        'customer_info'     => 'sometimes|array',
    ]);
 
    Stripe::setApiKey(config('services.stripe.secret'));
 
    try {
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount'         => (int) round($request->amount * 100),
            'currency'       => $request->currency ?? 'aed',
            'payment_method' => $request->payment_method_id, /* ✅ Attach kiya */
            'description'    => 'Order Payment',
            'receipt_email'  => $request->customer_info['email'] ?? null,
 
            /* ✅ Confirm:true — ek hi call mein attach + confirm */
            'confirm'        => true,
 
            /* ✅ 3D Secure ke liye return_url zaroori hai */
            'return_url'     => config('app.frontend_url') . '/review-checkout',
 
            /* ✅ automatic_payment_methods ke saath allow_redirects=never
             * warna Stripe redirect methods bhi enable karta hai (wallets etc)
             */
            'automatic_payment_methods' => [
                'enabled'          => true,
                'allow_redirects'  => 'never', /* ✅ Sirf card allow karo */
            ],
 
            /* ✅ 3D Secure — any rakhna sahi hai fraud protection ke liye */
            'payment_method_options' => [
                'card' => [
                    'request_three_d_secure' => 'any',
                ],
            ],
        ]);
 
        /* ─── Status ke hisaab se response ─── */
 
        /* ✅ Payment succeeded — seedha */
        if ($paymentIntent->status === 'succeeded') {
            return response()->json([
                'success'               => true,
                'requires_action'       => false,
                'payment_intent_status' => 'succeeded',
                'payment_intent_id'     => $paymentIntent->id,
                'client_secret'         => $paymentIntent->client_secret,
            ]);
        }
 
        /* ✅ 3D Secure required */
        if (
            $paymentIntent->status === 'requires_action' ||
            $paymentIntent->status === 'requires_source_action'
        ) {
            return response()->json([
                'success'               => true,
                'requires_action'       => true,
                'payment_intent_status' => $paymentIntent->status,
                'payment_intent_id'     => $paymentIntent->id,
                'client_secret'         => $paymentIntent->client_secret,
            ]);
        }
 
        /* ❌ Koi aur unexpected status */
        return response()->json([
            'success'               => false,
            'payment_intent_status' => $paymentIntent->status,
            'error'                 => 'Payment could not be completed. Status: ' . $paymentIntent->status,
        ], 400);
 
    } catch (\Stripe\Exception\CardException $e) {
        /* ✅ Card decline — clear message */
        return response()->json([
            'success' => false,
            'error'   => $e->getError()->message ?? 'Your card was declined.',
            'code'    => $e->getError()->code ?? 'card_declined',
        ], 400);
 
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        return response()->json([
            'success' => false,
            'error'   => $e->getMessage(),
        ], 400);
 
    } catch (\Exception $e) {
        \Log::error('Stripe Payment Error: ' . $e->getMessage(), [
            'amount'            => $request->amount,
            'payment_method_id' => $request->payment_method_id,
        ]);
        return response()->json([
            'success' => false,
            'error'   => 'Payment processing failed. Please try again.',
        ], 500);
    }
}

    // public function createPaymentIntent(Request $request)
    // {
    //     $request->validate([
    //         'amount' => 'required|numeric|min:1',
    //         'currency' => 'sometimes|in:aed,usd',
    //     ]);

    //     Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         $paymentIntent = \Stripe\PaymentIntent::create([
    //             'amount' => (int) round($request->amount * 100),
    //             'currency' => $request->currency ?? 'aed',

    //             // 🔐 Strong fraud protection
    //             'automatic_payment_methods' => [
    //                 'enabled' => true,
    //             ],
    //             'payment_method_options' => [
    //                 'card' => [
    //                     'request_three_d_secure' => 'any',
    //                 ],
    //             ],

    //             // ❌ No receipt_email
    //             // ❌ No customer
    //             // ❌ No metadata from user

    //             'description' => 'Order Payment',
    //         ]);

    //         return response()->json([
    //             'success' => true,
    //             'client_secret' => $paymentIntent->client_secret,
    //             'payment_intent_id' => $paymentIntent->id,
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

  

    /**
     * Add this method to confirm payment intent after 3D Secure
     */
    public function confirmPaymentIntent(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string'
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

            return response()->json([
                'success' => $paymentIntent->status === 'succeeded',
                'status' => $paymentIntent->status,
                'payment_intent_id' => $paymentIntent->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/stripe/create-stripe-payment-link",
     *     summary="Create Stripe Payment link",
     *     description="Creates a Stripe PaymentIntent for a one-time payment.",
     *     operationId="createStripePaymentLink",
     *     tags={"Stripe"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "order_id"},
     *             @OA\Property(property="amount", type="number", format="float", example=150.0, description="Payment amount in dollars"),
     *             @OA\Property(property="order_id", type="string", example="pm_1234567890", description="Stripe Payment Method ID"),
     *             @OA\Property(property="currency", type="string", example="usd", description="Currency code"),
     *             @OA\Property(property="itemName", type="string", example="product name", description="product name")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment intent processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="client_secret", type="string", example="pi_3Nx4x4xxxx_secret_1234"),
     *             @OA\Property(property="requires_action", type="boolean", example=false),
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_3Nx4x4xxxx")
     *         )
     *     ),
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function createStripePaymentLink(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|numeric|min:0',
            'itemName' => 'required|string',
            'currency' => 'required|string',
            'order_id' => 'required',

        ]);
        $url = config('app.url');
        $totalAmount = ($request->amount) * 100;
        $itemName = $request->itemName;
        $currency = $request->currency;
        $order_id = $request->order_id;
        $payment = Order::where('id', $order_id)->first();
        if (!$payment) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid order_id, not found in records'
            ], 404);
        }

        $success_url = config('app.url').'/thanks' . '?session_id={CHECKOUT_SESSION_ID}';
        $cancel_url = config('app.url').'/failed';
        $stripeSecret = config('services.stripe.secret');

        $res = Http::withOptions(['verify' => false])
            ->withToken($stripeSecret)
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'payment_method_types[]' => 'card',
                'line_items[0][price_data][currency]' => $currency,
                'line_items[0][price_data][unit_amount]' => $totalAmount,
                'line_items[0][price_data][product_data][name]' => $itemName,
                'line_items[0][quantity]' => 1,
                'mode' => 'payment',
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'metadata[order_id]' => $order_id,
            ]);

        $body = $res->json();
        if (!isset($body['url'])) {
            return response()->json(['error' => $body], 500);
        }

        $data = [
            'idempotency_key' => (string) Str::uuid(),
            'quick_pay' => [
                'name' => $itemName,
                'price_money' => [
                    'amount' => $totalAmount,
                    'currency' => 'USD'
                ],
                'payment_url' => $body['url'],
            ],
            'checkout_options' => [
                'success_url' => config('app.url').'/thanks?session_id={CHECKOUT_SESSION_ID}' ,
                'failed_url' => config('app.url').'/failed?session_id={CHECKOUT_SESSION_ID}'
            ]
        ];
        // You now have a permanent payment link
        return response()->json([
            'payment_url' => $body['url'],
        ]);

    }

   public function generatePaymentLink($order)
    {
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

        $stripeSecret = config('services.stripe.secret');

        // ✅ Currency based on APP_WEBSITE
        $currency = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : 'USD';

        $success_url = config('app.url') . '/thanks?session_id={CHECKOUT_SESSION_ID}';
        $cancel_url  = config('app.url') . '/failed?session_id={CHECKOUT_SESSION_ID}';

        $res = Http::withOptions(['verify' => false])
            ->withToken($stripeSecret)
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'payment_method_types[]' => 'card',
                'line_items[0][price_data][currency]' => strtolower($currency),
                'line_items[0][price_data][unit_amount]' => $totalAmount,
                'line_items[0][price_data][product_data][name]' => $itemName,
                'line_items[0][quantity]' => 1,
                'mode' => 'payment',
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'metadata[order_id]' => $order->id,
            ]);

        $body = $res->json();

        return $body['url'] ?? null;
    }



    public function handleWebhook(Request $request)
    {

        \Log::error('Stripe Webhook Received', $request->all());

        $endpointSecret = config('services.stripe.webhook_secret');
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            // ✅ Save order payment in DB
            $orderId = $session->metadata->order_id ?? null;

            PaymentManagement::create([
                'order_id' => $orderId,
                'transaction_id' => $session->payment_intent,
                'payment_mode' => 'Credit Card',
                'payment_method' => 'Stripe',
                'amount' => $session->amount_total / 100,
                'status' => "Completed",
                'payment_date' => date('Y-m-d H:i:s'),
                'notes' => 'Payment marked through link',
                'payment_details' => ''
            ]);

        }
    }
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');
        if (!$sessionId) {
            return response()->json(['error' => 'Missing session_id'], 400);
        }
        $stripeSecret = config('services.stripe.secret');
        // Fetch session details from Stripe
        $res = Http::withOptions(['verify' => false])
            ->withToken($stripeSecret)
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        $data = $res->json();

        if (!empty($data)) {
            $amount = $data['amount_total'] / 100;
            $currency = $data['currency'];
            $transactionId = $data['payment_intent'];
            $order_id = $data['metadata']['order_id'];
            if ($data['status'] == 'complete') {
                $status = "Completed";
            } else {
                $status = "Failed";
            }


            $orderdetails = Order::where('id', $order_id)->where('is_paid', '0')->first();

            if (!empty($orderdetails)) {
                $total_amount = $orderdetails->total_amount;
                $paid_amount = $orderdetails->paid_amount + $amount;
                $pending_amount = $total_amount - $paid_amount;

                $order = Order::find($orderdetails->id);
                if ($paid_amount < $total_amount) {
                    $order->update([
                        'paid_amount' => $paid_amount,
                        'pending_amount' => $pending_amount,
                        'is_paid' => $pending_amount <= 0,
                        'is_reserved' => $pending_amount <= 0,
                    ]);
                } else if ($paid_amount == $total_amount) {

                    $order->update([
                        'paid_amount' => $paid_amount,
                        'pending_amount' => $pending_amount,
                        'is_paid' => 1,
                        'is_reserved' => 0,
                        'status' => 'Confirmed'
                    ]);
                }
                if ($status != 'Failed') {
                    $checkTransaction = PaymentManagement::where('transaction_id', $transactionId)->get()->count();
                    if (!$checkTransaction) {
                        PaymentManagement::create([
                            'order_id' => $orderdetails->id,
                            'transaction_id' => $transactionId,
                            'payment_mode' => 'Credit Card',
                            'payment_method' => 'Stripe',
                            'amount' => $amount,
                            'status' => $status,
                            'payment_date' => date('Y-m-d H:i:s'),
                            'notes' => 'Payment marked through link',
                            'payment_details' => ''
                        ]);
                    }
                }
            }
            // Example response
            return response()->json([
                'order_id' => $order_id ?? null,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
            ]);
        } else {
            return response()->json([
                'data' => $data ?? null,
                'status' => false,
            ]);
        }



    }

    /**
     * @OA\Get(
     *     path="/api/stripe/failed",
     *     summary="Stripe Payment Cancel Redirect",
     *     tags={"Stripe"},
     *     @OA\Response(
     *         response=200,
     *         description="Payment was cancelled"
     *     )
     * )
     */
    public function paymentFailed(Request $request)
    {
        $sessionId = $request->get('session_id');
        if (!$sessionId) {
            return response()->json(['error' => 'Missing session_id'], 400);
        }
        $stripeSecret = config('services.stripe.secret');
        // Fetch session details from Stripe
        $res = Http::withOptions(['verify' => false])
            ->withToken($stripeSecret)
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        $data = $res->json();

        if (!empty($data)) {
            $amount = $data['amount_total'] / 100;
            $currency = $data['currency'];
            $transactionId = $data['payment_intent'];
            $order_id = $data['metadata']['order_id'];
            if ($data['status'] == 'complete') {
                $status = "Completed";
            } else {
                $status = "Failed";
            }


            $orderdetails = Order::where('id', $order_id)->where('is_paid', '0')->first();

            if (!empty($orderdetails)) {
                $total_amount = $orderdetails->total_amount;
                $paid_amount = $orderdetails->paid_amount + $amount;
                $pending_amount = $total_amount - $paid_amount;

                $order = Order::find($orderdetails->id);

                if ($status != 'Failed') {
                    $checkTransaction = PaymentManagement::where('transaction_id', $transactionId)->get()->count();
                    if (!$checkTransaction) {
                        PaymentManagement::create([
                            'order_id' => $orderdetails->id,
                            'transaction_id' => $transactionId,
                            'payment_mode' => 'Credit Card',
                            'payment_method' => 'Stripe',
                            'amount' => $amount,
                            'status' => $status,
                            'payment_date' => date('Y-m-d H:i:s'),
                            'notes' => 'Payment marked through link',
                            'payment_details' => ''
                        ]);
                    }
                }

            }
            // Example response
            return response()->json([
                'order_id' => $order_id ?? null,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
            ]);
        } else {
            return response()->json([
                'data' => $data ?? null,
                'status' => false,
            ]);
        }



    }


}
