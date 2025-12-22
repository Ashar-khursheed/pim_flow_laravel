<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\PaymentManagement;
use Illuminate\Http\Request;
use App\Models\FrontEnd\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Jobs\Order\OrderPlacedMailJob;

class PaymentManagementController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/payments",
	 *     summary="Get all payments with search, sort, and pagination",
	 *     tags={"Frontend-Payments"},
	 *     @OA\Parameter(name="search", in="query", description="Search term", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column to sort by", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_order", in="query", description="Sort order (asc or desc)", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Paginated list of payments"),
	 *     security={{"bearerAuth": {}}},
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
	 *     tags={"Frontend-Payments"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"order_id", "payment_mode", "amount", "status", "payment_date"},
	 *             @OA\Property(property="order_id", type="integer", description="ID of the order this payment is for", example=123),
	 *             @OA\Property(property="transaction_id", type="string", description="Unique transaction identifier", example="TXN456789"),
	 *             @OA\Property(property="payment_mode", type="string", description="Method of payment", example="Credit Card"),
	 *             @OA\Property(property="amount", type="number", format="float", description="Payment amount", example=299.99),
	 *             @OA\Property(property="status", type="string", description="Payment status", example="completed"),
	 *             @OA\Property(property="payment_date", type="string", format="date", description="Date when payment was made", example="2024-06-24"),
	 *             @OA\Property(property="notes", type="string", description="Additional notes about the payment", example="First installment paid"),
	 *             @OA\Property(
	 *                 property="payment_details",
	 *                 type="object",
	 *                 description="Additional payment gateway details",
	 *                 example={"bank":"XYZ Bank","ref":"12345XYZ","gateway_response":"success"}
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		try {
			// Validate the incoming request
			$validated = $request->validate([
				'order_id' => 'required|integer|exists:orders,id',
				'transaction_id' => 'nullable|string|max:255|unique:payments_management,transaction_id',
				'payment_mode' => 'required|string|in:Credit Card,Debit Card,PayPal,Bank Transfer,Cash on Delivery,Stripe,Razorpay,Paymob,Stax,Square,CC Avenue,NetTerm,Check,Cheque',
				'amount' => 'required|numeric|min:0.01|max:999999.99',
				'status' => 'required|string|in:Pending,Completed,Failed,Cancelled,Refunded',
				'payment_date' => 'required|date|before_or_equal:today',
				'notes' => 'nullable|string|max:1000',
				'payment_details' => 'nullable|json|max:2000',
				'payment_method' => 'nullable|string|max:255'
			]);

			if (!auth()->check()) {
				return response()->json([
					'message' => 'Authentication required.'
				], 401);
			}

			$order = Order::where('id', $request->order_id)->first();
			if (isset($validated['payment_details'])) {
				$validated['payment_details'] = json_encode($validated['payment_details']);
			}
			$validated['order_id'] = $order->id;
			$validated['created_by'] = auth()->id();
			$validated['rider_name'] = $request->rider_name;

			$total_amount = $order->total_amount;
			if ($total_amount < $request->amount) {
				return response()->json([
					'success' => false,
					'message' => 'Paid amount is greater than total amount ' . $total_amount,
				], 401);
			}

			// Upload payment image if available
			$validated['payment_img'] = uploadImageToWebpS3FromFile(
				$request,
				'payment_img',
				env('STORAGE_ENV') . '/customer/payment'
			);

			DB::beginTransaction();

			// Create the payment record
			$payment = PaymentManagement::create($validated);

			/* Update order amounts */
			$newPaidAmount = $order->paid_amount + $request->amount;
			$pendingAmount = $order->total_amount - $newPaidAmount;

			$order->update([
				'paid_amount' => $newPaidAmount,
				'pending_amount' => $pendingAmount,
				'is_paid' => $pendingAmount <= 0,
			]);

			// ✅ If full amount is paid, release reservation
			if ($pendingAmount <= 0) {
				$order->update(['is_reserved' => 0]);

				// ✅ Send email when payment completed
				$batch = Bus::batch([])->name("Order Placed by Customer (Paid) - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_PLC';
				$batch->add(new OrderPlacedMailJob([
					'recordId' => $order->id
				]));
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Payment recorded successfully.',
				'data' => $payment
			], 201);

		} catch (ValidationException $e) {
			return response()->json([
				'success' => false,
				'message' => 'The given data was invalid.',
				'errors' => $e->errors()
			], 422);

		} catch (\Exception $e) {
			DB::rollBack();
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
	 *     tags={"Frontend-Payments"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Payment ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
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
	 *     tags={"Frontend-Payments"},
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
			'payment_method' => 'nullable|string'
		]);

		$payment->update($validated);

		return response()->json($payment);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/frontend/payments/{id}",
	 *     summary="Delete a payment",
	 *     tags={"Frontend-Payments"},
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
