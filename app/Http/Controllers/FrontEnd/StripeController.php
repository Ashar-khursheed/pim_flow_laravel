<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;

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
            'amount' => $request->amount * 100, // Stripe uses cents
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
}