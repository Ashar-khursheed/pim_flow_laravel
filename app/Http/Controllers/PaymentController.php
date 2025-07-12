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
