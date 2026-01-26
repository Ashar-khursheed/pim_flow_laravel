<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\PaymentManagement;
use App\Models\FrontEnd\Order;

class NoFraudController extends Controller
{
  /**
 * @OA\Post(
 *     path="/api/screen-transaction",
 *     operationId="screenTransaction",
 *     tags={"NoFraud"},
 *     security={{"bearerAuth":{}}},
 *     summary="Send transaction data to NoFraud API and save the response",
 *     description="This endpoint sends billing, shipping, and card data to NoFraud for fraud screening. It returns the screening decision and stores it in the database.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"order_id", "amount", "billing_name", "billing_email", "billing_phone", "billing_address", "billing_city", "billing_state", "billing_zip", "billing_country"},
 *
 *             @OA\Property(property="order_id", type="string", example="ORDER123"),
 *             @OA\Property(property="amount", type="number", format="float", example=49.99),
 *
 *             @OA\Property(property="billing_first_name", type="string", example="John"),
 *             @OA\Property(property="billing_last_name", type="string", example="Doe"),
 *             @OA\Property(property="billing_email", type="string", format="email", example="john@example.com"),
 *             @OA\Property(property="billing_phone", type="string", example="1234567890"),
 *             @OA\Property(property="billing_address", type="string", example="123 Main St"),
 *             @OA\Property(property="billing_city", type="string", example="New York"),
 *             @OA\Property(property="billing_state", type="string", example="NY"),
 *             @OA\Property(property="billing_zip", type="string", example="10001"),
 *             @OA\Property(property="billing_country", type="string", example="US"),
 *
 *             @OA\Property(property="shipping_first_name", type="string", example="John"),
 *             @OA\Property(property="shipping_last_name", type="string", example="Doe"),
 *             @OA\Property(property="shipping_address", type="string", example="123 Main St"),
 *             @OA\Property(property="shipping_city", type="string", example="New York"),
 *             @OA\Property(property="shipping_state", type="string", example="NY"),
 *             @OA\Property(property="shipping_zip", type="string", example="10001"),
 *             @OA\Property(property="shipping_country", type="string", example="US"),
 *
 *             @OA\Property(property="card_bin", type="string", example="411111"),
 *             @OA\Property(property="card_last4", type="string", example="4242"),
 *             @OA\Property(property="card_type", type="string", example="Visa"),
 *             @OA\Property(property="card_expiration", type="string", example="0925")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Transaction screened successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="decision", type="string", example="pass"),
 *             @OA\Property(property="nofraud_result", type="object")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid request",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Validation failed"),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="NoFraud API error"
 *     )
 * )
 */


public function screenTransaction(Request $request)
{
    // Validate required fields
    $validator = Validator::make($request->all(), [
        'order_id' => 'required|string',
        'amount' => 'required|numeric|min:0.01',
        'billing_first_name' => 'required|string|max:100',
        'billing_last_name' => 'nullable|string|max:100',
        'billing_email' => 'required|email:strict',
        'billing_phone' => 'nullable|string|max:20',
        'billing_address' => 'required|string|max:255',
        'billing_city' => 'required|string|max:100',
        'billing_state' => 'required|string|max:50',
        'billing_zip' => 'required|string|max:20',
        'billing_country' => 'required|string|size:2',
        'card_bin' => 'nullable|digits_between:6,8',
        'card_last4' => 'required|digits:4',


    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 400);
    }

    try {
        // Build the payload for NoFraud API
        $payload = [
            'nf-token' => env('NOFRAUD_API_KEY'),
            'amount' => number_format((float)$request->amount, 2, '.', ''),
            'currencyCode' => $request->currency_code ?? 'USD',
            'customerIP' => $request->ip(),

            'billTo' => [
                'firstName' => $request->billing_first_name,
                'lastName' => $request->billing_last_name,
                'company' => $request->billing_company ?? '',
                'address' => $request->billing_address,
                'city' => $request->billing_city,
                'state' => $request->billing_state,
                'zip' => $request->billing_zip,
                'country' => $request->billing_country,
                'phoneNumber' => $request->billing_phone,
            ],

            'shipTo' => [
                'firstName' => $request->shipping_first_name ?? $request->billing_first_name,
                'lastName' => $request->shipping_last_name ?? $request->billing_last_name,
                'company' => $request->shipping_company ?? $request->billing_company ?? '',
                'address' => $request->shipping_address ?? $request->billing_address,
                'city' => $request->shipping_city ?? $request->billing_city,
                'state' => $request->shipping_state ?? $request->billing_state,
                'zip' => $request->shipping_zip ?? $request->billing_zip,
                'country' => $request->shipping_country ?? $request->billing_country,
            ],

            'payment' => [
                'method' => 'Credit Card',
                'creditCard' => [
                    'last4' => $request->card_last4,
                    'bin' => $request->card_bin,
                    'cardType' => $request->card_type ?? $this->getCardTypeFromBin($request->card_bin),
                    'expirationDate' => $request->card_expiration ?? null,
                ],
            ],

            'order' => [
                'invoiceNumber' => $request->order_id,
                'orderType' => $request->order_type ?? 'one-time',
                'description' => $request->order_description ?? 'Online Order',
            ],

            'customer' => [
                'id' => $request->customer_id ?? 'guest-' . uniqid(),
                'email' => $request->billing_email,
                'joinedOn' => $request->customer_joined_date ?
                    date('m/d/Y', strtotime($request->customer_joined_date)) : null,
                'lastSignIn' => $request->customer_last_signin ?
                    date('m/d/Y', strtotime($request->customer_last_signin)) : null,
                'lastPurchaseDate' => $request->customer_last_purchase ?
                    date('m/d/Y', strtotime($request->customer_last_purchase)) : null,
                'totalPreviousPurchases' => $request->customer_total_purchases ?? 0,
                'totalPurchaseValue' => $request->customer_total_value ?? 0,
            ],
        ];

        // Remove null values to avoid API issues
        $payload = $this->removeNullValues($payload);


        // Make the API call
        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post(config('services.nofraud.api_url'), $payload);

        if ($response->successful()) {
            $result = $response->json();


            // Save the response to database
            try {
                \App\Models\NoFraudResponse::create([
                    'order_id' => $request->order_id,
                   'response' => $result, // Only the 'nofraud_result' part
                    'created_at' => now(),
                ]);
            } catch (\Exception $e) {
            }

            return response()->json([
                'status' => 'success',
                'decision' => $result['decision'] ?? 'unknown',
                'score' => $result['score'] ?? null,
                'nofraud_result' => $result,
            ]);
        }

        // Handle API errors
        $errorMessage = 'NoFraud API request failed';
        $errorDetails = $response->body();



        return response()->json([
            'status' => 'error',
            'message' => $errorMessage,
            'details' => $response->status() >= 500 ? 'Service temporarily unavailable' : $errorDetails,
        ], $response->status());

    } catch (\Exception $e) {

        return response()->json([
            'status' => 'error',
            'message' => 'An unexpected error occurred while processing the transaction',
        ], 500);
    }
}

/**
 * Remove null values from array recursively
 */
private function removeNullValues($array)
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = $this->removeNullValues($value);
        } elseif (is_null($value)) {
            unset($array[$key]);
        }
    }
    return $array;
}

