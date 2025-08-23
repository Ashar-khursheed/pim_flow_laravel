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
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=50.0, description="Payment amount in dollars")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Client secret for payment intent returned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="clientSecret", type="string", example="pi_3Nx4x4xxxx_secret_1234")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error (missing or invalid amount)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Stripe error or internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Your card was declined.")
     *         )
     *     )
     * )
     */

    public function createPaymentIntent(Request $request)
    {
        // Validate amount input
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        // Set Stripe Secret Key
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amount * 100, // Stripe uses cents
                'currency' => 'aed',
                'description' => 'Test Payment',
                'payment_method_types' => ['card'],
            ]);

            return response()->json([
                'sucess' => true,
                'clientSecret' => $paymentIntent->client_secret,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'sucess' => false,
                'error' => $e->getMessage()], 500);
        }
    }
}
