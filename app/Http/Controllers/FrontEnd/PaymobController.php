<?php


// namespace App\Http\Controllers\Frontend;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Http;
// use App\Models\FrontEnd\CustomerAddress;
// use App\Models\FrontEnd\Customer;
// class PaymobController extends Controller
// {
//     // Step 1: Initiate checkout
//     public function initiate(Request $request)
//     {
//         $amountCents = $request->amount * 100;
//         $merchantOrderId = uniqid();

//         $authToken = app('paymob')->authenticate();
//         $order = app('paymob')->createOrder($authToken, $amountCents, $merchantOrderId);

//         $billingData = [
//             "apartment" => "NA",
//             "email" => $request->email,
//             "floor" => "NA",
//             "first_name" => $request->first_name,
//             "last_name" => $request->last_name,
//             "phone_number" => $request->phone,
//             "street" => "NA",
//             "building" => "NA",
//             "shipping_method" => "NA",
//             "postal_code" => "NA",
//             "city" => "Cairo",
//             "country" => "EG",
//             "state" => "NA"
//         ];

//         $paymentToken = app('paymob')->getPaymentKey($authToken, $order['id'], $amountCents, $billingData);

//         return response()->json([
//             'order_id' => $order['id'],
//             'payment_token' => $paymentToken,
//             // You’ll load this in an iframe or redirect user:
//             'iframe_url' => "https://accept.paymobsolutions.com/api/acceptance/iframes/{IFRAME_ID}?payment_token={$paymentToken}"
//         ]);
//     }

//     // Webhook callback (server-to-server)
//     public function webhook(Request $request)
//     {
//         // ✅ Verify HMAC before trusting the request
//         $hmac = $request->hmac;
//         $calcHmac = $this->calculateHmac($request->all());

//         if ($hmac !== $calcHmac) {
//             return response()->json(['error' => 'Invalid HMAC'], 403);
//         }

//         // Process order status (success/failed)
//         // Example: mark order as paid in DB
//         return response()->json(['message' => 'Webhook received']);
//     }

//     // Transaction Response Callback (redirect after payment)
//     public function response(Request $request)
//     {
//         // ✅ Verify HMAC here as well
//         $hmac = $request->hmac;
//         $calcHmac = $this->calculateHmac($request->all());

//         if ($hmac !== $calcHmac) {
//             return view('payment.failed', ['message' => 'Invalid payment response']);
//         }

//         if ($request->success == "true") {
//             return view('payment.success', ['order_id' => $request->merchant_order_id]);
//         } else {
//             return view('payment.failed', ['message' => 'Payment failed, please try again.']);
//         }
//     }

//     // HMAC calculation helper
//     private function calculateHmac($data)
//     {
//         $secret = env('PAYMOB_HMAC_SECRET'); // from Paymob dashboard
//         $keys = [
//             "amount_cents",
//             "created_at",
//             "currency",
//             "error_occured",
//             "has_parent_transaction",
//             "id",
//             "integration_id",
//             "is_3d_secure",
//             "is_auth",
//             "is_capture",
//             "is_refunded",
//             "is_standalone_payment",
//             "is_voided",
//             "order",
//             "owner",
//             "pending",
//             "source_data_pan",
//             "source_data_sub_type",
//             "source_data_type",
//             "success"
//         ];

//         $concatenated = '';
//         foreach ($keys as $key) {
//             $concatenated .= $data[$key] ?? '';
//         }

//         return hash_hmac('sha512', $concatenated, $secret);
//     }


//     public function generatePaymobPaymentLink($order)
//     {
//         try {

//             $customerAddress = CustomerAddress::find($order->customer_address_id);
//             $customer = Customer::find($order->customer_id);
//             // $this->baseUrl;
//             $baseUrl = config('services.paymob.base_url');

//             // Step : Authentication - Get auth token
//             $authResponse = Http::post("{$baseUrl}/auth/tokens", [
//                 'api_key' => env('PAYMOB_API_KEY'),
//             ]);

//             if (!$authResponse->successful()) {
//                 throw new \Exception('Authentication failed: ' . $authResponse->body());
//             }

//             $authToken = $authResponse->json()['token'];

//             // Step Create order
//             $merchantOrderId = $order->order_number ?? uniqid('order_');
//             $amountCents = (int) ($order->total_amount * 100);

//             $orderResponse = Http::post("{$baseUrl}/ecommerce/orders", [
//                 'auth_token' => $authToken,
//                 'delivery_needed' => 'false', // String instead of boolean
//                 'amount_cents' => (string) $amountCents, // Convert to string
//                 'currency' => 'AED',
//                 'merchant_order_id' => (string) $merchantOrderId,
//                 'items' => [],
//             ]);
//             $orderID = $orderResponse->json();
//             // Generate payment key
//             $billingData = [
//                 'first_name' => $order->customer->name ?? 'N/A',
//                 'last_name' => $order->customer_name ?? 'N/A',
//                 'email' => $order->customer->email ?? 'user@example.com',
//                 'phone_number' => $order->customer->mobile_number ?? '+971000000000',
//                 'apartment' => 'NA',
//                 'floor' => 'NA',
//                 'street' => $customerAddress->type ?? "",
//                 'building' => $customerAddress->address ?? '',
//                 'shipping_method' => 'NA',
//                 'postal_code' => $customerAddress->zip_code ?? 'NA',
//                 'city' => $customerAddress->city ?? '',
//                 'country' => $customerAddress->country ?? '',
//                 'state' => $customerAddress->state ?? ''
//             ];