// public function screenTransaction(Request $request)
// {
//     // Validate required fields
//     $validator = Validator::make($request->all(), [
//         'order_id' => 'required|string',
//         'amount' => 'required|numeric|min:0.01',
//         'billing_first_name' => 'required|string|max:100',
//         'billing_last_name' => 'required|string|max:100',
//         'billing_email' => 'required|email:strict',
//         'billing_phone' => 'required|string|max:20',
//         'billing_address' => 'required|string|max:255',
//         'billing_city' => 'required|string|max:100',
//         'billing_state' => 'required|string|max:10',
//         'billing_zip' => 'required|string|max:20',
//         'billing_country' => 'required|string|size:2',
//         'card_bin' => 'nullable|digits_between:6,8',
//         'card_last4' => 'required|digits:4',
//         'avs_result' => 'required|string',
//         'cvv_result' => 'required|string',
//     ]);

//     if ($validator->fails()) {
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Validation failed',
//             'errors' => $validator->errors()
//         ], 400);
//     }

//     try {
//         // Normalize enums for AVS/CVV
//         $avsCode = $this->normalizeAvs($request->avs_result);
//         $cvvCode = $this->normalizeCvv($request->cvv_result);

//         // Get real customer IP
//         $customerIp = $request->header('X-Forwarded-For')
//             ? explode(',', $request->header('X-Forwarded-For'))[0]
//             : $request->ip();

