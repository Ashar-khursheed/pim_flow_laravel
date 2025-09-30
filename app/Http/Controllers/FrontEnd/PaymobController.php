<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymobController extends Controller
{
    protected $paymob;

    public function __construct(PaymobService $paymob)
    {
        $this->paymob = $paymob;
    }

    /**
     * Step 1: Initiate checkout
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'email' => 'required|email',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
        ]);

        try {
            $amountCents = $request->amount * 100;
            $merchantOrderId = uniqid();

            $authToken = $this->paymob->authenticate();
            $order = $this->paymob->createOrder($authToken, $amountCents, $merchantOrderId);

            $billingData = [
                "apartment" => "NA",
                "email" => $request->email,
                "floor" => "NA",
                "first_name" => $request->first_name,
                "last_name" => $request->last_name,
                "phone_number" => $request->phone,
                "street" => "NA",
                "building" => "NA",
                "shipping_method" => "NA",
                "postal_code" => "NA",
                "city" => "Cairo",
                "country" => "EG",
                "state" => "NA"
            ];

            $paymentToken = $this->paymob->getPaymentKey($authToken, $order['id'], $amountCents, $billingData);

            return response()->json([
                'status' => true,
                'order_id' => $order['id'],
                'merchant_order_id' => $merchantOrderId,
                'payment_token' => $paymentToken,
                'iframe_url' => "https://accept.paymob.com/api/acceptance/iframes/" . env('PAYMOB_IFRAME_ID') . "?payment_token=$paymentToken"
            ]);

        } catch (\Exception $e) {
            Log::error('Paymob Initiate Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Payment initiation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 2: Confirm payment with card details
     */
    public function pay(Request $request)
    {
        $request->validate([
            'payment_token' => 'required|string',
            'card_number' => 'required|string',
            'expiry_month' => 'required|string',
            'expiry_year' => 'required|string',
            'cvv' => 'required|string',
        ]);

        try {
            $response = $this->paymob->payWithCard(
                $request->payment_token,
                $request->only(['card_number', 'expiry_month', 'expiry_year', 'cvv'])
            );

            return response()->json([
                'status' => true,
                'response' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('Paymob Card Payment Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Payment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook callback
     */
    public function webhook(Request $request)
    {
        $hmacSecret = env('PAYMOB_HMAC');
        $data = $request->all();

        // Verify HMAC
        $calculatedHmac = hash_hmac('sha512', json_encode($data), $hmacSecret);
        $receivedHmac = $request->input('hmac');

        if ($calculatedHmac !== $receivedHmac) {
            Log::warning('Invalid HMAC on Paymob webhook', $data);
            return response()->json(['status' => false, 'message' => 'Invalid HMAC'], 400);
        }

        // Handle payment success/failure
        Log::info('Paymob Webhook Received', $data);

        return response()->json(['status' => true, 'data' => $data]);
    }
}
