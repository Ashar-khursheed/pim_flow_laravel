<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FrontEnd\Order;

class TourasPaymentController extends Controller
{
	private $aggregatorId;
	private $merchantId;
	private $encryptionKey;
	private $postUrl;
	// private $successUrl;
	// private $failureUrl;

	public function __construct()
	{
		$this->aggregatorId = env('TOURAS_AGGREGATOR_ID');
		$this->merchantId = env('TOURAS_MERCHANT_ID');
		$this->encryptionKey = env('TOURAS_ENCRYPTION_KEY');
		$this->postUrl = env('TOURAS_POST_URL');
		// $this->successUrl = env('TOURAS_SUCCESS_URL');
		// $this->failureUrl = env('TOURAS_FAILURE_URL');
	}

	/**
	 * Encrypt data using AES-256-CBC
	 */
	private function encryptData($data)
	{
		try {
			$key = base64_decode($this->encryptionKey);
			$iv = openssl_random_pseudo_bytes(16);

			$encrypted = openssl_encrypt(
				json_encode($data),
				'AES-256-CBC',
				$key,
				OPENSSL_RAW_DATA,
				$iv
			);

			/* Combine IV and encrypted data */
			$result = base64_encode($iv . $encrypted);

			return $result;
		} catch (\Exception $e) {
			Log::error('Touras Encryption Error: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Decrypt response from Touras
	 */
	private function decryptData($encryptedData)
	{
		try {
			$key = base64_decode($this->encryptionKey);
			$data = base64_decode($encryptedData);

			/* Extract IV and encrypted content */
			$iv = substr($data, 0, 16);
			$encrypted = substr($data, 16);

			$decrypted = openssl_decrypt(
				$encrypted,
				'AES-256-CBC',
				$key,
				OPENSSL_RAW_DATA,
				$iv
			);

			return json_decode($decrypted, true);
		} catch (\Exception $e) {
			Log::error('Touras Decryption Error: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Create payment payload
	 */
	private function createPayload($orderData)
	{
		return [
			'card_details' => [
				'cardNumber' => $orderData['card_number'] ?? '',
				'expiryMonth' => $orderData['expiry_month'] ?? '',
				'expiryYear' => $orderData['expiry_year'] ?? '',
				'cvv' => $orderData['cvv'] ?? '',
				'cardName' => $orderData['card_name'] ?? '',
			],
			'upi_details' => [
				'VPAaddress' => $orderData['upi_address'] ?? '',
			],
			'other_details' => [
				'udf1' => $orderData['udf1'] ?? '',
				'udf2' => $orderData['udf2'] ?? '',
				'udf3' => $orderData['udf3'] ?? '',
				'udf4' => $orderData['udf4'] ?? '',
				'udf5' => $orderData['udf5'] ?? '',
				'udf6' => $orderData['udf6'] ?? '',
				'udf7' => $orderData['udf7'] ?? '',
			],
			'ship_details' => [
				'shipAddress' => $orderData['ship_address'] ?? '',
				'shipCity' => $orderData['ship_city'] ?? '',
				'shipState' => $orderData['ship_state'] ?? '',
				'shipCountry' => $orderData['ship_country'] ?? 'UAE',
				'shipZip' => $orderData['ship_zip'] ?? '',
				'shipDays' => $orderData['ship_days'] ?? '',
				'addressCount' => $orderData['address_count'] ?? '',
			],
			'txn_details' => [
				'agId' => $this->aggregatorId,
				'meId' => $this->merchantId,
				'orderNo' => $orderData['order_no'],
				'amount' => $orderData['amount'],
				'country' => $orderData['country'] ?? 'ARE',
				'currency' => $orderData['currency'] ?? 'AED',
				'transactionType' => $orderData['transaction_type'] ?? 'SALE',
				'sucessUrl' => $orderData['success_url'] ?? url(),
				'failureUrl' => $orderData['failure_url'] ?? url(),
				// 'sucessUrl' => $orderData['success_url'] ?? $this->successUrl,
				// 'failureUrl' => $orderData['failure_url'] ?? $this->failureUrl,
				'channel' => $orderData['channel'] ?? 'API',
			],
			'item_details' => [
				'itemCount' => $orderData['item_count'] ?? '',
				'itemValue' => $orderData['item_value'] ?? '',
				'itemCategory' => $orderData['item_category'] ?? '',
			],
			'cust_details' => [
				'customerName' => $orderData['customer_name'],
				'emailId' => $orderData['customer_email'],
				'mobileNumber' => $orderData['customer_mobile'],
				'uniqueId' => $orderData['customer_unique_id'] ?? '',
				'isLoggedIn' => $orderData['is_logged_in'] ?? 'Y',
			],
			'pg_details' => [
				'pg_Id' => $orderData['pg_id'] ?? '',
				'paymode' => $orderData['paymode'] ?? 'CC',
				'scheme_Id' => $orderData['scheme_id'] ?? '',
				'emi_Month' => $orderData['emi_month'] ?? '1',
			],
			'bill_details' => [
				'billAddress' => $orderData['bill_address'] ?? '',
				'billCity' => $orderData['bill_city'] ?? '',
				'billState' => $orderData['bill_state'] ?? '',
				'billCountry' => $orderData['bill_country'] ?? 'UAE',
				'billZip' => $orderData['bill_zip'] ?? '',
			],
		];
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/touras/initiate-payment",
	 *     summary="Initiate payment with Touras gateway",
	 *     tags={"FrontEnd-Touras"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"amount", "card_number", "expiry_month", "expiry_year", "cvv", "card_name"},
	 *             @OA\Property(property="amount", type="string", example="100.00"),
	 *             @OA\Property(property="card_number", type="string", example="2223000000000007"),
	 *             @OA\Property(property="expiry_month", type="string", example="12"),
	 *             @OA\Property(property="expiry_year", type="string", example="2034"),
	 *             @OA\Property(property="cvv", type="string", example="123"),
	 *             @OA\Property(property="card_name", type="string", example="John Doe"),
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Payment initiated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function initiatePayment(Request $request)
	{
		/* Validate request */
		$request->validate([
			'amount' => 'required|numeric|min:0.01',
			'card_number' => 'required|string',
			'expiry_month' => 'required|string|size:2',
			'expiry_year' => 'required|string|size:4',
			'cvv' => 'required|string|min:3|max:4',
			'card_name' => 'required|string',
		]);

		/* Get authenticated customer */
		$customer = auth()->user();

		if (!$customer) {
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized. Please login to continue.',
			], 401);
		}

		/* Prepare customer data */
		$customerData = [
			'customer_name' => $customer->name,
			'customer_email' => $customer->email,
			'customer_mobile' => $customer->mobile_number ?? $customer->phone,
			'customer_unique_id' => (string)$customer->id,
			'is_logged_in' => 'Y',
		];

		try {
			/* Generate unique order number */
			$latestOrder = Order::orderBy('order_number', 'desc')->first();

			/* Generate the next order number */
			if ($latestOrder && is_numeric($latestOrder->order_number)) {
				$orderNumber = (int) $latestOrder->order_number + 1;
			} else {
				$orderNumber = in_array(config('app.website'), ['US', 'US_T']) ? 10001 : (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101);
			}

			$orderNo = $orderNumber. '-' . time();

			/* Merge request data with customer data and order number */
			$paymentData = array_merge($request->all(), $customerData, [
				'order_no' => $orderNo,
			]);

			/* Create payload */
			$payload = $this->createPayload($paymentData);

			/* Log payload for debugging */
			Log::info('FrontEnd-Touras Payload', ['payload' => $payload]);

			/* Encrypt payload */
			$encryptedData = $this->encryptData($payload);

			if (!$encryptedData) {
				return response()->json([
					'success' => false,
					'message' => 'Failed to encrypt payment data',
				], 500);
			}

			/* Send request to Touras */
			$response = Http::asForm()->post($this->postUrl, [
				'encData' => $encryptedData,
				'meId' => $this->merchantId,
			]);

			/* Log response */
			Log::info('FrontEnd-Touras Response', [
				'status' => $response->status(),
				'body' => $response->body(),
			]);

			if ($response->successful()) {
				$responseData = $response->json();

				return response()->json([
					'success' => true,
					'message' => 'Payment initiated successfully',
					'order_no' => $orderNo,
					'data' => $responseData,
					'redirect_url' => $responseData['redirectUrl'] ?? null,
				], 201);
			}

			return response()->json([
				'success' => false,
				'message' => 'Payment initiation failed',
				'error' => $response->body(),
			], $response->status());

		} catch (\Exception $e) {
			Log::error('FrontEnd-Touras Error: ' . $e->getMessage());

			return response()->json([
				'success' => false,
				'message' => 'Payment processing failed',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/touras/success",
	 *     summary="Handle successful payment callback",
	 *     tags={"FrontEnd-Touras"},
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function handleSuccess(Request $request)
	{
		try {
			/* Get encrypted response */
			$encryptedData = $request->input('encData');

			if (!$encryptedData) {
				Log::warning('Touras Success: No encrypted data received');
				return redirect('/payment/failed?reason=invalid_response');
			}

			/* Decrypt response */
			$decryptedData = $this->decryptData($encryptedData);

			if (!$decryptedData) {
				Log::error('Touras Success: Failed to decrypt response');
				return redirect('/payment/failed?reason=decryption_failed');
			}

			/* Log transaction details */
			Log::info('FrontEnd-Touras Success', ['data' => $decryptedData]);

			/* Process successful payment */
			$orderNo = $decryptedData['orderNo'] ?? null;
			$transactionId = $decryptedData['transactionId'] ?? null;
			$amount = $decryptedData['amount'] ?? null;
			$status = $decryptedData['status'] ?? null;

			/* Update order in database */
			/* Your order update logic here */

			/* Redirect to success page */
			return redirect('/payment/success?order=' . $orderNo . '&transaction=' . $transactionId);

		} catch (\Exception $e) {
			Log::error('Touras Success Handler Error: ' . $e->getMessage());
			return redirect('/payment/failed?reason=processing_error');
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/touras/failure",
	 *     summary="Handle failed payment callback",
	 *     tags={"FrontEnd-Touras"},
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function handleFailure(Request $request)
	{
		try {
			/* Get encrypted response */
			$encryptedData = $request->input('encData');

			if (!$encryptedData) {
				Log::warning('Touras Failure: No encrypted data received');
				return redirect('/payment/failed?reason=invalid_response');
			}

			/* Decrypt response */
			$decryptedData = $this->decryptData($encryptedData);

			if (!$decryptedData) {
				Log::error('Touras Failure: Failed to decrypt response');
				return redirect('/payment/failed?reason=decryption_failed');
			}

			/* Log transaction details */
			Log::info('FrontEnd-Touras Failed', ['data' => $decryptedData]);

			/* Process failed payment */
			$orderNo = $decryptedData['orderNo'] ?? null;
			$reason = $decryptedData['reason'] ?? 'Unknown';

			/* Update order status in database */
			/* Your order update logic here */

			/* Redirect to failure page */
			return redirect('/payment/failed?order=' . $orderNo . '&reason=' . urlencode($reason));

		} catch (\Exception $e) {
			Log::error('Touras Failure Handler Error: ' . $e->getMessage());
			return redirect('/payment/failed?reason=processing_error');
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/touras/verify-payment",
	 *     summary="Verify payment status",
	 *     tags={"FrontEnd-Touras"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"order_no"},
	 *             @OA\Property(property="order_no", type="string", example="ORD123456"),
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function verifyPayment(Request $request)
	{
		$request->validate([
			'order_no' => 'required|string',
		]);

		try {
			/* Create verification payload */
			$payload = [
				'agId' => $this->aggregatorId,
				'meId' => $this->merchantId,
				'orderNo' => $request->order_no,
			];

			/* Encrypt payload */
			$encryptedData = $this->encryptData($payload);

			/* Send verification request */
			$response = Http::asForm()->post($this->postUrl . '/verify', [
				'encData' => $encryptedData,
				'meId' => $this->merchantId,
			]);

			if ($response->successful()) {
				$encryptedResponse = $response->json()['encData'] ?? null;

				if ($encryptedResponse) {
					$decryptedData = $this->decryptData($encryptedResponse);

					return response()->json([
						'success' => true,
						'data' => $decryptedData,
					]);
				}
			}

			return response()->json([
				'success' => false,
				'message' => 'Payment verification failed',
			], 400);

		} catch (\Exception $e) {
			Log::error('FrontEnd-Touras Verification Error: ' . $e->getMessage());

			return response()->json([
				'success' => false,
				'message' => 'Verification failed',
				'error' => $e->getMessage(),
			], 500);
		}
	}
}