//         // Build the payload for NoFraud API
//         $payload = [
//             'nf-token' => env('NOFRAUD_API_KEY'),
//             'amount' => number_format((float)$request->amount, 2, '.', ''),
//             'currency_code' => $request->currency_code ?? 'USD', // ✅ fixed
//             'customerIP' => $customerIp,

//             'billTo' => [
//                 'firstName' => $request->billing_first_name,
//                 'lastName' => $request->billing_last_name,
//                 'company' => $request->billing_company ?? '',
//                 'address' => $request->billing_address,
//                 'city' => $request->billing_city,
//                 'state' => $request->billing_state,
//                 'zip' => $request->billing_zip,
//                 'country' => $request->billing_country,
//                 'phoneNumber' => $request->billing_phone,
//             ],

//             'shipTo' => [
//                 'firstName' => $request->shipping_first_name ?? $request->billing_first_name,
//                 'lastName' => $request->shipping_last_name ?? $request->billing_last_name,
//                 'company' => $request->shipping_company ?? $request->billing_company ?? '',
//                 'address' => $request->shipping_address ?? $request->billing_address,
//                 'city' => $request->shipping_city ?? $request->billing_city,
//                 'state' => $request->shipping_state ?? $request->billing_state,
//                 'zip' => $request->shipping_zip ?? $request->billing_zip,
//                 'country' => $request->shipping_country ?? $request->billing_country,
//             ],

//             'payment' => [
//                 'method' => 'Credit Card',
//                 'creditCard' => [
//                     'last4' => $request->card_last4,
//                     'bin' => $request->card_bin,
//                     'cardType' => $request->card_type ?? $this->getCardTypeFromBin($request->card_bin),
//                     'expirationDate' => $request->card_expiration ?? null,
//                     'avsResultCode' => $avsCode, // ✅ new
//                     'cvvResultCode' => $cvvCode, // ✅ new
//                 ],
//             ],

//             'order' => [
//                 'invoiceNumber' => $request->order_id,
//                 'orderType' => $request->order_type ?? 'one-time',
//                 'description' => $request->order_description ?? 'Online Order',
//             ],

//             'customer' => [
//                 'id' => $request->customer_id ?? 'guest-' . uniqid(),
//                 'email' => $request->billing_email,
//                 'joinedOn' => $request->customer_joined_date ?
//                     date('m/d/Y', strtotime($request->customer_joined_date)) : null,
//                 'lastSignIn' => $request->customer_last_signin ?
//                     date('m/d/Y', strtotime($request->customer_last_signin)) : null,
//                 'lastPurchaseDate' => $request->customer_last_purchase ?
//                     date('m/d/Y', strtotime($request->customer_last_purchase)) : null,
//                 'totalPreviousPurchases' => $request->customer_total_purchases ?? 0,
//                 'totalPurchaseValue' => $request->customer_total_value ?? 0,
//             ],
//         ];

//         // Remove null values to avoid API issues
//         $payload = $this->removeNullValues($payload);

//         // Make the API call
//         $response = Http::timeout(30)
//             ->withHeaders([
//                 'Content-Type' => 'application/json',
//                 'Accept' => 'application/json',
//             ])
//             ->post(config('services.nofraud.api_url') . '/transaction', $payload);

//         if ($response->successful()) {
//             $result = $response->json();

//             // Save the response to database
//             try {
//                 \App\Models\NoFraudResponse::create([
//                     'order_id' => $request->order_id,
//                     'response' => $result,
//                     'created_at' => now(),
//                 ]);
//             } catch (\Exception $e) {
//             }

//             return response()->json([
//                 'status' => 'success',
//                 'decision' => $result['decision'] ?? 'unknown',
//                 'score' => $result['score'] ?? null,
//                 'nofraud_result' => $result,
//             ]);
//         }

//         // Handle API errors
//         return response()->json([
//             'status' => 'error',
//             'message' => 'NoFraud API request failed',
//             'details' => $response->status() >= 500 ? 'Service temporarily unavailable' : $response->body(),
//         ], $response->status());

//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => 'error',
//             'message' => 'An unexpected error occurred while processing the transaction',
//         ], 500);
//     }
// }