//             $paymentKeyResponse = Http::post("{$baseUrl}/acceptance/payment_keys", [
//                 'auth_token' => $authToken,
//                 'amount_cents' => $amountCents,
//                 'expiration' => 3600,
//                 'order_id' => $orderID["id"],
//                 'billing_data' => $billingData,
//                 'currency' => 'AED', // Fixed: Should be AED for UAE, not EGP
//                 'integration_id' => env('PAYMOB_INTEGRATION_ID'),
//             ]);
//             $paymentToken = $paymentKeyResponse->json()['token'];

//             // Step 4: Build iframe payment URL
//             $paymentUrl = "{$baseUrl}/acceptance/iframes/"
//                 . env('PAYMOB_IFRAME_ID')
//                 . "?payment_token="
//                 . $paymentToken;

//             return $paymentUrl;

//         } catch (\Exception $e) {
//             \Log::error('Paymob payment link generation failed', [
//                 'order_id' => $order->id,
//                 'error' => $e->getMessage()
//             ]);

//             throw $e;
//         }
//     }
// }


namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\Customer;
use Illuminate\Support\Facades\Log;
use App\Services\PaymobService;
use App\Models\PaymentManagement;
use App\Models\FrontEnd\Order;
class PaymobController extends Controller
{
    protected $paymob;

