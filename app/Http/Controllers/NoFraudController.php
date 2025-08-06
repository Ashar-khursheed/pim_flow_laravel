<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\NoFraudResponse;

class NoFraudController extends Controller
{
 /**
 * @OA\Post(
 *     path="/api/screen-transaction",
 *     operationId="screenTransaction",
 *     tags={"NoFraud"},
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
 *             @OA\Property(property="billing_name", type="string", example="John Doe"),
 *             @OA\Property(property="billing_email", type="string", format="email", example="john@example.com"),
 *             @OA\Property(property="billing_phone", type="string", example="1234567890"),
 *             @OA\Property(property="billing_address", type="string", example="123 Main St"),
 *             @OA\Property(property="billing_city", type="string", example="New York"),
 *             @OA\Property(property="billing_state", type="string", example="NY"),
 *             @OA\Property(property="billing_zip", type="string", example="10001"),
 *             @OA\Property(property="billing_country", type="string", example="US"),
 *             
 *             @OA\Property(property="shipping_name", type="string", example="John Doe"),
 *             @OA\Property(property="shipping_address", type="string", example="123 Main St"),
 *             @OA\Property(property="shipping_city", type="string", example="New York"),
 *             @OA\Property(property="shipping_state", type="string", example="NY"),
 *             @OA\Property(property="shipping_zip", type="string", example="10001"),
 *             @OA\Property(property="shipping_country", type="string", example="US"),
 *             
 *             @OA\Property(property="card_bin", type="string", example="411111"),
 *             @OA\Property(property="card_last4", type="string", example="4242")
 *         )
 *     ),
 *     @OA\Parameter(
 *         name="X-Nofraud-Api-Key",
 *         in="header",
 *         required=true,
 *         description="Your NoFraud live API key",
 *         @OA\Schema(type="string", example="your_live_api_key_here")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Transaction screened successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="nofraud_result", type="object")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid request"
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="NoFraud API error"
 *     )
 * )
 */

public function screenTransaction(Request $request)
{
    $payload = [
        'nf-token' => env('NOFRAUD_API_KEY'),
        'amount' => $request->amount ?? 49.99,
        'currencyCode' => 'USD',
        'customerIP' => $request->ip(),

        'billTo' => [
            'firstName' => $request->billing_first_name ?? 'John',
            'lastName' => $request->billing_last_name ?? 'Doe',
            'company' => $request->billing_company ?? 'ACME Inc.',
            'address' => $request->billing_address ?? '123 Main St',
            'city' => $request->billing_city ?? 'New York',
            'state' => $request->billing_state ?? 'NY',
            'zip' => $request->billing_zip ?? '10001',
            'country' => $request->billing_country ?? 'US',
            'phoneNumber' => $request->billing_phone ?? '1234567890',
        ],

        'shipTo' => [
            'firstName' => $request->shipping_first_name ?? 'John',
            'lastName' => $request->shipping_last_name ?? 'Doe',
            'company' => $request->shipping_company ?? 'ACME Inc.',
            'address' => $request->shipping_address ?? '456 Another St',
            'city' => $request->shipping_city ?? 'Los Angeles',
            'state' => $request->shipping_state ?? 'CA',
            'zip' => $request->shipping_zip ?? '90001',
            'country' => $request->shipping_country ?? 'US',
        ],

        'payment' => [
            'method' => 'Credit Card',
            'creditCard' => [
                'last4' => $request->card_last4 ?? '4242',
                'bin' => $request->card_bin ?? '411111',
                'cardType' => 'Visa',
                'expirationDate' => '0925',
                'cardNumber' => '4111111111111111', // optional, only if allowed
                'cardCode' => '999',
            ],
        ],

        'order' => [
            'invoiceNumber' => $request->order_id ?? 'ORD-' . time(),
            'orderType' => 'one-time',
        ],

        'customer' => [
            'id' => 'user-123',
            'email' => $request->billing_email ?? 'john@example.com',
            'joinedOn' => now()->subMonths(6)->format('m/d/Y'),
            'lastSignIn' => now()->format('m/d/Y'),
            'lastPurchaseDate' => now()->subDays(5)->format('m/d/Y'),
            'totalPreviousPurchases' => 3,
            'totalPurchaseValue' => 1200.00,
        ],
    ];

    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post(env('NOFRAUD_API_URL'), $payload);

    if ($response->successful()) {
        $result = $response->json();

        NoFraudResponse::create([
            'order_id' => $payload['order']['invoiceNumber'],
            'response' => json_encode($result),
        ]);

        return response()->json([
            'status' => 'success',
            'nofraud_result' => $result,
        ]);
    }

    return response()->json([
        'status' => 'error',
        'message' => 'NoFraud API request failed.',
        'details' => $response->body(),
    ], $response->status());
}



}