// /**
//  * Normalize AVS codes
//  */
// private function normalizeAvs($code)
// {
//     return match ($code) {
//         'AVS_ACCEPTED' => 'Y',
//         'AVS_REJECTED' => 'N',
//         'AVS_NOT_CHECKED' => 'U',
//         default => 'U',
//     };
// }

// /**
//  * Normalize CVV codes
//  */
// private function normalizeCvv($code)
// {
//     return match ($code) {
//         'CVV_ACCEPTED' => 'M',
//         'CVV_REJECTED' => 'N',
//         'CVV_NOT_CHECKED' => 'U',
//         default => 'U',
//     };
// }

// /**
//  * Remove null values from array recursively
//  */
// private function removeNullValues($array)
// {
//     foreach ($array as $key => $value) {
//         if (is_array($value)) {
//             $array[$key] = $this->removeNullValues($value);
//         } elseif (is_null($value)) {
//             unset($array[$key]);
//         }
//     }
//     return $array;
// }


/**
 * Get card type from BIN
 */
private function getCardTypeFromBin($bin)
{
    $firstDigit = substr($bin, 0, 1);
    $firstTwo = substr($bin, 0, 2);

    if ($firstDigit == '4') {
        return 'Visa';
    } elseif (in_array($firstTwo, ['51', '52', '53', '54', '55']) || ($firstTwo >= '22' && $firstTwo <= '27')) {
        return 'MasterCard';
    } elseif (in_array($firstTwo, ['34', '37'])) {
        return 'American Express';
    } elseif ($firstTwo == '60') {
        return 'Discover';
    }

    return 'Unknown';
}



/**
 * @OA\Post(
 *     path="/api/nofraud/process/{order_id}",
 *     operationId="processNoFraud",
 *     tags={"NoFraud"},
 *     summary="Trigger NoFraud screening for an order",
 *     description="Fetches payment and order details (including customer and address relations) and sends the data to the NoFraud API for screening.",
 *
 *     @OA\Parameter(
 *         name="order_id",
 *         in="path",
 *         required=true,
 *         description="Unique order ID to process the NoFraud screening for",
 *         @OA\Schema(type="integer", example=123)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Successful NoFraud screening response",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="decision", type="string", example="pass"),
 *             @OA\Property(property="score", type="integer", example=82),
 *             @OA\Property(
 *                 property="nofraud_result",
 *                 type="object",
 *                 description="Raw NoFraud API response"
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Payment or order not found",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Payment not found")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Unexpected server error or NoFraud API failure",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Error processing NoFraud screening"),
 *             @OA\Property(property="error", type="string", example="Exception message")
 *         )
 *     ),
 *     security={{"bearerAuth": {}}}
 * )
 */
// public function processNoFraud($orderId)
// {
//     try {
//         // Load related models
//         $payment = PaymentManagement::with([
//             'order.customer',
//             'order.customerAddress'
//         ])->where('order_id', $orderId)->first();

//         if (!$payment || !$payment->order) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Payment or order not found'
//             ], 404);
//         }

//         $order = $payment->order;
//         $customer = $order->customer;
//         $address = $order->customerAddress;

//         if (!$customer || !$address) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Missing customer or address information'
//             ], 400);
//         }

//         // ✅ Split full name
//         $nameParts = explode(' ', trim($customer->name ?? 'Unknown'), 2);
//         $firstName = $nameParts[0] ?? 'Unknown';
//         $lastName = $nameParts[1] ?? '';

//         // ✅ Decode card meta safely
//         $metaRaw = $payment->payment_details ?? $payment->meta ?? '{}';
//         $meta = json_decode($metaRaw, true);
//         if (is_string($meta)) {
//             $meta = json_decode($meta, true);
//         }

//         $cardLast4 = $meta['card_last_four'] ?? $payment->card_last4 ?? null;
//         $cardBin   = $meta['meta']['cardDisplay'] ?? $payment->card_bin ?? null;
//         $cardType  = $meta['card_type'] ?? $payment->card_type ?? null;
//         $cardExp   = $meta['card_exp'] ?? $payment->card_exp ?? null;

//         if (!$cardLast4) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Missing card information (last4)',
//                 'raw_meta' => $meta,
//             ], 400);
//         }

//         // ✅ Use the already-loaded order (no need to re-query)
//         $orderNumber = $order->order_number ?? $orderId;

