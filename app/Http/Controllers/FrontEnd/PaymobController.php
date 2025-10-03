<?php
// namespace App\Http\Controllers\Frontend;

// use App\Http\Controllers\Controller;

// use App\Services\PaymobService;
// use Illuminate\Http\Request;

// class PaymobController extends Controller
// {
//     protected $paymob;

//     public function __construct(PaymobService $paymob)
//     {
//         $this->paymob = $paymob;
//     }

//     // Step 1: Initiate checkout
//     public function initiate(Request $request)
//     {
//         $amountCents = $request->amount * 100;
//         $merchantOrderId = uniqid();

//         $authToken = $this->paymob->authenticate();
//         $order = $this->paymob->createOrder($authToken, $amountCents, $merchantOrderId);

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

//         $paymentToken = $this->paymob->getPaymentKey($authToken, $order['id'], $amountCents, $billingData);

//         return response()->json([
//             'order_id' => $order['id'],
//             'payment_token' => $paymentToken
//         ]);
//     }

//     // Step 2: Confirm payment with card details
//     public function pay(Request $request)
//     {
//         $paymentToken = $request->payment_token;
//         $cardData = $request->only(['card_number', 'expiry_month', 'expiry_year', 'cvv']);

//         $response = $this->paymob->payWithCard($paymentToken, $cardData);

//         return response()->json($response);
//     }

//     // Webhook callback
//     public function webhook(Request $request)
//     {
//         // TODO: Add HMAC verification
//         return response()->json($request->all());
//     }
// }


namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
class PaymobController extends Controller
{
    protected $paymob;
    private $apiKey;
    private $integrationId;
    private $iframeId;
    private $hmacSecret;
    private $baseUrl;
    public function __construct(PaymobService $paymob)
    {
        $this->paymob = $paymob;
        $this->integrationId = config('services.paymob.integration_id');
        $this->iframeId = config('services.paymob.iframe_id');
        $this->hmacSecret = config('services.paymob.hmac_secret');
        $this->baseUrl = config('services.paymob.base_url', 'https://accept.paymob.com');
        $this->apiKey = config('services.paymob.api_key');

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
                "AED",
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

    /**
     * Generate payment link
     * 
     * @param array $orderData
     * @return array
     */
    public function generatePaymentLink($order)
    {
        try {       

            $authToken = $this->paymob->authenticate();
            $paymentKey = $this->paymob->getPaymentKey($authToken, $order['merchant_order_id'], $order['amount_cents'], $order['billing_data']);


            if (!$paymentKey) {
                return [
                    'success' => false,
                    'message' => 'Payment key generation failed'
                ];
            }

            // Step 4: Generate payment link
            $paymentLink = "{$this->baseUrl}/acceptance/iframes/{$this->iframeId}?payment_token={$paymentKey}";

            return [
                'payment_link' => $paymentLink,
            ];

        } catch (\Exception $e) {
            \Log::error('Paymob Payment Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

    }

    public function generatePaymobPaymentLink($order)
    {

        $orderData = [
            'amount_cents' => $order->total_amount * 100, // Convert to cents
            'currency' => 'EGP',
            'merchant_order_id' => $order->order_number,
            'delivery_needed' => false,
            'items' => [],
            'billing_data' => [
                'first_name' => $order->customer->name ?? '',
                'last_name' => $order->customer_name,
                'email' => $order->customer->email ?? '',
                'phone_number' => $order->customer->mobile_number ?? '',
                'apartment' => 'NA',
                'floor' => 'NA',
                'street' => 'NA',
                'building' => 'NA',
                'shipping_method' => 'NA',
                'postal_code' => 'NA',
                'city' => 'NA',
                'country' => 'NA',
                'state' => 'NA'
            ]
        ];

        $result = $this->generatePaymentLink($orderData);

        return response()->json($result);
    }
}