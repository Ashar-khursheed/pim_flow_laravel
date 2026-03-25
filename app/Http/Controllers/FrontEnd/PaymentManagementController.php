<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\PaymentManagement;
use Illuminate\Http\Request;
use App\Models\FrontEnd\Order;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Jobs\Order\OrderPlacedMailJob;
use App\Helpers\CurrencyConverter;

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
	 *             @OA\Property(property="currency", type="string", example="USD"),
	 *             @OA\Property(property="order_id", type="integer", example=123),
	 *             @OA\Property(property="transaction_id", type="string", example="TXN456789"),
	 *             @OA\Property(property="payment_mode", type="string", example="Credit Card"),
	 *             @OA\Property(property="amount", type="number", format="float", example=299.99),
	 *             @OA\Property(property="status", type="string", example="completed"),
	 *             @OA\Property(property="payment_date", type="string", format="date", example="2024-06-24"),
	 *             @OA\Property(property="notes", type="string", example="First installment paid"),
	 *             @OA\Property(property="payment_method", type="string", example="stripe"),
	 *             @OA\Property(property="payment_img", type="string", format="binary"),
	 *             @OA\Property(property="payment_details", type="object", example={"bank":"XYZ Bank","ref":"12345XYZ"})
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		try {
			/* Validate incoming request */
			$validated = $request->validate([
				'currency' => 'required|string|exists:currencies,title',
				'order_id' => 'required|integer|exists:orders,id',
				'transaction_id' => 'nullable|string|max:255|unique:payments_management,transaction_id',
				'payment_mode' => 'required|string|in:Credit Card,Debit Card,PayPal,Bank Transfer,Cash on Delivery,Stripe,Razorpay,Paymob,Stax,Square,CC Avenue,NetTerm,Check,Cheque,Ascentium Capital,Resolve Pay,Approve',
				'amount' => 'required|numeric|min:0.01|max:999999.99',
				'status' => 'required|string|in:Pending,Completed,Failed,Cancelled,Refunded',
				'payment_date' => 'required|date|before_or_equal:today',
				'notes' => 'nullable|string|max:1000',
				'payment_details' => 'nullable|json|max:2000',
				'payment_method' => 'nullable|string|max:255',
			]);

			$order = Order::find($request->order_id);

			/* Convert paid amount to base currency */
			$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']);
			$baseCurrencyTitle = $isUAE ? 'AED' : 'USD';
			$paidAmountInBase = CurrencyConverter::convertCurrency($request->currency, $baseCurrencyTitle, $request->amount) ?? 0;

			/* Validate paid amount does not exceed order total */
			$totalAmount = $order->total_amount;
			if ($totalAmount < $paidAmountInBase) {
				return response()->json([
					'success' => false,
					'message' => 'Paid amount is greater than total amount ' . $totalAmount,
				], 401);
			}

			/* Merge original currency and amount into payment_details before saving */
			$existingDetails = isset($validated['payment_details']) ? json_decode($validated['payment_details'], true) : [];
			$existingDetails['original_currency'] = $request->currency;
			$existingDetails['original_amount'] = $request->amount;
			$validated['payment_details'] = json_encode($existingDetails);

			/* Override amount with base currency value and remove currency field */
			$validated['amount'] = $paidAmountInBase;
			unset($validated['currency']);

			/* Assign meta fields */
			$validated['order_id'] = $order->id;
			$validated['created_by'] = auth()->id();
			$validated['rider_name'] = $request->rider_name;

			/* Upload payment image if provided */
			$validated['payment_img'] = uploadImageToWebpS3FromFile(
				$request,
				'payment_img',
				env('STORAGE_ENV') . '/customer/payment'
			);

			DB::beginTransaction();

			/* Create payment record */
			$payment = PaymentManagement::create($validated);

			/* Update order paid and pending amounts */
			$newPaidAmount = $order->paid_amount + $paidAmountInBase;
			$pendingAmount = $totalAmount - $newPaidAmount;

			$order->update([
				'paid_amount' => $newPaidAmount,
				'pending_amount' => $pendingAmount,
				'is_paid' => $pendingAmount <= 0,
			]);

			/* If fully paid — release reservation and dispatch order placed mail */
			if ($pendingAmount <= 0) {
				$order->update(['is_reserved' => 0]);

				$batch = Bus::batch([])->name("Order Placed by Customer (Paid) - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_PLC';
				$batch->add(new OrderPlacedMailJob([
					'recordId' => $order->id,
				]));
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Payment recorded successfully.',
				'data' => $payment,
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();
			return response()->json([
				'success' => false,
				'message' => 'Something went wrong while creating the payment.',
				'error' => $e->getMessage(),
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
