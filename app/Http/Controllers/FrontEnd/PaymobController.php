<?php

namespace App\Http\Controllers;

use App\Services\PaymobService;
use Illuminate\Http\Request;

class PaymobController extends Controller
{
    protected $paymob;

    public function __construct(PaymobService $paymob)
    {
        $this->paymob = $paymob;
    }

    // Step 1: Initiate checkout
    public function initiate(Request $request)
    {
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
            'order_id' => $order['id'],
            'payment_token' => $paymentToken
        ]);
    }

    // Step 2: Confirm payment with card details
    public function pay(Request $request)
    {
        $paymentToken = $request->payment_token;
        $cardData = $request->only(['card_number', 'expiry_month', 'expiry_year', 'cvv']);

        $response = $this->paymob->payWithCard($paymentToken, $cardData);

        return response()->json($response);
    }

    // Webhook callback
    public function webhook(Request $request)
    {
        // TODO: Add HMAC verification
        return response()->json($request->all());
    }
}
