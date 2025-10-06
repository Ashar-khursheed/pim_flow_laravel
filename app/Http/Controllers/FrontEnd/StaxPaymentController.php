<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\StaxService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StaxPaymentController extends Controller
{
    protected $stax;

    public function __construct(StaxService $stax)
    {
        $this->stax = $stax;
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/auth/Stax",
     *     tags={"Payments"},
     *     summary="Process a checkout payment",
     *     description="Takes a Stax.js payment method ID and amount, then processes the charge via Stax API.",
     *     operationId="checkout",
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_method_id","amount"},
     *             @OA\Property(
     *                 property="payment_method_id", 
     *                 type="string", 
     *                 example="pm_abc123XYZ", 
     *                 description="Payment method ID from Stax.js tokenization"
     *             ),
     *             @OA\Property(
     *                 property="amount", 
     *                 type="number", 
     *                 format="float", 
     *                 example=100.50, 
     *                 description="Charge amount in USD"
     *             ),
     *             @OA\Property(
     *                 property="pre_auth",
     *                 type="boolean",
     *                 example=false,
     *                 description="Set to true for pre-authorization (optional)"
     *             ),
     *             @OA\Property(
     *                 property="customer",
     *                 type="object",
     *                 description="Optional customer information",
     *                 @OA\Property(property="firstname", type="string", example="John"),
     *                 @OA\Property(property="lastname", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="address_1", type="string", example="123 Main St"),
     *                 @OA\Property(property="address_city", type="string", example="New York"),
     *                 @OA\Property(property="address_state", type="string", example="NY"),
     *                 @OA\Property(property="address_zip", type="string", example="10001"),
     *                 @OA\Property(property="address_country", type="string", example="USA")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 description="Optional metadata",
     *                 @OA\Property(property="order_id", type="string", example="ORD-12345"),
     *                 @OA\Property(property="reference", type="string", example="Invoice #123"),
     *                 @OA\Property(property="tax", type="number", example=5.50),
     *                 @OA\Property(property="subtotal", type="number", example=95.00)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful transaction",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment processed successfully"),
     *             @OA\Property(
     *                 property="transaction",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="txn_12345"),
     *                 @OA\Property(property="total", type="number", example=100.50),
     *                 @OA\Property(property="status", type="string", example="completed"),
     *                 @OA\Property(property="payment_method_id", type="string", example="pm_abc123")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="payment_method_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The payment method id field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed transaction",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Payment processing failed")
     *         )
     *     )
     * )
     */
    public function checkout(Request $request)
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'pre_auth' => 'nullable|boolean',
            'customer' => 'nullable|array',
            'customer.firstname' => 'nullable|string',
            'customer.lastname' => 'nullable|string',
            'customer.email' => 'nullable|email',
            'customer.phone' => 'nullable|string',
            'customer.address_1' => 'nullable|string',
            'customer.address_city' => 'nullable|string',
            'customer.address_state' => 'nullable|string',
            'customer.address_zip' => 'nullable|string',
            'customer.address_country' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Prepare charge data in the format expected by StaxService
            $chargeData = [
                'amount' => $request->amount,
                'payment_method' => $request->payment_method_id,
            ];

            // Add pre_auth if provided
            if ($request->has('pre_auth')) {
                $chargeData['pre_auth'] = $request->pre_auth;
            }

            // Add customer info if provided
            if ($request->has('customer')) {
                $chargeData['customer'] = $request->customer;
            }

            // Add metadata if provided
            if ($request->has('meta')) {
                $chargeData['meta'] = $request->meta;
            }

            Log::info('Processing Stax payment', [
                'amount' => $request->amount,
                'payment_method_id' => $request->payment_method_id,
                'has_customer' => $request->has('customer'),
                'has_meta' => $request->has('meta'),
            ]);

            // Process the charge through StaxService
            $result = $this->stax->charge($chargeData);

            Log::info('Stax payment successful', [
                'transaction_id' => $result['id'] ?? null,
                'status' => $result['status'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'transaction' => $result,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Stax payment failed', [
                'error' => $e->getMessage(),
                'payment_method_id' => $request->payment_method_id ?? null,
                'amount' => $request->amount ?? null,
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transaction details
     * 
     * @OA\Get(
     *     path="/api/frontend/auth/Stax/transaction/{id}",
     *     tags={"Payments"},
     *     summary="Get transaction details",
     *     operationId="getTransaction",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Transaction ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="transaction", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving transaction",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getTransaction($id)
    {
        try {
            Log::info('Fetching Stax transaction', ['transaction_id' => $id]);

            $transaction = $this->stax->getTransaction($id);

            return response()->json([
                'success' => true,
                'transaction' => $transaction,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to fetch Stax transaction', [
                'transaction_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refund a transaction
     * 
     * @OA\Post(
     *     path="/api/frontend/auth/Stax/refund/{id}",
     *     tags={"Payments"},
     *     summary="Refund a transaction",
     *     operationId="refundTransaction",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Transaction ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", example=50.00),
     *             @OA\Property(property="reason", type="string", example="Customer request")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Refund processed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Refund processed successfully"),
     *             @OA\Property(property="refund", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Refund failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function refund(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            Log::info('Processing Stax refund', [
                'transaction_id' => $id,
                'amount' => $request->amount,
                'reason' => $request->reason,
            ]);

            $refund = $this->stax->refund($id, [
                'amount' => $request->amount,
                'reason' => $request->reason ?? 'Customer request',
            ]);

            Log::info('Stax refund successful', [
                'transaction_id' => $id,
                'refund_id' => $refund['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund' => $refund,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Stax refund failed', [
                'transaction_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void a transaction
     * 
     * @OA\Post(
     *     path="/api/frontend/auth/Stax/void/{id}",
     *     tags={"Payments"},
     *     summary="Void a transaction",
     *     operationId="voidTransaction",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Transaction ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction voided",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transaction voided successfully"),
     *             @OA\Property(property="void", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Void failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function void($id)
    {
        try {
            Log::info('Voiding Stax transaction', ['transaction_id' => $id]);

            $void = $this->stax->void($id);

            Log::info('Stax transaction voided', [
                'transaction_id' => $id,
                'void_id' => $void['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction voided successfully',
                'void' => $void,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Stax void failed', [
                'transaction_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/auth/Stax/tokenize",
     *     summary="Tokenize a card and get a payment method token",
     *     description="This endpoint tokenizes a customer's card details using the Stax API and returns a payment method ID (token) that can be used for future charges.",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_number","exp_month","exp_year","cvc"},
     *             @OA\Property(property="card_number", type="string", example="4242424242424242", description="Customer's card number"),
     *             @OA\Property(property="exp_month", type="string", example="12", description="Card expiration month (MM)"),
     *             @OA\Property(property="exp_year", type="string", example="2028", description="Card expiration year (YYYY)"),
     *             @OA\Property(property="cvc", type="string", example="123", description="Card CVV code"),
     *             @OA\Property(property="billing_email", type="string", example="customer@example.com", description="Billing email (optional)"),
     *             @OA\Property(property="billing_name", type="string", example="John Doe", description="Cardholder name (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Card successfully tokenized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="payment_method_id", type="string", example="pm_1234567890abcdef"),
     *             @OA\Property(property="raw", type="object", description="Raw Stax API response (optional, for debugging)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input or tokenization failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="The card number is invalid")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Failed to connect to Stax API")
     *         )
     *     )
     * )
     */
    /**
     * Tokenize card and return payment method id (token)
     * POST /api/frontend/auth/Stax/tokenize
     */
    public function tokenizeCard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_number' => 'required|string',
            'exp_month'   => 'required|string',
            'exp_year'    => 'required|string',
            'cvc'         => 'required|string',
            'billing_email' => 'nullable|email',
            'billing_name'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $apiKey = env('STAX_API_KEY');
            $baseUrl = rtrim(env('STAX_BASE_URL', 'https://apiprod.fattlabs.com'), '/');
            // Choose the tokenization endpoint your Stax instance expects.
            // Many integrations accept: /paymentmethod  OR  /v1/paymentmethod
            $url = $baseUrl . '/paymentmethod';

            $payload = [
                'type' => 'card', // explicit type
                'card' => [
                    'number' => $request->card_number,
                    'exp_month' => $request->exp_month,
                    'exp_year' => $request->exp_year,
                    'cvc' => $request->cvc,
                ],
                'meta' => [
                    'email' => $request->billing_email,
                    'name'  => $request->billing_name,
                ],
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$apiKey}",
                    "Content-Type: application/json",
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
            ]);

            $response = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                throw new \Exception('cURL error: ' . $curlErr);
            }

            $data = json_decode($response, true);

            // If API returns errors in the body
            if (isset($data['error']) || isset($data['errors'])) {
                // Log raw for debugging
                Log::error('Stax tokenization failed', ['response' => $data]);
                $msg = $data['error']['message'] ?? json_encode($data['errors'] ?? $data);
                return response()->json(['success' => false, 'error' => $msg], 400);
            }

            // The token is usually in data.id or id field depending on API
            $token = $data['data']['id'] ?? $data['id'] ?? null;

            if (! $token) {
                Log::error('Stax tokenization unexpected response', ['response' => $data]);
                return response()->json(['success' => false, 'error' => 'Token not returned by Stax'], 500);
            }

            // Return token (payment method id)
            return response()->json([
                'success' => true,
                'payment_method_id' => $token,
                'raw' => $data, // optional: remove in production
            ], 200);

        } catch (\Exception $e) {
            Log::error('Stax tokenize error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }



}