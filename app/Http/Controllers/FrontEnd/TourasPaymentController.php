<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FrontEnd\Order;

class TourasPaymentController extends Controller
{
	private $merchantId;
	private $aggregatorId;
	private $encryptionKey;
	private $postUrl;
	private $successUrl;
	private $failureUrl;
	private $frontendUrl;

	public function __construct()
	{
		$this->merchantId = env('TOURAS_MERCHANT_ID');
		$this->aggregatorId = env('TOURAS_AGGREGATOR_ID');
		$this->encryptionKey = env('TOURAS_ENCRYPTION_KEY');
		$this->postUrl = env('TOURAS_POST_URL');
		// $this->successUrl = env('TOURAS_SUCCESS_URL');
		// $this->failureUrl = env('TOURAS_FAILURE_URL');
		// $this->successUrl = url("/touras");
		$this->successUrl = "http://localhost:3000/touras";
		$this->failureUrl = 'https://development.d28qosi1cuigvb.amplifyapp.com/';
		// $this->frontendUrl = env('FRONTEND_URL');
		$this->frontendUrl = '';
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/touras/initiate",
	 *     summary="Initiate Touras payment",
	 *     tags={"Front-Touras"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"amount", "channel"},
	 *             @OA\Property(property="amount", type="number", format="float", example=150.50, description="Transaction amount"),
	 *             @OA\Property(property="channel", type="string", example="WEB", description="Channel type (WEB or MOBILE)"),
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Payment initiated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function initiate(Request $request)
	{
		/* Validate request */
		$request->validate([
			'amount' => 'required|numeric|min:0.01',
			'channel' => 'required|string|in:WEB,MOBILE',
		]);

		/* Get authenticated customer */
		$customer = auth()->user();

		if (!$customer) {
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized. Please login to continue.',
			], 401);
		}

