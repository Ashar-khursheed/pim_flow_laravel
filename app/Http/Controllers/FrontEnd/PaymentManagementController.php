<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\PaymentManagement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;


class PaymentManagementController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/payments",
     *     summary="Get all payments with search, sort, and pagination",
     *     tags={"Frontend-Payment History"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="search", in="query", description="Search term", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_by", in="query", description="Column to sort by", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_order", in="query", description="Sort order (asc or desc)", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Paginated list of payments")
     * )
     */
    public function index(Request $request)
    {
        $query = PaymentManagement::query();

        // Search logic
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%$search%")
                ->orWhere('transaction_id', 'like', "%$search%")
                ->orWhere('payment_mode', 'like', "%$search%")
                ->orWhere('status', 'like', "%$search%");
            });
        }

        // Sorting logic
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination logic
        $perPage = $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        return response()->json($payments);
    }


     /**
     * @OA\Post(
     *     path="/api/frontend/payments",
     *     summary="Create a new payment",
     *     description="Create a new payment record for an authenticated customer",
     *     operationId="createPayment",
     *     tags={"Frontend-Payment History"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Payment data",
     *         @OA\JsonContent(
     *             required={"order_id", "payment_mode", "amount", "status", "payment_date"},
     *             @OA\Property(
     *                 property="order_id", 
     *                 type="integer", 
     *                 description="ID of the order this payment is for",
     *                 example=123
     *             ),
     *             @OA\Property(
     *                 property="transaction_id", 
     *                 type="string", 
     *                 description="Unique transaction identifier from payment gateway",
     *                 example="TXN456789"
     *             ),
     *             @OA\Property(
     *                 property="payment_mode", 
     *                 type="string", 
     *                 description="Method of payment",
     *                 example="Credit Card",
     *                 enum={"Credit Card", "Debit Card", "PayPal", "Bank Transfer", "Cash", "Stripe", "Razorpay"}
     *             ),
     *             @OA\Property(
     *                 property="amount", 
     *                 type="number", 
     *                 format="float", 
     *                 description="Payment amount",
     *                 example=299.99,
     *                 minimum=0.01
     *             ),
     *             @OA\Property(
     *                 property="status", 
     *                 type="string", 
     *                 description="Payment status",
     *                 example="completed",
     *                 enum={"pending", "completed", "failed", "cancelled", "refunded"}
     *             ),
     *             @OA\Property(
     *                 property="payment_date", 
     *                 type="string", 
     *                 format="date", 
     *                 description="Date when payment was made",
     *                 example="2024-06-24"
     *             ),
     *             @OA\Property(
     *                 property="notes", 
     *                 type="string", 
     *                 description="Additional notes about the payment",
     *                 example="First installment paid",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="payment_details", 
     *                 type="object", 
     *                 description="Additional payment gateway details",
     *                 example={"bank":"XYZ Bank","ref":"12345XYZ","gateway_response":"success"},
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="order_id", type="integer", example=123),
     *             @OA\Property(property="customer_id", type="integer", example=45),
     *             @OA\Property(property="transaction_id", type="string", example="TXN456789"),
     *             @OA\Property(property="payment_mode", type="string", example="Credit Card"),
     *             @OA\Property(property="amount", type="number", format="float", example=299.99),
     *             @OA\Property(property="status", type="string", example="completed"),
     *             @OA\Property(property="payment_date", type="string", format="date", example="2024-06-24"),
     *             @OA\Property(property="notes", type="string", example="First installment paid"),
     *             @OA\Property(property="payment_details", type="object", example={"bank":"XYZ Bank","ref":"12345XYZ"}),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2024-06-24T10:30:00.000000Z"),
     *             @OA\Property(property="updated_at", type="string", format="date-time", example="2024-06-24T10:30:00.000000Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Validation errors",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors", 
     *                 type="object",
     *                 example={
     *                     "order_id": {"The order id field is required."},
     *                     "status": {"The status field is required."},
     *                     "payment_date": {"The payment date field is required."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity - Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Something went wrong while creating the payment."),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate the incoming request
            $validated = $request->validate([
                'order_id' => 'required|integer|exists:orders,id', // Ensure order exists
                'transaction_id' => 'nullable|string|max:255|unique:payment_managements,transaction_id', // Ensure unique transaction
                'payment_mode' => 'required|string|in:Credit Card,Debit Card,PayPal,Bank Transfer,Cash,Stripe,Razorpay',
                'amount' => 'required|numeric|min:0.01|max:999999.99',
                'status' => 'required|string|in:pending,completed,failed,cancelled,refunded',
                'payment_date' => 'required|date|before_or_equal:today',
                'notes' => 'nullable|string|max:1000',
                'payment_details' => 'nullable|json|max:2000',
            ]);

            // Add authenticated user ID (assumes customer authentication)
            if (!auth()->check()) {
                return response()->json([
                    'message' => 'Authentication required.'
                ], 401);
            }

            $validated['customer_id'] = auth()->id();

            // Create the payment record
            $payment = PaymentManagement::create($validated);

            // Return success response with 201 status
            return response()->json([
                'message' => 'Payment created successfully.',
                'data' => $payment
            ], 201);

        } catch (ValidationException $e) {
            // Handle validation errors
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            // Handle any other errors
            return response()->json([
                'message' => 'Something went wrong while creating the payment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/frontend/payments/{id}",
     *     summary="Get a single payment",
     *     tags={"Frontend-Payment History"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Payment details"),
     *     @OA\Response(response=404, description="Payment not found")
     * )
     */
    public function show($id)
    {
        $payment = PaymentManagement::findOrFail($id);
        return response()->json($payment);
    }

    /**
     * @OA\Put(
     *     path="/api/frontend/payments/{id}",
     *     summary="Update a payment",
     *     tags={"Frontend-Payment History"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="order_id", type="integer", example=123),
     *             @OA\Property(property="transaction_id", type="string", example="TXN456789"),
     *             @OA\Property(property="payment_mode", type="string", example="PayPal"),
     *             @OA\Property(property="amount", type="number", format="float", example=199.99),
     *             @OA\Property(property="status", type="string", example="pending"),
     *             @OA\Property(property="payment_date", type="string", format="date", example="2024-06-24"),
     *             @OA\Property(property="notes", type="string", example="Awaiting confirmation"),
     *             @OA\Property(property="payment_details", type="object", example={"paypal_email":"user@example.com"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payment updated"),
     *     @OA\Response(response=404, description="Payment not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $payment = PaymentManagement::findOrFail($id);

        $validated = $request->validate([
            'order_id' => 'sometimes|required|integer',
            'transaction_id' => 'nullable|string',
            'payment_mode' => 'sometimes|required|string',
            'amount' => 'sometimes|required|numeric',
            'status' => 'sometimes|required|string',
            'payment_date' => 'sometimes|required|date',
            'notes' => 'nullable|string',
            'payment_details' => 'nullable|json',
        ]);

        $payment->update($validated);

        return response()->json($payment);
    }

    /**
     * @OA\Delete(
     *     path="/api/frontend/payments/{id}",
     *     summary="Delete a payment",
     *     tags={"Frontend-Payment History"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Payment deleted"),
     *     @OA\Response(response=404, description="Payment not found")
     * )
     */
    public function destroy($id)
    {
        $payment = PaymentManagement::findOrFail($id);
        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }
}
