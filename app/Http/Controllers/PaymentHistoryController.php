<?php

namespace App\Http\Controllers;

use Doctrine\Common\Annotations\Annotation\Required;
use Illuminate\Http\Request;
use App\Models\ProductAccessory;
use App\Models\Product;
use App\Models\AccessoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\PaymentManagement;
use App\Models\FrontEnd\Order;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Log;

use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\DB;
use App\Jobs\Order\OrderPlacedMailJob;

class PaymentHistoryController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/delivery/payment-history",
	 *     summary="Get list of delivery payment history",
	 *     tags={"Delivery Payment History"},
	 *     @OA\Parameter(
	 *         name="order_number",
	 *         in="query",
	 *         required=false,
	 *         description="Filter by order number",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="status",
	 *         in="query",
	 *         required=false,
	 *         description="Filter by status",
	 *         @OA\Schema(type="string", enum={"Pending","Completed","Failed","Cancelled","Refunded","all"}, example="all")
	 *     ),
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         required=false,
	 *         description="Search by order id, transaction_id",
	 *         @OA\Schema(type="string", example="")
	 *     ),
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         required=false,
	 *         description="Page number for pagination",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         required=false,
	 *         description="Number of records per page",
	 *         @OA\Schema(type="integer", minimum=1, example=10)
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         required=false,
	 *         description="Column to sort by (id, order_id, status)",
	 *         @OA\Schema(type="string", enum={"id", "order_id", "status"}, example="id")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_direction",
	 *         in="query",
	 *         required=false,
	 *         description="Sort direction (asc or desc)",
	 *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product accessories retrieved successfully"),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{

		$query = PaymentManagement::with(['createdBy', 'updatedBy', 'order']);

		if ($request->filled('order_number')) {
			$orderNumber = $request->input('order_number');


			$query->whereHas('order', function ($q) use ($orderNumber) {
				$q->where('order_number', 'like', "%{$orderNumber}%");
			});
		}


		if ($request->filled('status') && $request->input('status') !== "all") {
			$query->where('status', $request->input('status'));
		}

		if ($request->filled('search')) {
			$search = $request->input('search');

			$query->where(function ($q) use ($search) {

				$q->where('transaction_id', 'like', "%{$search}%")
				->orWhere('payment_method', 'like', "%{$search}%")
				->orWhere('rider_name', 'like', "%{$search}%")
				->orWhere('notes', 'like', "%{$search}%")

				->orWhereHas('order', function ($orderQuery) use ($search) {
					$orderQuery->where('order_number', 'like', "%{$search}%");
				})

				->orWhere(function ($numericQuery) use ($search) {
					if (is_numeric($search)) {
						$numericQuery->where('order_id', $search);
					}
				});
			});
		}


		$searchableColumns = ['id', 'transaction_id', 'payment_method', 'status', 'rider_name'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at', 'payment_date', 'amount']);


		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'created_at';
		$sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

		// Pagination parameters
		$perPage = min($request->get('per_page', 15), 100); // Limit max per_page to 100
		$page = max($request->get('page', 1), 1); // Ensure page is at least 1

		// Get total count BEFORE applying pagination
		$totalRecords = (clone $query)->count();
		$totalPages = $perPage > 0 ? (int) ceil($totalRecords / $perPage) : 1;

		// Adjust page if it exceeds total pages
		if ($page > $totalPages && $totalPages > 0) {
			$page = $totalPages; // Go to last page instead of first page
		}

		// Apply sorting and pagination
		$paymentManagement = $query->orderBy($sortBy, $sortDir)
		->offset(($page - 1) * $perPage)
		->limit($perPage)
		->get();

		// Format the results
		$formattedPayments = $paymentManagement->map(function ($payment) {
			return [
				'id' => $payment->id,
				'payment_method' => $payment->payment_method,
				'order_id' => $payment->order_id,
				'order_number' => $payment->order?->order_number ?? null, // Include order_number
				'transaction_id' => $payment->transaction_id,
				'payment_mode' => $payment->payment_mode,
				'amount' => number_format($payment->amount, 2), // Format amount
				'status' => $payment->status,
				'notes' => $payment->notes,
				'payment_details' => $payment->payment_details ? json_decode($payment->payment_details, true) : null,
				'payment_img' => $payment->payment_img,
				'rider_name' => $payment->rider_name,
				'payment_date' => $payment->payment_date ? date('d-m-Y', strtotime($payment->payment_date)) : null,
				'created_by' => $payment->createdBy?->username ?? null,
				'updated_by' => $payment->updatedBy?->username ?? null,
				'created_at' => date('d-m-Y H:i:s', strtotime($payment->created_at)),
				'updated_at' => date('d-m-Y H:i:s', strtotime($payment->updated_at)),
			];
		});

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_list"),
			'data' => [
				'current_page' => (int) $page,
				'per_page' => (int) $perPage,
				'total_pages' => $totalPages,
				'total_records' => $totalRecords,
				'data' => $formattedPayments,
			]
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/delivery/payment-history",
	 *     summary="Create a new cash delivery payment",
	 *     description="Create a new cash delivery payment record for an authenticated customer",
	 *     operationId="createCashPayment",
	 *     tags={"Delivery Payment History"},
	 *     security={{"bearerAuth": {}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         description="Payment data with optional file attachment",
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"order_number", "payment_mode", "amount", "status", "payment_date"},
	 *                 @OA\Property(property="order_number", type="integer", example=123),
	 *                 @OA\Property(property="transaction_id", type="string", example="TXN456789"),
	 *                 @OA\Property(property="payment_mode", type="string", enum={"Bank Transfer", "Stripe", "Razorpay", "Cash on Delivery", "CC Avenue", "Credit Card", "Debit Card", "Tabby", "Cheque", "Tamara", "Paymob", "COD", "PayPal", "Stax", "Square"}, example="Cash on Delivery"),
	 *                 @OA\Property(property="amount", type="number", format="float", example=299.99),
	 *                 @OA\Property(property="status", type="string", enum={"Pending","Completed","Failed","Cancelled","Refunded"}, example="Completed"),
	 *                 @OA\Property(property="rider_name", type="string", example="Jon Jones"),
	 *                 @OA\Property(property="payment_date", type="string", format="date", example="2024-06-24"),
	 *                 @OA\Property(property="notes", type="string", example="First installment paid"),
	 *                  @OA\Property(
	 *                     property="payment_details",
	 *                     type="object",
	 *                     description="Additional payment gateway details",
	 *                     @OA\Property(property="bank", type="string", example="XYZ Bank"),
	 *                     @OA\Property(property="ref", type="string", example="12345XYZ"),
	 *                     @OA\Property(property="gateway_response", type="string", example="success")
	 *                 ),
	 *                 @OA\Property(
	 *                     property="payment_img",
	 *                     description="Upload receipt or proof of payment",
	 *                     type="string",
	 *                     format="binary"
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Payment created successfully",
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Invalid or missing authentication token"
	 *     )
	 * )
	 */
	public function store(Request $request)
	{
		try {
			// Validate the incoming request
			$validated = $request->validate([
				'order_number' => 'required|integer|exists:orders,order_number',
				'transaction_id' => 'nullable|string',
				'payment_mode' => 'required|string|in:Credit Card,Debit Card,PayPal,Bank Transfer,Cash on Delivery,Stripe,Razorpay,CC Avenue,Paymob,Stax,Square,Tabby,Cheque,Tamara,COD',
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

			$order = Order::where('order_number', $request->order_number)->first();

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

			if (strtolower($request->status) !== 'pending') {
				$newPaidAmount = $order->paid_amount + $request->amount;
				$pendingAmount = $order->total_amount - $newPaidAmount;

				$order->update([
					'paid_amount' => $newPaidAmount,
					'pending_amount' => $pendingAmount,
					'is_paid' => $pendingAmount <= 0,
				]);

				if ($pendingAmount <= 0) {
					Log::channel('testLog')->info("reserve called");

					$order->update(['is_reserved' => 0]);
					// $order->update(['pay_with_cheque' => 0]);

					$batch = Bus::batch([])->name("Order Placed from Backend (Paid) - #{$order->order_number}")->dispatch();
					$batch->options['queue'] = config('app.website') . '_ORD_PLC';
					$batch->add(new OrderPlacedMailJob([
						'recordId' => $order->id
					]));
				}
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
	 *     path="/api/delivery/payment-history/{id}",
	 *     summary="Edit get Payment History",
	 *     tags={"Delivery Payment History"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Delivery Payment History order ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Payment History retrieved successfully"),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Payment History not found"
	 *     ),
	 *      security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		try {

			$paymentManagement = PaymentManagement::with(['createdBy', 'updatedBy'])->where('order_id', $id)->get();

			// Map items properly
			$paymentManagementList = $paymentManagement->map(function ($pyment) {

				return [
					'id' => $pyment->id,
					'payment_method' => $pyment->payment_method,
					'order_number' => $pyment->order_number,
					'transaction_id' => $pyment->transaction_id,
					'payment_mode' => $pyment->payment_mode,
					'amount' => $pyment->amount,
					'status' => $pyment->status,
					'notes' => $pyment->notes,
					'payment_details' => json_decode($pyment->payment_details),
					'payment_img' => $pyment->payment_img,
					'rider_name' => $pyment->rider_name,
					'payment_date' => date('d-m-Y h:i:s', strtotime($pyment->payment_date)),
					'created_by' => $pyment->createdBy?->username ?? null,
					'updated_by' => $pyment->updatedBy?->username ?? null,
					'created_at' => date('d-m-Y', strtotime($pyment->created_at)),
					'updated_at' => date('d-m-Y', strtotime($pyment->updated_at)),

				];
			});


			return response()->json([
				'success' => true,
				'message' => 'Payment History successfully',
				'data' => $paymentManagementList
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Payment History not found',
				'error' => $e->getMessage()
			], 404);
		}

	}

	/**
	 * @OA\Get(
	 *     path="/api/delivery/get-price-ordernumber",
	 *     summary="Get price by order number",
	 *     tags={"Delivery Payment History"},
	 *     @OA\Parameter(
	 *         name="order_number",
	 *         in="query",
	 *         required=false,
	 *         description="Filter by order number",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product accessories retrieved successfully"),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getPriceOrderNumber(Request $request)
	{
		$validated = $request->validate([
			'order_number' => 'required|integer|exists:orders,order_number',
		]);
		$order = Order::where('order_number', $request->order_number)->first();
		if (!$order) {
			return response()->json([
				'success' => true,
				'message' => 'the order number does not exist',

			], 201);
		}
		return response()->json([
			'success' => true,
			'message' => "Price details",
			'data' => [
				'order_numner' => $order->order_number,
				'price' => $order->pending_amount,

			]
		]);
	}


}
