<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentManagement;

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
     *     tags={"Frontend-Payment History"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id", "payment_mode", "amount", "status", "payment_date"},
     *             @OA\Property(property="order_id", type="integer", example=123),
     *             @OA\Property(property="transaction_id", type="string", example="TXN456789"),
     *             @OA\Property(property="payment_mode", type="string", example="Credit Card"),
     *             @OA\Property(property="amount", type="number", format="float", example=299.99),
     *             @OA\Property(property="status", type="string", example="completed"),
     *             @OA\Property(property="payment_date", type="string", format="date", example="2024-06-24"),
     *             @OA\Property(property="notes", type="string", example="First installment paid"),
     *             @OA\Property(property="payment_details", type="object", example={"bank":"XYZ Bank","ref":"12345XYZ"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Payment created")
     * )
     */
   /**
 * @OA\Post(
 *     path="/api/frontend/payments",
 *     summary="Create a new payment",
 *     tags={"Frontend-Payment History"},
 *     security={{"bearerAuth": {}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"order_id", "payment_mode", "amount", "status", "payment_date"},
 *             @OA\Property(property="order_id", type="integer", example=123),
 *             @OA\Property(property="transaction_id", type="string", example="TXN456789"),
 *             @OA\Property(property="payment_mode", type="string", example="Credit Card"),
 *             @OA\Property(property="amount", type="number", format="float", example=299.99),
 *             @OA\Property(property="status", type="string", example="completed"),
 *             @OA\Property(property="payment_date", type="string", format="date", example="2024-06-24"),
 *             @OA\Property(property="notes", type="string", example="First installment paid"),
 *             @OA\Property(property="payment_details", type="object", example={"bank":"XYZ Bank","ref":"12345XYZ"})
 *         )
 *     ),
 *     @OA\Response(response=201, description="Payment created")
 * )
 */
public function store(Request $request)
{
    $validated = $request->validate([
        'order_id' => 'required|integer',
        'transaction_id' => 'nullable|string',
        'payment_mode' => 'required|string',
        'amount' => 'required|numeric',
        'status' => 'required|string',
        'payment_date' => 'required|date',
        'notes' => 'nullable|string',
        'payment_details' => 'nullable|json',
    ]);

    // Add authenticated user ID (assumes customer authentication)
    $validated['customer_id'] = auth()->id();

    $payment = PaymentManagement::create($validated);

    return response()->json($payment, 201);
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