//         // ✅ Prepare data for NoFraud API
//         $requestData = [
//             // 'order_id' => $orderId, // still used for internal handling
//             'order_id' => $orderNumber, // for saving to NoFraudResponse table
//             'amount' => $payment->amount ?? $order->amount ?? 0,

//             'billing_first_name' => $firstName,
//             'billing_last_name' => $lastName,
//             'billing_email' => $customer->email ?? 'noemail@example.com',
//             'billing_phone' => $customer->phone ?? null,
//             'billing_address' => $address->address ?? '',
//             'billing_city' => $address->city ?? '',
//             'billing_state' => $address->state ?? '',
//             'billing_zip' => $address->zip_code ?? '',
//             'billing_country' => substr($address->country ?? 'US', 0, 2),

//             'card_bin' => $cardBin,
//             'card_last4' => $cardLast4,
//             'card_type' => $cardType,
//             'card_expiration' => $cardExp,
//         ];

//         // ✅ Create a fake request instance to reuse screenTransaction
//         $req = new \Illuminate\Http\Request($requestData);

//         // ✅ Call the same controller method with correct context
//         return $this->screenTransaction($req);

//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Error processing NoFraud screening',
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// }

public function processNoFraud($orderId)
{
    try {
        // Load related models
        $payment = PaymentManagement::with([
            'order.customer',
            'order.customerAddress'
        ])->where('order_id', $orderId)->first();

        if (!$payment || !$payment->order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment or order not found'
            ], 404);
        }

        $order = $payment->order;
        $customer = $order->customer;
        $address = $order->customerAddress;

        if (!$customer || !$address) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing customer or address information'
            ], 400);
        }

        // ✅ Split customer name into first/last
        $nameParts = explode(' ', trim($customer->name ?? 'Unknown'), 2);
        $firstName = $nameParts[0] ?? 'Unknown';
        $lastName  = $nameParts[1] ?? '';

        // ✅ Decode payment details safely (may come double-encoded or raw array)
        $metaRaw = $payment->payment_details ?? $payment->meta ?? '{}';
        $meta = is_string($metaRaw) ? json_decode($metaRaw, true) : $metaRaw;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }

        // ✅ Attempt to extract card details from multiple possible formats
        $cardData = $meta['card'] ?? $meta['payment_method_details']['card'] ?? [];

        $cardLast4 = $cardData['last4']
            ?? $meta['card_last_four']
            ?? $meta['last4']
            ?? $payment->card_last4
            ?? null;

        $cardBin = $cardData['bin']
            ?? $meta['meta']['cardDisplay']
            ?? $meta['card_bin']
            ?? $payment->card_bin
            ?? null;

        $cardType = $cardData['brand']
            ?? $cardData['display_brand']
            ?? $meta['card_type']
            ?? $payment->card_type
            ?? null;

        // Combine expiration if available
        $expMonth = $cardData['exp_month'] ?? $meta['exp_month'] ?? null;
        $expYear  = $cardData['exp_year'] ?? $meta['exp_year'] ?? null;
        $cardExp  = $expMonth && $expYear ? "$expMonth/$expYear" : ($meta['card_exp'] ?? null);

        if (!$cardLast4) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing card information (last4)',
                'raw_meta' => $meta,
            ], 400);
        }

        // ✅ Prepare order and billing info
        $orderNumber = $order->order_number ?? $orderId;

        $requestData = [
            'order_id' => $orderNumber,
            'amount' => $payment->amount ?? $order->amount ?? 0,

            'billing_first_name' => $firstName,
            'billing_last_name' => $lastName,
            'billing_email' => $customer->email ?? 'noemail@example.com',
            'billing_phone' => $customer->phone ?? null,
            'billing_address' => $address->address ?? '',
            'billing_city' => $address->city ?? '',
            'billing_state' => $address->state ?? 'NA',
            'billing_zip' => $address->zip_code ?? '',
            'billing_country' => substr($address->country ?? 'US', 0, 2),

            'card_bin' => $cardBin,
            'card_last4' => $cardLast4,
            'card_type' => $cardType,
            'card_expiration' => $cardExp,
        ];

        // ✅ Forward to screenTransaction
        $req = new \Illuminate\Http\Request($requestData);
        return $this->screenTransaction($req);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error processing NoFraud screening',
            'error' => $e->getMessage(),
        ], 500);
    }
}


}