    public function __construct(PaymobService $paymob)
    {
        $this->paymob = $paymob;
    }

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
                "city" => "Dubai",
                "country" => "UAE",
                "state" => "NA"
            ];

            $intention = $this->paymob->createIntention(
                $request->amount, // decimal amount, e.g., 2881.2
                "SAR",
                $billingData,
                [
                    [
                        "name" => "Sample Item",
                        "amount" => $request->amount, // keep as decimal here
                        "description" => "Test Product",
                        "quantity" => 1,
                    ]
                ]
            );


            return response()->json([
                'status' => true,
                'intention' => $intention,
                'public_key' => env('PAYMOB_PUBLIC_KEY'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Payment initiation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
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
    // public function pay(Request $request)
// {
//     $request->validate([
//         'payment_token' => 'required|string',
//     ]);

    //     try {
//         // Case 1: Google Pay token (usually JSON or starts with "{")
//         if (str_starts_with(trim($request->payment_token), '{')) {
//             $response = $this->paymob->payWithGooglePay($request->payment_token);

    //         // Case 2: Manual card entry
//         } else {
//             $request->validate([
//                 'card_number' => 'required|string',
//                 'expiry_month' => 'required|string',
//                 'expiry_year' => 'required|string',
//                 'cvv' => 'required|string',
//             ]);

    //             $response = $this->paymob->payWithCard(
//                 $request->payment_token,
//                 $request->only(['card_number', 'expiry_month', 'expiry_year', 'cvv'])
//             );
//         }

    //         return response()->json([
//             'status' => true,
//             'response' => $response
//         ]);

    //     } catch (\Exception $e) {
//         \Log::error('Paymob Payment Error: ' . $e->getMessage());

    //         return response()->json([
//             'status' => false,
//             'message' => 'Payment failed',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }



    // Webhook callback (server-to-server)
    public function webhook(Request $request)
    {
        // ✅ Verify HMAC before trusting the request
        $hmac = $request->hmac;
        $calcHmac = $this->calculateHmac($request->all());

        if ($hmac !== $calcHmac) {
            Log::info('Paymob Webhook Invalid HMAC:', $request->all());
            //return response()->json(['error' => 'Invalid HMAC'], 403);
        }
        Log::info('Paymob Webhook Received:', $request->all());
        $data = $request->all();
        try {
            $transactionId = $data['id'] ?? null;
            $orderId = $data['merchant_order_id'] ?? null;
            $amount = ($data['amount_cents'] ?? 0) / 100;
            $currency = $data['currency'] ?? 'EGP';
            $status = $data['success'] ? 'Completed' : 'Failed';

            $checkTransaction = PaymentManagement::where('transaction_id', $transactionId)->get()->count();
            if (!$checkTransaction) {
                PaymentManagement::create([
                    'order_id' => $orderId,
                    'transaction_id' => $transactionId,
                    'payment_mode' => 'Credit Card',
                    'payment_method' => 'Paymob',
                    'amount' => $amount,
                    'status' => $status,
                    'payment_date' => date('Y-m-d H:i:s'),
                    'notes' => 'Payment marked through link',
                    'payment_details' => ''
                ]);
            }

            return response()->json(['message' => 'Webhook processed'], 200);

        } catch (\Exception $e) {
            Log::error('Paymob Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    // Transaction Response Callback (redirect after payment)
    public function response(Request $request)
    {
        // ✅ Verify HMAC here as well
        $hmac = $request->hmac;
        $calcHmac = $this->calculateHmac($request->all());
        if ($hmac !== $calcHmac) {
            Log::info('Paymob thank Invalid HMAC:', $request->all());
            //return response()->json(['error' => 'Invalid HMAC'], 403);
        }
        Log::info('Paymob thank Received:', $request->all());

        $data = $request->all();

        try {
            $transactionId = $data['id'] ?? null;
            $orderId = $data['merchant_order_id'] ?? null;
            $amount = ($data['amount_cents'] ?? 0) / 100;
            $currency = $data['currency'] ?? 'EGP';
            $status = $data['success'] ? 'Completed' : 'Failed';

            $orderdetails = Order::where('order_number', $orderId)->where('is_paid', '0')->first();

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
                            'payment_method' => 'Paymob',
                            'amount' => $amount,
                            'status' => $status,
                            'payment_date' => date('Y-m-d H:i:s'),
                            'notes' => 'Payment marked through link',
                            'payment_details' => ''
                        ]);
                    }
                }
            }

            return view('thanks', ['amount' => $amount, 'transaction_id' => $transactionId]);


        } catch (\Exception $e) {
            Log::error('Paymob Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    // HMAC calculation helper
    private function calculateHmac($data)
    {
        $secret = env('PAYMOB_HMAC_SECRET'); // from Paymob dashboard
        $keys = [
            "amount_cents",
            "created_at",
            "currency",
            "error_occured",
            "has_parent_transaction",
            "id",
            "integration_id",
            "is_3d_secure",
            "is_auth",
            "is_capture",
            "is_refunded",
            "is_standalone_payment",
            "is_voided",
            "order",
            "owner",
            "pending",
            "source_data_pan",
            "source_data_sub_type",
            "source_data_type",
            "success"
        ];

        $concatenated = '';
        foreach ($keys as $key) {
            $concatenated .= $data[$key] ?? '';
        }

        return hash_hmac('sha512', $concatenated, $secret);
    }


    public function generatePaymobPaymentLink($order)
    {
        try {

            $customerAddress = CustomerAddress::find($order->customer_address_id);
            $customer = Customer::find($order->customer_id);
            // $this->baseUrl;
            $baseUrl = config('services.paymob.base_url');

            // Step : Authentication - Get auth token
            $authResponse = Http::post("{$baseUrl}/auth/tokens", [
                'api_key' => env('PAYMOB_API_KEY'),
            ]);

            if (!$authResponse->successful()) {
                throw new \Exception('Authentication failed: ' . $authResponse->body());
            }

            $authToken = $authResponse->json()['token'];

            // Step Create order
            $merchantOrderId = $order->order_number ?? uniqid('order_');
            $amountCents = (int) ($order->pending_amount * 100);

            $orderResponse = Http::post("{$baseUrl}/ecommerce/orders", [
                'auth_token' => $authToken,
                'delivery_needed' => 'false', // String instead of boolean
                'amount_cents' => (string) $amountCents, // Convert to string
                'currency' => 'SAR',
                'merchant_order_id' => (string) $merchantOrderId,
                'items' => [],
            ]);
            $orderID = $orderResponse->json();
            // Generate payment key
            $billingData = [
                'first_name' => $order->customer->name ?? 'N/A',
                'last_name' => $order->customer_name ?? 'N/A',
                'email' => $order->customer->email ?? 'user@example.com',
                'phone_number' => $order->customer->mobile_number ?? '+971000000000',
                'apartment' => 'NA',
                'floor' => 'NA',
                'street' => $customerAddress->type ?? "",
                'building' => $customerAddress->address ?? '',
                'shipping_method' => 'NA',
                'postal_code' => $customerAddress->zip_code ?? 'NA',
                'city' => $customerAddress->city ?? '',
                'country' => $customerAddress->country ?? '',
                'state' => $customerAddress->state ?? ''
            ];

            $paymentKeyResponse = Http::post("{$baseUrl}/acceptance/payment_keys", [
                'auth_token' => $authToken,
                'amount_cents' => $amountCents,
                'expiration' => 3600,
                'order_id' => $orderID["id"],
                'billing_data' => $billingData,
                'currency' => 'SAR', // Fixed: Should be AED for UAE, not EGP
                'integration_id' => env('PAYMOB_LINK_ID'),
                'redirect_url' => config('app.url').'/thanks',
                'notification_url' => config('app.backend_url').'/api/paymob/webhook',

            ]);
            $paymentToken = $paymentKeyResponse->json()['token'];

            //Step : Build iframe payment URL
            $paymentUrl = "{$baseUrl}/acceptance/iframes/"
                . env('PAYMOB_IFRAME_ID')
                . "?payment_token="
                . $paymentToken;
            return $paymentUrl;
        } catch (\Exception $e) {
            \Log::error('Paymob payment link generation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
