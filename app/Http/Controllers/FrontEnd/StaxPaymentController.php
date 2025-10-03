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
     *     description="Takes a Stax.js payment token and amount, then processes the charge via Stax API.",
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
     *                 property="customer",
     *                 type="object",
     *                 description="Optional customer information",
     *                 @OA\Property(property="firstname", type="string", example="John"),
     *                 @OA\Property(property="lastname", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 description="Optional metadata",
     *                 @OA\Property(property="order_id", type="string", example="ORD-12345"),
     *                 @OA\Property(property="reference", type="string", example="Invoice #123")
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
            'payment_method_id' => 'required|string',  // Token from Stax.js
            'amount' => 'required|numeric|min:0.01',
            'customer' => 'nullable|array',
            'customer.firstname' => 'nullable|string',
            'customer.lastname' => 'nullable|string',
            'customer.email' => 'nullable|email',
            'customer.phone' => 'nullable|string',
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
            // Prepare charge data
            $chargeData = [
                'amount' => $request->amount,
                'payment_method' => $request->payment_method_id,
                'currency' => 'USD',
            ];

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
                'payment_method_id' => $request->payment_method_id
            ]);

            // Process the charge
            $result = $this->stax->charge($chargeData);

            Log::info('Stax payment successful', ['transaction_id' => $result['id'] ?? null]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'transaction' => $result,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Stax payment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
     *     )
     * )
     */
    public function getTransaction($id)
    {
        try {
            $transaction = $this->stax->getTransaction($id);

            return response()->json([
                'success' => true,
                'transaction' => $transaction,
            ], 200);

        } catch (\Exception $e) {
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
     *             @OA\Property(property="refund", type="object")
     *         )
     *     )
     * )
     */
    public function refund(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $refund = $this->stax->refund($id, [
                'amount' => $request->amount,
                'reason' => $request->reason ?? 'Customer request',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund' => $refund,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}