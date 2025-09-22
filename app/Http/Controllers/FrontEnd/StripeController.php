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
    public function createPaymentIntent(Request $request)
    {
        // Validate input
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method_id' => 'required|string',
            'currency' => 'sometimes|string|in:aed,usd',
            'customer_info' => 'sometimes|array'
        ]);

        // Set Stripe Secret Key
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Create PaymentIntent
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($request->amount * 100), // Convert to smallest unit and round
                'currency' => $request->currency ?? 'aed',
                'payment_method' => $request->payment_method_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => config('app.url') . '/payment/return',
                'description' => 'Order Payment - ' . now()->format('Y-m-d H:i:s'),
                'metadata' => [
                    'customer_name' => $request->customer_info['name'] ?? '',
                    'customer_email' => $request->customer_info['email'] ?? '',
                    'order_timestamp' => now()->toISOString()
                ]
            ]);


            // Handle the payment intent status
            if ($paymentIntent->status === 'requires_action') {
                return response()->json([
                    'success' => true,
                    'requires_action' => true,
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'payment_mode' => 'Credit Card',
                    'status' => 'requires_action',
                    'payment_method' => 'Stripe'
                ]);
            } else if ($paymentIntent->status === 'succeeded') {
                return response()->json([
                    'success' => true,
                    'requires_action' => false,
                    'payment_intent_id' => $paymentIntent->id,
                    'payment_mode' => 'Credit Card',
                    'status' => 'completed',
                    'payment_method' => 'Stripe',
                    'client_secret' => $paymentIntent->client_secret
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment failed with status: ' . $paymentIntent->status,
                    'payment_intent_status' => $paymentIntent->status
                ], 400);
            }

        } catch (\Stripe\Exception\CardException $e) {
            // Card was declined
            return response()->json([
                'success' => false,
                'error' => $e->getError()->message,
                'decline_code' => $e->getError()->decline_code ?? null
            ], 400);
        } catch (\Stripe\Exception\RateLimitException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Too many requests made to the API too quickly'
            ], 429);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid parameters: ' . $e->getError()->message
            ], 400);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication with Stripe\'s API failed'
            ], 401);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Network communication with Stripe failed'
            ], 500);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Stripe API error: ' . $e->getError()->message
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Stripe Payment Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

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
        $success_url = url('/api/stripe/thanks') . '?session_id={CHECKOUT_SESSION_ID}';
        $cancel_url = url('/api/stripe/failed');
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
                'success_url' => url('/api/stripe/thanks?order_id=' . $order_id),
                'failed_url' => url('/api/stripe/failed?order_id=' . $order_id)
            ]
        ];
        // You now have a permanent payment link
        return response()->json([
            'payment_url' => $body['url'],
        ]);

    }

    public function generatePaymentLink($order)
    {
        $totalAmount = (int) round($order->total_amount * 100);

        // Handle both real orders and test objects
        if (is_object($order) && isset($order->orderProducts)) {
            $product = $order->orderProducts->first();
            $itemName = $order->orderProducts->count() > 1
                ? "Order #" . $order->order_number . " (" . $order->orderProducts->count() . " items)"
                : ($product->product->name ?? "Order #" . $order->order_number);
        } else {
            $itemName = "Order #" . $order->order_number;
        }
        $url = config('app.url');
        $stripeSecret = config('services.stripe.secret');
        $currency = "AED";
        // $success_url = $url.'/api/stripe/thanks' . '?session_id={CHECKOUT_SESSION_ID}';
        // $cancel_url = $url.'/api/stripe/failed';
        $success_url = url('/api/stripe/thanks') . '?session_id={CHECKOUT_SESSION_ID}';
        $cancel_url = url('/api/stripe/failed');
        $res = Http::withOptions(['verify' => false]);
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
                'metadata[order_id]' => $order->id,
            ]);

        $body = $res->json();
        if (!isset($body['url'])) {
            return;
        } else {
            return $body['url'];
        }

    }

    /**
     * @OA\Get(
     *     path="/api/stripe/thanks",
     *     summary="Stripe Payment Success Redirect",
     *     tags={"Stripe"},
     *     @OA\Response(
     *         response=200,
     *         description="Payment was successful"
     *     )
     * )
     */
    public function paymentSuccess(Request $request)
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
        $id = $data['id'];
        $amount_total = $data['amount_total'];
        $created = date('Y-m-d', strtotime($data['created']));
        $currency = $data['currency'];
        $mode = $data['mode'];
        $payment_intent = $data['payment_intent'];
        $order_id = $data['metadata']['order_id'];
        $customer_details = $data['customer_details'];
        $payment_method_types = $data['payment_method_types']['0'];
        $payment_status = $data['payment_status'];
        $status = $data['status'];
        if ($status == 'complete') {
            $status = "Completed";
        } elseif ($status == 'success') {
            $status = "Completed";
        } elseif ($status == "processing") {
            $status = "Pending";
        } elseif ($status == "canceled") {
            $status = "Failed";
        } elseif ($status == "failed") {
            $status = "Failed";
        } elseif ($status == "expired") {
            $status = "Failed";
        } elseif ($status == "succeeded") {
            $status = "Completed";
        } else {
            $status = "Completed";
        }
        $email = $data['customer_details']['email'];
        $name = $data['customer_details']['name'];
        $phone = $data['customer_details']['phone'];
        $city = $data['customer_details']['address']['city'];
        $country = $data['customer_details']['address']['country'];
        $line1 = $data['customer_details']['address']['line1'];
        $postal_code = $data['customer_details']['address']['postal_code'];
        $state = $data['customer_details']['address']['state'];
        $stripeSecret = config('services.stripe.secret');
        $paymentIntent = Http::withOptions(['verify' => false])

            ->withToken($stripeSecret)
            ->get("https://api.stripe.com/v1/payment_intents/{$payment_intent}")
            ->json();

        PaymentManagement::create([
            'order_id' => $order_id,
            'transaction_id' => $payment_intent,
            'payment_mode' => 'Credit Card',
            'payment_method' => 'Stripe',
            'amount' => $amount_total / 100,
            'status' => $status,
            'payment_date' => date('Y-m-d H:i:s'),
            'notes' => 'Payment marked through link',
            'payment_details' => ''
        ]);

        $order = Order::where('id', $order_id)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }
        if ($status == 'succeeded') {
            if ($order->amount_total == $amount_total) {
                // Mark order as paid and remove payment link
                $order->update([
                    'is_paid' => true,
                    'paid_amount' => $amount_total / 100,
                    'pending_amount' => $order->pending_amount - ($amount_total / 100),
                    'payment_link' => null,
                    'status' => $status
                ]);
            } else {
                $order->update([
                    'is_paid' => false,
                    'paid_amount' => $order->paid_amount + ($amount_total / 100),
                    'pending_amount' => $order->pending_amount - ($amount_total / 100),
                    'payment_link' => null,
                    'status' => $status
                ]);

            }
        }
        // Example response
        return response()->json([
            'order_id' => $order_id ?? null,
            'amount' => ($amount_total / 100),
            'currency' => $currency,
            'status' => $status,
        ]);

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
        $id = $data['id'];
        $amount_total = $data['amount_total'];
        $created = date('Y-m-d', strtotime($data['created']));
        $currency = $data['currency'];
        $mode = $data['mode'];
        $payment_intent = $data['payment_intent'];
        $order_id = $data['metadata']['order_id'];
        $customer_details = $data['customer_details'];
        $payment_method_types = $data['payment_method_types']['0'];
        $payment_status = $data['payment_status'];
        $status = $data['status'];
        if ($status == 'complete') {
            $status = "Completed";
        } elseif ($status == 'success') {
            $status = "Completed";
        } elseif ($status == "processing") {
            $status = "Pending";
        } elseif ($status == "canceled") {
            $status = "Failed";
        } elseif ($status == "failed") {
            $status = "Failed";
        } elseif ($status == "expired") {
            $status = "Failed";
        } elseif ($status == "succeeded") {
            $status = "Completed";
        } else {
            $status = "Failed";
        }
        $email = $data['customer_details']['email'];
        $name = $data['customer_details']['name'];
        $phone = $data['customer_details']['phone'];
        $city = $data['customer_details']['address']['city'];
        $country = $data['customer_details']['address']['country'];
        $line1 = $data['customer_details']['address']['line1'];
        $postal_code = $data['customer_details']['address']['postal_code'];
        $state = $data['customer_details']['address']['state'];
        $stripeSecret = config('services.stripe.secret');
        $paymentIntent = Http::withOptions(['verify' => false])

            ->withToken($stripeSecret)
            ->get("https://api.stripe.com/v1/payment_intents/{$payment_intent}")
            ->json();

        PaymentManagement::create([
            'order_id' => $order_id,
            'transaction_id' => $payment_intent,
            'payment_mode' => 'Credit Card',
            'payment_method' => 'Stripe',
            'amount' => $amount_total / 100,
            'status' => $status,
            'payment_date' => date('Y-m-d H:i:s'),
            'notes' => 'Payment marked through link',
            'payment_details' => ''
        ]);

        $order = Order::where('id', $order_id)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }


        // Example response
        return response()->json([
            'order_id' => $order_id ?? null,
            'amount' => ($amount_total / 100),
            'currency' => $currency,
            'status' => $status,
        ]);

    }


}