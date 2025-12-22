<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\CryptoHelper;
use App\Models\CcavenueTransaction;
use App\Models\PaymentManagement;

class PaymentController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/payments",
	 *     summary="Get all payment records between two dates",
	 *     tags={"Payments"},
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Response(response=200, description="Orders retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$request->validate([
			'from_date' => 'required|date',
			'to_date'   => 'required|date|after_or_equal:from_date',
		]);

		$from = $request->from_date . ' 00:00:00';
		$to   = $request->to_date . ' 23:59:59';

		$records = PaymentManagement::whereBetween('created_at', [$from, $to])->pluck('id');

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data'    => $records,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/payments",
	 *     summary="Create a new payment",
	 *     description="Create a new payment record for an authenticated customer",
	 *     tags={"Payments"},
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
	 *                 enum={"Credit Card", "Debit Card", "PayPal", "Bank Transfer", "Cash", "Stripe", "Razorpay","Paymob","Stax","Square","CC Avenue","NetTerm","Check","Cheque"}
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
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		try {
			$validated = $request->validate([
				'order_id' => 'required|integer|exists:orders,id',
				'transaction_id' => 'nullable|string|max:255|unique:payments_management,transaction_id',
				'payment_mode' => 'required|string|in:Credit Card,Debit Card,PayPal,Bank Transfer,Cash on Delivery,Stripe,Razorpay,Paymob,CC Avenue,Stax,Square,NetTerm,Check,Cheque',
				'amount' => 'required|numeric|min:0.01|max:999999.99',
				'status' => 'required|string|in:pending,completed,failed,cancelled,refunded',
				'payment_date' => 'required|date|before_or_equal:today',
				'notes' => 'nullable|string|max:1000',
				'payment_details' => 'nullable|array|max:2000',
			]);

			if (isset($validated['payment_details'])) {
				$validated['payment_details'] = json_encode($validated['payment_details']);
			}

			$payment = PaymentManagement::create($validated);

			return response()->json([
				'message' => 'Payment created successfully.',
				'data' => $payment
			], 201);

		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Something went wrong while creating the payment.',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/payments/{id}",
	 *     summary="Get payment details",
	 *     tags={"Payments"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Payment ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Payment details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function show($id)
	{
		$payment = PaymentManagement::find($id);

		if (!$payment) {
			return response()->json([
				'success' => false,
				'message' => "Payment not found."
			]);
		}

		/* Load relationships */
		$payment->load([
			'order'
		]);

		return response()->json([
			'success' => true,
			'data' => $payment
		]);
	}

	/**
	 * Initiate CCAvenue payment.
	 *
	 * @OA\Post(
	 *     path="/api/payment/ccavenue/initiate",
	 *     summary="Initiate CCAvenue payment",
	 *     description="Encrypts payment data and returns encRequest and access_code for CCAvenue redirection.",
	 *     tags={"Payments"},
	 *      security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="order_id", type="string", example="ORD12345"),
	 *             @OA\Property(property="amount", type="number", format="float", example=100.50),
	 *             @OA\Property(property="currency", type="string", example="INR"),
	 *             @OA\Property(property="redirect_url", type="string", example="https://your-site.com/callback"),
	 *             @OA\Property(property="cancel_url", type="string", example="https://your-site.com/cancel")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Encrypted data for redirection",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="url", type="string", example="https://secure.ccavenue.ae/transaction/transaction.do?command=initiateTransaction"),
	 *             @OA\Property(property="encRequest", type="string", example="encrypted-data"),
	 *             @OA\Property(property="access_code", type="string", example="XYZ123")
	 *         )
	 *     )
	 * )
	 */
	public function initiatePayment(Request $request)
	{
		$workingKey = env('CCAVENUE_WORKING_KEY');
		$accessCode = env('CCAVENUE_ACCESS_CODE');

		// Collect the merchant data to be sent in the request
		$merchant_data = '';
		foreach ($request->all() as $key => $value) {
			$merchant_data .= $key . '=' . urlencode($value) . '&';
		}

		// Encrypt the data using CryptoHelper
		$encryptedData = CryptoHelper::encrypt($merchant_data, $workingKey);

		// Return the response with form data to be submitted on the frontend
		return response()->json([
			'url' => 'https://secure.ccavenue.ae/transaction/transaction.do?command=initiateTransaction',
			'encRequest' => $encryptedData,
			'access_code' => $accessCode
		]);
	}


	/**
	 * Handle CCAvenue payment callback.
	 *
	 * @OA\Post(
	 *     path="/api/payment/ccavenue/callback",
	 *     summary="CCAvenue payment callback",
	 *     description="Decrypts response from CCAvenue and saves transaction.",
	 *     tags={"Payments"},
	 *      security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="encResp", type="string", example="encrypted-response-from-ccavenue")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Transaction saved",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Transaction saved"),
	 *             @OA\Property(property="order_status", type="string", example="Success"),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     )
	 * )
	 */
	public function paymentCallback(Request $request)
	{
		$workingKey = env('CCAVENUE_WORKING_KEY');
		$encResp = $request->input('encResp');
		$rcvdString = CryptoHelper::decrypt($encResp, $workingKey);

		$data = [];
		foreach (explode('&', $rcvdString) as $value) {
			[$key, $val] = explode('=', $value);
			$data[$key] = urldecode($val);
		}

	// Save to DB
		CcavenueTransaction::updateOrCreate(
			['order_id' => $data['order_id']],
			[
				'tracking_id' => $data['tracking_id'] ?? null,
				'bank_ref_no' => $data['bank_ref_no'] ?? null,
				'order_status' => $data['order_status'] ?? 'unknown',
				'payment_mode' => $data['payment_mode'] ?? null,
				'amount' => $data['amount'] ?? 0,
				'currency' => $data['currency'] ?? 'INR',
				'raw_response' => $data,
			]
		);

		return response()->json([
			'message' => 'Transaction saved',
			'order_status' => $data['order_status'],
			'data' => $data,
		]);
	}

	/**
	 * Get all transactions.
	 *
	 * @OA\Get(
	 *     path="/api/transactions",
	 *     summary="List all transactions",
	 *     description="Returns a list of all CCAvenue transactions stored in the database.",
	 *     tags={"Payments"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of transactions",
	 *         @OA\JsonContent(
	 *             @OA\Property(
	 *                 property="transactions",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="order_id", type="string", example="ORD123"),
	 *                     @OA\Property(property="amount", type="number", example=150.75),
	 *                     @OA\Property(property="order_status", type="string", example="Success"),
	 *                     @OA\Property(property="payment_mode", type="string", example="Net Banking"),
	 *                     @OA\Property(property="currency", type="string", example="INR")
	 *                 )
	 *             )
	 *         )
	 *     )
	 * )
	 */
	public function getAllTransactions()
	{
		return response()->json([
			'transactions' => CcavenueTransaction::latest()->get()
		]);
	}

}
