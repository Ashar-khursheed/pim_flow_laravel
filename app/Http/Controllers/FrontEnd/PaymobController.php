<?php


namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymobController extends Controller
{
    // Step 1: Initiate checkout
    public function initiate(Request $request)
    {
        $amountCents = $request->amount * 100;
        $merchantOrderId = uniqid();

        $authToken = app('paymob')->authenticate();
        $order = app('paymob')->createOrder($authToken, $amountCents, $merchantOrderId);

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

        $paymentToken = app('paymob')->getPaymentKey($authToken, $order['id'], $amountCents, $billingData);

        return response()->json([
            'order_id' => $order['id'],
            'payment_token' => $paymentToken,
            // You’ll load this in an iframe or redirect user:
            'iframe_url' => "https://accept.paymobsolutions.com/api/acceptance/iframes/{IFRAME_ID}?payment_token={$paymentToken}"
        ]);
    }

    // Webhook callback (server-to-server)
    public function webhook(Request $request)
    {
        // ✅ Verify HMAC before trusting the request
        $hmac = $request->hmac;
        $calcHmac = $this->calculateHmac($request->all());

        if ($hmac !== $calcHmac) {
            return response()->json(['error' => 'Invalid HMAC'], 403);
        }

        // Process order status (success/failed)
        // Example: mark order as paid in DB
        return response()->json(['message' => 'Webhook received']);
    }

    // Transaction Response Callback (redirect after payment)
    public function response(Request $request)
    {
        // ✅ Verify HMAC here as well
        $hmac = $request->hmac;
        $calcHmac = $this->calculateHmac($request->all());

        if ($hmac !== $calcHmac) {
            return view('payment.failed', ['message' => 'Invalid payment response']);
        }

        if ($request->success == "true") {
            return view('payment.success', ['order_id' => $request->merchant_order_id]);
        } else {
            return view('payment.failed', ['message' => 'Payment failed, please try again.']);
        }
    }

    // HMAC calculation helper
    private function calculateHmac($data)
    {
        $secret = env('PAYMOB_HMAC_SECRET'); // from Paymob dashboard
        $keys = [
            "amount_cents", "created_at", "currency", "error_occured", "has_parent_transaction",
            "id", "integration_id", "is_3d_secure", "is_auth", "is_capture", "is_refunded",
            "is_standalone_payment", "is_voided", "order", "owner", "pending", "source_data_pan",
            "source_data_sub_type", "source_data_type", "success"
        ];

        $concatenated = '';
        foreach ($keys as $key) {
            $concatenated .= $data[$key] ?? '';
        }

        return hash_hmac('sha512', $concatenated, $secret);
    }
}