		try {
			$orderData = $request->all();

			// Default values
			$orderData['currency'] = 'AED';
			$orderData['country'] = 'ARE';

			// Customer details from authenticated user
			$orderData['customer'] = [
				'cust_name' => $customer->name,
				'email_id' => $customer->email,
				'mobile_no' => $customer->mobile_number ?? '',
				'unique_id' => (string) $customer->id,
				'is_logged_in' => 'Y',
			];

			/* Generate unique order number */
			$latestOrder = Order::orderBy('order_number', 'desc')->first();

			if ($latestOrder && is_numeric($latestOrder->order_number)) {
				$orderNumber = (int) $latestOrder->order_number + 1;
			} else {
				$orderNumber = in_array(config('app.website'), ['US', 'US_T'])
				? 10001
				: (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101);
			}

			$orderData['order_number'] = $orderNumber . '-' . time();

			// Prepare payment request
			$paymentData = $this->preparePaymentRequest($orderData);

			return response()->json([
				'success' => true,
				'message' => 'Payment initiated successfully',
				'data' => $paymentData
			], 200);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Payment initiation failed: ' . $e->getMessage(),
			], 500);
		}
	}

	/**
	 * Prepare payment request with encryption
	 */
	private function preparePaymentRequest($orderData)
	{
		// Transaction Details (Required) - 10 fields
		$txnDetails = [
			$this->aggregatorId,                                    // ag_id
			$this->merchantId,                                      // me_id
			$orderData['order_number'],                             // order_no
			number_format($orderData['amount'], 2, '.', ''),        // amount
			$orderData['country'],                                  // country
			$orderData['currency'],                                 // currency
			'SALE',                                                 // txn_type
			$this->successUrl,                                      // success_url
			$this->failureUrl,                                      // failure_url
			$orderData['channel'],                                  // channel
		];

		// PG Details (Optional) - 4 fields
		$pgDetails = [
			$orderData['pg_details']['pg_id'] ?? '',                // pg_id
			$orderData['pg_details']['paymode'] ?? '',              // paymode
			$orderData['pg_details']['scheme'] ?? '',               // scheme
			$orderData['pg_details']['emi_months'] ?? '',           // emi_months
		];

		// Card Details (Optional) - 5 fields
		$cardDetails = [
			$orderData['card_details']['card_no'] ?? '',            // card_no
			$orderData['card_details']['exp_month'] ?? '',          // exp_month
			$orderData['card_details']['exp_year'] ?? '',           // exp_year
			$orderData['card_details']['cvv2'] ?? '',               // cvv2
			$orderData['card_details']['card_name'] ?? '',          // card_name
		];

		// Customer Details (Optional) - 5 fields
		$custDetails = [
			$orderData['customer']['cust_name'] ?? '',              // cust_name
			$orderData['customer']['email_id'] ?? '',               // email_id
			$orderData['customer']['mobile_no'] ?? '',              // mobile_no
			$orderData['customer']['unique_id'] ?? '',              // unique_id
			$orderData['customer']['is_logged_in'] ?? '',           // is_logged_in
		];

		// Billing Details (Optional) - 5 fields
		$billDetails = [
			$orderData['billing']['bill_address'] ?? '',            // bill_address
			$orderData['billing']['bill_city'] ?? '',               // bill_city
			$orderData['billing']['bill_state'] ?? '',              // bill_state
			$orderData['billing']['bill_country'] ?? '',            // bill_country
			$orderData['billing']['bill_zip'] ?? '',                // bill_zip
		];

		// Shipping Details (Optional) - 7 fields
		$shipDetails = [
			$orderData['shipping']['ship_address'] ?? '',           // ship_address
			$orderData['shipping']['ship_city'] ?? '',              // ship_city
			$orderData['shipping']['ship_state'] ?? '',             // ship_state
			$orderData['shipping']['ship_country'] ?? '',           // ship_country
			$orderData['shipping']['ship_zip'] ?? '',               // ship_zip
			$orderData['shipping']['ship_days'] ?? '',              // ship_days
			$orderData['shipping']['address_count'] ?? '',          // address_count
		];

		// Item Details (Optional) - 3 fields
		$itemDetails = [
			$orderData['item_details']['item_count'] ?? '',         // item_count
			$orderData['item_details']['item_value'] ?? '',         // item_value
			$orderData['item_details']['item_category'] ?? '',      // item_category
		];

		// UPI Details (Optional) - 1 field
		$upiDetails = $orderData['upi_details']['upi_id'] ?? '';    // Direct string, not array

		// Other Details (Optional) - 5 fields
		$otherDetails = [
			$orderData['other_details']['udf_1'] ?? '',             // udf_1
			$orderData['other_details']['udf_2'] ?? '',             // udf_2
			$orderData['other_details']['udf_3'] ?? '',             // udf_3
			$orderData['other_details']['udf_4'] ?? '',             // udf_4
			$orderData['other_details']['udf_5'] ?? '',             // udf_5
		];

		// Recurring Details (Optional) - 1 field
		$recurringDetails = $orderData['recurring_details']['planId'] ?? '';  // Direct string, not array

		// Combine all sections with ~ separator (EXACTLY AS PER TOURAS SOURCE CODE)
		$allValues =
		implode('|', $txnDetails) . '~' .
		implode('|', $pgDetails) . '~' .
		implode('|', $cardDetails) . '~' .
		implode('|', $custDetails) . '~' .
		implode('|', $billDetails) . '~' .
		implode('|', $shipDetails) . '~' .
		implode('|', $itemDetails) . '~' .
			$upiDetails . '~' .                                     // Direct string
			implode('|', $otherDetails) . '~' .
			$recurringDetails;                                       // Direct string

		// Encrypt merchant request using custom AES function
			$merchantRequest = $this->encryptAES($allValues, $this->encryptionKey, 256);

		// Generate hash as per Touras specification
		// Hash format: merchant_id~order_no~amount~country~currency
			$hashString = $this->merchantId . '~' .
			$orderData['order_number'] . '~' .
			number_format($orderData['amount'], 2, '.', '') . '~' .
			$orderData['country'] . '~' .
			$orderData['currency'];

		// SHA256 hash WITHOUT encryption first
			$checksum = hash("sha256", $hashString, false);

		// Then encrypt the checksum
			$hash = $this->encryptAES($checksum, $this->encryptionKey, 256);

			Log::info('Touras Payment Request Created', [
				'order_number' => $orderData['order_number'],
				'amount' => $orderData['amount'],
				'hash_string' => $hashString,
				'checksum' => $checksum,
				'raw_data' => substr($allValues, 0, 200) . '...',
			]);

			return [
				'me_id' => $this->merchantId,
				'merchant_request' => $merchantRequest,
				'hash' => $hash,
				'post_url' => $this->postUrl,
				'order_number' => $orderData['order_number'],
			];
		}

	/**
	 * Encrypt data using AES-256-CBC (Touras specific implementation)
	 * EXACT copy from Touras source code
	 */
	private function encryptAES($text, $key, $type)
	{
		$iv = "0123456789abcdef";  // Static IV as per Touras
		$size = 16;
		$pad = $size - (strlen($text) % $size);
		$padtext = $text . str_repeat(chr($pad), $pad);

		$crypt = openssl_encrypt(
			$padtext,
			"AES-256-CBC",
			base64_decode($key),
			OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
			$iv
		);

		return base64_encode($crypt);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/touras/callback/success",
	 *     summary="Handle successful payment callback from Touras",
	 *     tags={"Front-Touras"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/x-www-form-urlencoded",
	 *             @OA\Schema(
	 *                 @OA\Property(property="txn_response", type="string", description="Encrypted transaction response"),
	 *                 @OA\Property(property="me_id", type="string", description="Merchant ID"),
	 *                 @OA\Property(property="pg_details", type="string", description="Encrypted PG details"),
	 *                 @OA\Property(property="fraud_details", type="string", description="Encrypted fraud details"),
	 *                 @OA\Property(property="other_details", type="string", description="Encrypted other details")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=302,
	 *         description="Redirect to frontend success page"
	 *     )
	 * )
	 */
	public function handleSuccessCallback(Request $request)
	{
		try {
			Log::info('Touras Success Callback Received', $request->all());

			$encryptedResponse = [
				'txn_response' => $request->input('txn_response'),
				'me_id' => $request->input('me_id'),
				'pg_details' => $request->input('pg_details'),
				'fraud_details' => $request->input('fraud_details'),
				'other_details' => $request->input('other_details'),
			];

			// Decrypt and parse response
			$response = $this->parseResponse($encryptedResponse);

			// Check if payment is successful
			$isSuccessful = $this->isPaymentSuccessful($response);

			if ($isSuccessful) {
				// Update transaction
				DB::table('payment_transactions')
				->where('order_no', $response['order_no'])
				->update([
					'payment_status' => 'completed',
					'transaction_id' => $response['txn_id'],
					'bank_ref_no' => $response['bank_ref_no'] ?? null,
					'pg_txn_id' => $response['pg_details']['pg_txn_id'] ?? null,
					'payment_response' => json_encode($response),
					'paid_at' => now(),
					'updated_at' => now(),
				]);

				Log::info('Payment Completed Successfully', [
					'order_no' => $response['order_no'],
					'txn_id' => $response['txn_id'],
				]);

				// Redirect to frontend success page
				$redirectUrl = $this->frontendUrl . '/payment/success?order_no=' . urlencode($response['order_no']) . '&txn_id=' . urlencode($response['txn_id']);
				return redirect($redirectUrl);

			} else {
				// Payment failed
				DB::table('payment_transactions')
				->where('order_no', $response['order_no'])
				->update([
					'payment_status' => 'failed',
					'payment_response' => json_encode($response),
					'updated_at' => now(),
				]);

				Log::warning('Payment Failed on Success Callback', $response);

				$redirectUrl = $this->frontendUrl . '/payment/failed?order_no=' . urlencode($response['order_no']) . '&reason=' . urlencode($response['status_msg'] ?? 'Payment declined');
				return redirect($redirectUrl);
			}

		} catch (\Exception $e) {
			Log::error('Touras Success Callback Error', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'request' => $request->all(),
			]);

			$redirectUrl = $this->frontendUrl . '/payment/error?message=' . urlencode('Payment processing error');
			return redirect($redirectUrl);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/touras/callback/failure",
	 *     summary="Handle failed payment callback from Touras",
	 *     tags={"Front-Touras"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/x-www-form-urlencoded",
	 *             @OA\Schema(
	 *                 @OA\Property(property="txn_response", type="string", description="Encrypted transaction response"),
	 *                 @OA\Property(property="me_id", type="string", description="Merchant ID"),
	 *                 @OA\Property(property="pg_details", type="string", description="Encrypted PG details"),
	 *                 @OA\Property(property="fraud_details", type="string", description="Encrypted fraud details"),
	 *                 @OA\Property(property="other_details", type="string", description="Encrypted other details")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=302,
	 *         description="Redirect to frontend failure page"
	 *     )
	 * )
	 */
	public function handleFailureCallback(Request $request)
	{
		try {
			Log::info('Touras Failure Callback Received', $request->all());

			$encryptedResponse = [
				'txn_response' => $request->input('txn_response'),
				'me_id' => $request->input('me_id'),
				'pg_details' => $request->input('pg_details'),
				'fraud_details' => $request->input('fraud_details'),
				'other_details' => $request->input('other_details'),
			];

			// Decrypt and parse response
			$response = $this->parseResponse($encryptedResponse);

			// Update transaction
			DB::table('payment_transactions')
			->where('order_no', $response['order_no'])
			->update([
				'payment_status' => 'failed',
				'transaction_id' => $response['txn_id'] ?? null,
				'payment_response' => json_encode($response),
				'updated_at' => now(),
			]);

			Log::warning('Payment Failed', [
				'order_no' => $response['order_no'],
				'status' => $response['status_msg'] ?? 'Unknown',
			]);

			// Redirect to frontend failure page
			$redirectUrl = $this->frontendUrl . '/payment/failed?order_no=' . urlencode($response['order_no']) . '&reason=' . urlencode($response['status_msg'] ?? 'Payment declined');
			return redirect($redirectUrl);

		} catch (\Exception $e) {
			Log::error('Touras Failure Callback Error', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'request' => $request->all(),
			]);

			$redirectUrl = $this->frontendUrl . '/payment/error?message=' . urlencode('Payment processing error');
			return redirect($redirectUrl);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/touras/status/{order_no}",
	 *     summary="Get payment transaction status",
	 *     tags={"Front-Touras"},
	 *     @OA\Parameter(
	 *         name="order_no",
	 *         in="path",
	 *         required=true,
	 *         description="Order number",
	 *         @OA\Schema(type="string", example="ORD-12345")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Transaction status retrieved successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="object",
	 *                 @OA\Property(property="order_no", type="string", example="ORD-12345"),
	 *                 @OA\Property(property="amount", type="number", example=150.50),
	 *                 @OA\Property(property="currency", type="string", example="AED"),
	 *                 @OA\Property(property="payment_status", type="string", example="completed"),
	 *                 @OA\Property(property="transaction_id", type="string", example="2058981736486650927"),
	 *                 @OA\Property(property="bank_ref_no", type="string", example="123456789"),
	 *                 @OA\Property(property="paid_at", type="string", format="date-time"),
	 *                 @OA\Property(property="payment_response", type="object")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Transaction not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Transaction not found")
	 *         )
	 *     )
	 * )
	 */
	public function getPaymentStatus($orderNo)
	{
		$transaction = DB::table('payment_transactions')
		->where('order_no', $orderNo)
		->first();

		if (!$transaction) {
			return response()->json([
				'success' => false,
				'message' => 'Transaction not found',
			], 404);
		}

		return response()->json([
			'success' => true,
			'data' => [
				'order_no' => $transaction->order_no,
				'amount' => (float) $transaction->amount,
				'currency' => $transaction->currency,
				'payment_status' => $transaction->payment_status,
				'transaction_id' => $transaction->transaction_id,
				'bank_ref_no' => $transaction->bank_ref_no,
				'pg_txn_id' => $transaction->pg_txn_id,
				'customer_email' => $transaction->customer_email,
				'paid_at' => $transaction->paid_at,
				'payment_response' => $transaction->payment_response ? json_decode($transaction->payment_response) : null,
				'created_at' => $transaction->created_at,
			],
		], 200);
	}

	/**
	 * Parse encrypted response
	 */
	private function parseResponse($encryptedResponse)
	{
		// Decrypt main response
		$decryptedResponse = $this->decrypt($encryptedResponse['txn_response']);
		$responseParts = explode('|', $decryptedResponse);

		$response = [
			'ag_id' => $responseParts[0] ?? null,
			'me_id' => $responseParts[1] ?? null,
			'order_no' => $responseParts[2] ?? null,
			'amount' => $responseParts[3] ?? null,
			'country' => $responseParts[4] ?? null,
			'currency' => $responseParts[5] ?? null,
			'txn_date' => $responseParts[6] ?? null,
			'txn_time' => $responseParts[7] ?? null,
			'txn_id' => $responseParts[8] ?? null,
			'bank_ref_no' => $responseParts[9] ?? null,
			'status_desc' => $responseParts[10] ?? null,
			'status_code' => $responseParts[11] ?? null,
			'status_msg' => $responseParts[12] ?? null,
		];

		// Decrypt pg_details
		if (!empty($encryptedResponse['pg_details'])) {
			$pgDetailsDecrypted = $this->decrypt($encryptedResponse['pg_details']);
			$pgParts = explode('|', $pgDetailsDecrypted);
			$response['pg_details'] = [
				'pg_txn_id' => $pgParts[0] ?? null,
				'pg_inst_name' => $pgParts[1] ?? null,
				'pg_mode' => $pgParts[2] ?? null,
				'pg_type_id' => $pgParts[3] ?? null,
			];
		}

		// Decrypt fraud_details
		if (!empty($encryptedResponse['fraud_details'])) {
			$response['fraud_details'] = $this->decrypt($encryptedResponse['fraud_details']);
		}

		// Decrypt other_details
		if (!empty($encryptedResponse['other_details'])) {
			$otherDetailsDecrypted = $this->decrypt($encryptedResponse['other_details']);
			$otherParts = explode('|', $otherDetailsDecrypted);
			$response['other_details'] = [
				'param1' => $otherParts[0] ?? null,
				'param2' => $otherParts[1] ?? null,
				'param3' => $otherParts[2] ?? null,
				'param4' => $otherParts[3] ?? null,
			];
		}

		return $response;
	}

	/**
	 * Decrypt data using AES-256-CBC
	 */
	private function decrypt($encryptedData)
	{
		$key = base64_decode($this->encryptionKey);
		$data = base64_decode($encryptedData);

		// Extract IV (first 16 bytes)
		$iv = substr($data, 0, 16);
		$encrypted = substr($data, 16);

		$decrypted = openssl_decrypt(
			$encrypted,
			'AES-256-CBC',
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		return $decrypted;
	}

	/**
	 * Check if payment was successful
	 */
	private function isPaymentSuccessful($response)
	{
		return isset($response['status_code']) &&
		$response['status_code'] === '0' &&
		strtolower($response['status_msg']) === 'successful';
	}
}