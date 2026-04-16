<?php

// namespace App\Http\Controllers\FrontEnd;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;
// use App\Models\FrontEnd\Order;
// use App\Jobs\Order\OrderPlacedMailJob;
// use App\Jobs\Order\OrderReservedMailJob;
// use App\Models\PaymentManagement;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Bus;

// class TourasPaymentController extends Controller
// {
// 	private $merchantId;
// 	private $aggregatorId;
// 	private $encryptionKey;
// 	private $postUrl;
// 	private $successUrl;
// 	private $failureUrl;
// 	private $frontendUrl;

// 	public function __construct()
// 	{
// 		$this->merchantId = env('TOURAS_MERCHANT_ID');
// 		$this->aggregatorId = env('TOURAS_AGGREGATOR_ID');
// 		$this->encryptionKey = env('TOURAS_ENCRYPTION_KEY');
// 		$this->postUrl = env('TOURAS_POST_URL');
// 		$this->successUrl = config('app.backend_url').'/api/frontend/touras/callback';
// 		$this->failureUrl = config('app.backend_url').'/api/frontend/touras/callback';

// 		// $this->successUrl = 'http://pim.devs/api/frontend/touras/callback';
// 		// $this->failureUrl = 'http://pim.devs/api/frontend/touras/callback';
// 		$this->frontendUrl = config('app.url');
// 	}

// 	/**
// 	 * @OA\Post(
// 	 *     path="/api/frontend/touras/initiate",
// 	 *     summary="Initiate Touras payment",
// 	 *     tags={"FrontEnd-Touras"},
// 	 *     @OA\RequestBody(
// 	 *         required=true,
// 	 *         @OA\JsonContent(
// 	 *             required={"amount", "channel"},
// 	 *             @OA\Property(property="amount", type="number", format="float", example=150.50, description="Transaction amount"),
// 	 *             @OA\Property(property="channel", type="string", example="WEB", description="Channel type (WEB or MOBILE)"),
// 	 *         )
// 	 *     ),
// 	 *     @OA\Response(response=201, description="Payment initiated successfully", @OA\MediaType(mediaType="application/json")),
// 	 *     security={{"bearerAuth":{}}}
// 	 * )
// 	 */
// 	public function initiate(Request $request)
// 	{
// 		/* Validate request */
// 		$request->validate([
// 			'amount' => 'required|numeric|min:0.01',
// 			'channel' => 'required|string|in:WEB,MOBILE',
// 		]);

// 		/* Get authenticated customer */
// 		$customer = auth()->user();
// 		if (!$customer) {
// 			return response()->json([
// 				'success' => false,
// 				'message' => 'Unauthorized. Please login to continue.',
// 			], 401);
// 		}

// 		try {
// 			$orderData = $request->all();

// 			// Default values
// 			$orderData['currency'] = 'AED';
// 			$orderData['country'] = 'ARE';

// 			/* Generate unique order number */
// 			$latestOrder = Order::orderBy('order_number', 'desc')->first();
// 			if ($latestOrder && is_numeric($latestOrder->order_number)) {
// 				$orderNumber = (int) $latestOrder->order_number + 1;
// 			} else {
// 				$orderNumber = in_array(config('app.website'), ['US', 'US_T'])
// 				? 10001
// 				: (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101);
// 			}
// 			$orderData['order_number'] = $orderNumber . 'H' . time();

// 			// Prepare payment request
// 			$paymentData = $this->preparePaymentRequest($orderData);

// 			Log::channel('testLog')->info('check');
// 			return response()->json([
// 				'success' => true,
// 				'message' => 'Payment initiated successfully',
// 				'data' => $paymentData
// 			], 200);

// 		} catch (\Exception $e) {
// 			return response()->json([
// 				'success' => false,
// 				'message' => 'Payment initiation failed: ' . $e->getMessage(),
// 			], 500);
// 		}
// 	}

// 	/**
// 	 * Create Touras payment link for Admin/Order
// 	 */
// 	public function createTourasPaymentLink(Order $order)
// 	{
// 		// Generate the payment link that points to our internal pay route
// 		// This route will render the form and auto-submit to Touras
// 		// We use the order ID to identify the order
// 		return route('frontend.touras.pay', ['order' => $order->id]);
// 	}

// 	/**
// 	 * Handle the payment link visit (GET request)
// 	 * Renders the view that auto-submits to Touras
// 	 */
// 	public function pay(Request $request, $orderId)
// 	{
// 		$order = Order::findOrFail($orderId);

// 		if ($order->is_paid) {
// 			return "Order is already paid.";
// 		}

// 		// Prepare data for Touras
// 		$orderData = [
// 			'amount' => $order->pending_amount > 0 ? $order->pending_amount : $order->total_amount,
// 			'order_number' => $order->order_number . 'H' . time(),
// 			'country' => 'ARE', // Default to UAE as per requirement
// 			'currency' => 'AED',
// 			'channel' => 'WEB',
// 			'customer' => [
// 				'cust_name' => $order->customer->name ?? '',
// 				'email_id' => $order->customer->email ?? '',
// 				'mobile_no' => $order->customer->mobile_number ?? '',
// 				'unique_id' => $order->customer_id ?? '',
// 				'is_logged_in' => 'N',
// 			],
// 			'billing' => [
// 				'bill_address' => $order->customerAddress->address ?? '',
// 				'bill_city' => $order->customerAddress->city ?? '',
// 				'bill_state' => $order->customerAddress->state ?? '',
// 				'bill_country' => $order->customerAddress->country ?? '',
// 				'bill_zip' => $order->customerAddress->zipcode ?? '',
// 			],
// 			'shipping' => [
// 				'ship_address' => $order->customerAddress->address ?? '',
// 				'ship_city' => $order->customerAddress->city ?? '',
// 				'ship_state' => $order->customerAddress->state ?? '',
// 				'ship_country' => $order->customerAddress->country ?? '',
// 				'ship_zip' => $order->customerAddress->zipcode ?? '',
// 				'ship_days' => $order->product_supplier['delivery_days'] ?? '2',
// 				'address_count' => '1',
// 			],
// 		];

// 		// Reuse the existing preparePaymentRequest logic
// 		$paymentData = $this->preparePaymentRequest($orderData, [
// 			'success_url' => $this->successUrl . '?source=admin',
// 			'failure_url' => $this->failureUrl . '?source=admin',
// 		]);

// 		return view('touras-payment-form', [
// 			'postUrl' => $paymentData['post_url'],
// 			'meId' => $paymentData['me_id'],
// 			'merchantRequest' => $paymentData['merchant_request'],
// 			'hash' => $paymentData['hash'],
// 		]);
// 	}

// 	/**
// 	 * Prepare payment request with encryption
// 	 */
// 	private function preparePaymentRequest($orderData, $overrides = [])
// 	{
// 		// Transaction Details (Required) - 10 fields
// 		$txnDetails = [
// 			$this->aggregatorId,                                    // ag_id
// 			$this->merchantId,                                      // me_id
// 			$orderData['order_number'],                             // order_no
// 			number_format($orderData['amount'], 2, '.', ''),        // amount
// 			$orderData['country'],                                  // country
// 			$orderData['currency'],                                 // currency
// 			'SALE',                                                 // txn_type
// 			$overrides['success_url'] ?? $this->successUrl,         // success_url
// 			$overrides['failure_url'] ?? $this->failureUrl,         // failure_url
// 			$orderData['channel'],                                  // channel
// 		];

// 		// PG Details (Optional) - 4 fields
// 		$pgDetails = [
// 			$orderData['pg_details']['pg_id'] ?? '',                // pg_id
// 			$orderData['pg_details']['paymode'] ?? '',              // paymode
// 			$orderData['pg_details']['scheme'] ?? '',               // scheme
// 			$orderData['pg_details']['emi_months'] ?? '',           // emi_months
// 		];

// 		// Card Details (Optional) - 5 fields
// 		$cardDetails = [
// 			$orderData['card_details']['card_no'] ?? '',            // card_no
// 			$orderData['card_details']['exp_month'] ?? '',          // exp_month
// 			$orderData['card_details']['exp_year'] ?? '',           // exp_year
// 			$orderData['card_details']['cvv2'] ?? '',               // cvv2
// 			$orderData['card_details']['card_name'] ?? '',          // card_name
// 		];

// 		// Customer Details (Optional) - 5 fields
// 		$custDetails = [
// 			$orderData['customer']['cust_name'] ?? '',              // cust_name
// 			$orderData['customer']['email_id'] ?? '',               // email_id
// 			$orderData['customer']['mobile_no'] ?? '',              // mobile_no
// 			$orderData['customer']['unique_id'] ?? '',              // unique_id
// 			$orderData['customer']['is_logged_in'] ?? '',           // is_logged_in
// 		];

// 		// Billing Details (Optional) - 5 fields
// 		$billDetails = [
// 			$orderData['billing']['bill_address'] ?? '',            // bill_address
// 			$orderData['billing']['bill_city'] ?? '',               // bill_city
// 			$orderData['billing']['bill_state'] ?? '',              // bill_state
// 			$orderData['billing']['bill_country'] ?? '',            // bill_country
// 			$orderData['billing']['bill_zip'] ?? '',                // bill_zip
// 		];

// 		// Shipping Details (Optional) - 7 fields
// 		$shipDetails = [
// 			$orderData['shipping']['ship_address'] ?? '',           // ship_address
// 			$orderData['shipping']['ship_city'] ?? '',              // ship_city
// 			$orderData['shipping']['ship_state'] ?? '',             // ship_state
// 			$orderData['shipping']['ship_country'] ?? '',           // ship_country
// 			$orderData['shipping']['ship_zip'] ?? '',               // ship_zip
// 			$orderData['shipping']['ship_days'] ?? '',              // ship_days
// 			$orderData['shipping']['address_count'] ?? '',          // address_count
// 		];

// 		// Item Details (Optional) - 3 fields
// 		$itemDetails = [
// 			$orderData['item_details']['item_count'] ?? '',         // item_count
// 			$orderData['item_details']['item_value'] ?? '',         // item_value
// 			$orderData['item_details']['item_category'] ?? '',      // item_category
// 		];

// 		// UPI Details (Optional) - 1 field
// 		$upiDetails = $orderData['upi_details']['upi_id'] ?? '';    // Direct string, not array

// 		// Other Details (Optional) - 5 fields
// 		$otherDetails = [
// 			$orderData['other_details']['udf_1'] ?? '',             // udf_1
// 			$orderData['other_details']['udf_2'] ?? '',             // udf_2
// 			$orderData['other_details']['udf_3'] ?? '',             // udf_3
// 			$orderData['other_details']['udf_4'] ?? '',             // udf_4
// 			$orderData['other_details']['udf_5'] ?? '',             // udf_5
// 		];

// 		// Recurring Details (Optional) - 1 field
// 		$recurringDetails = $orderData['recurring_details']['planId'] ?? '';  // Direct string, not array

// 		// Combine all sections with ~ separator (EXACTLY AS PER TOURAS SOURCE CODE)
// 		$allValues =
// 		implode('|', $txnDetails) . '~' .
// 		implode('|', $pgDetails) . '~' .
// 		implode('|', $cardDetails) . '~' .
// 		implode('|', $custDetails) . '~' .
// 		implode('|', $billDetails) . '~' .
// 		implode('|', $shipDetails) . '~' .
// 		implode('|', $itemDetails) . '~' .
// 		$upiDetails . '~' .                                     // Direct string
// 		implode('|', $otherDetails) . '~' .
// 		$recurringDetails;                                       // Direct string

// 		// Encrypt merchant request using custom AES function
// 		$merchantRequest = $this->encryptAES($allValues, $this->encryptionKey, 256);

// 		// Generate hash as per Touras specification
// 		// Hash format: merchant_id~order_no~amount~country~currency
// 		$hashString = $this->merchantId . '~' .
// 		$orderData['order_number'] . '~' .
// 		number_format($orderData['amount'], 2, '.', '') . '~' .
// 		$orderData['country'] . '~' .
// 		$orderData['currency'];

// 		// SHA256 hash WITHOUT encryption first
// 		$checksum = hash("sha256", $hashString, false);

// 		// Then encrypt the checksum
// 		$hash = $this->encryptAES($checksum, $this->encryptionKey, 256);;

// 		return [
// 			'me_id' => $this->merchantId,
// 			'merchant_request' => $merchantRequest,
// 			'hash' => $hash,
// 			'post_url' => $this->postUrl,
// 			// 'order_number' => $orderData['order_number'],
// 		];
// 	}

// 	public function handleCallback(Request $request)
// 	{
// 		try {
// 			if (!$request->has('txn_response') || !$request->has('me_id')) {
// 				Log::error('Touras Callback: Missing txn_response or me_id', $request->all());
// 				if ($request->query('source') === 'admin') {
// 					return view('touras-payment-decline', ['message' => 'Invalid payment response']);
// 				}
// 				return redirect($this->frontendUrl . '/payment/decline?' . http_build_query([
// 					'success' => false,
// 					'message' => 'Invalid payment response',
// 				]));
// 			}

// 			$encryptedResponse = [
// 				'txn_response' => $request->input('txn_response'),
// 				'me_id' => $request->input('me_id'),
// 				'pg_details' => $request->input('pg_details'),
// 				'fraud_details' => $request->input('fraud_details'),
// 				'other_details' => $request->input('other_details'),
// 				'planId' => $request->input('planId'),
// 			];

// 			// Decrypt and parse response (with hash verification)
// 			$response = $this->parseResponse($encryptedResponse);

// 			// Log::channel('testLog')->info('Decrypt Reponse', $response);

// 			// Validate merchant ID
// 			if ($response['me_id'] !== $this->merchantId) {
// 				Log::error('Touras Callback: Merchant ID Mismatch', ['expected' => $this->merchantId, 'received' => $response['me_id']]);
// 				if ($request->query('source') === 'admin') {
// 					return view('touras-payment-decline', ['message' => 'Invalid merchant ID']);
// 				}
// 				return redirect($this->frontendUrl . '/payment/decline?' . http_build_query([
// 					'success' => false,
// 					'message' => 'Invalid merchant ID',
// 				]));
// 			}

// 			if (isset($response['response_code']) && $response['response_code'] === '0' && isset($response['message']) && strtolower($response['message']) === 'successful') {
				
// 				// Update Order Status in Database
// 				$orderNumber = $response['order_no'];
				
// 				/* Handle suffixed order number (e.g., 2398H1740012345 or 2398-1740012345) */
// 				$baseOrderNumber = $orderNumber;
// 				if (strpos($orderNumber, 'H') !== false) {
// 					$parts = explode('H', $orderNumber);
// 					$baseOrderNumber = $parts[0];
// 				} elseif (strpos($orderNumber, '-') !== false) {
// 					$parts = explode('-', $orderNumber);
// 					$baseOrderNumber = $parts[0];
// 				}

// 				$order = Order::where('order_number', $baseOrderNumber)->first();

// 				if ($order) {

// 					// Dispatch Email using Bus::batch
// 					try {
// 						if ($request->boolean('is_reserved')) {
// 							$batch = Bus::batch([])->name("Order Reserved by Backend - #{$order->order_number}")->dispatch();
// 							$batch->options['queue'] = config('app.website') . '_ORD_RES';
// 							$batch->add(new OrderReservedMailJob([
// 								'recordId' => $order->id
// 							]));
// 						} else {
// 							$batch = Bus::batch([])->name("Order Placed by Backend - #{$order->order_number}")->dispatch();
// 							$batch->options['queue'] = config('app.website') . '_ORD_PLC';
// 							$batch->add(new OrderPlacedMailJob([
// 								'recordId' => $order->id
// 							]));
// 						}
// 					} catch (\Exception $e) {
// 						Log::error('Touras Callback: Failed to dispatch email batch', [
// 							'error' => $e->getMessage(),
// 							'order_id' => $order->id
// 						]);
// 					}

// 					// Update Order Status in Database
// 					$order->update([
// 						'is_paid' => true,
// 						'paid_amount' => $response['amount'],
// 						'pending_amount' => 0,
// 						'payment_link' => null,
// 						'is_reserved' => false, // Set is_reserved to 0
// 					]);

// 					// Create Payment Record
// 					try {
// 						PaymentManagement::create([
// 							'order_id' => $order->id,
// 							'transaction_id' => $response['transaction_id'] ?? null,
// 							'payment_mode' => 'Credit Card', // Using 'Credit Card' as it's a valid ENUM value. 
// 							'amount' => $response['amount'],
// 							'status' => 'Success',
// 							'payment_date' => now(),
// 							'notes' => json_encode(['payment_gateway' => 'Touras', 'raw_response' => $response]),
// 							'payment_method' => 'Touras',
// 							'created_by' => auth()->id() ?? null,
// 						]);
// 					} catch (\Exception $e) {
// 						Log::error('Touras Callback: Failed to create payment record in payment_managements', [
// 							'error' => $e->getMessage(),
// 							'order_id' => $order->id
// 						]);
// 					}
// 				}

// 				if ($request->query('source') === 'admin') {
// 					return view('touras-payment-success');
// 				}

// 				$redirectUrl = $this->frontendUrl . '/review-checkout?' . http_build_query([
// 					'success' => true,
// 					'order_no' => $response['order_no'],
// 					'transaction_id' => $response['transaction_id'],
// 					'amount' => $response['amount'],
// 					'currency' => $response['currency'],
// 					'transaction_date' => $response['transaction_date'] ?? date('Y-m-d'),
// 					'transaction_time' => $response['transaction_time'] ?? date('H:i:s'),
// 					'status' => $response['status'],
// 				]);
// 				return redirect($redirectUrl);
// 			} else {
// 				Log::warning('Touras Payment Failed', $response);
// 				if ($request->query('source') === 'admin') {
// 					return view('touras-payment-decline', ['message' => $response['message'] ?? 'Payment declined']);
// 				}
// 				$redirectUrl = $this->frontendUrl . '/payment/decline?' . http_build_query([
// 					'success' => false,
// 					'order_no' => $response['order_no'],
// 					'message' => $response['message'] ?? 'Payment declined',
// 					'status' => $response['status'],
// 				]);
// 				return redirect($redirectUrl);
// 			}

// 		} catch (\Exception $e) {
// 			Log::error('Touras Callback Exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
// 			if ($request->query('source') === 'admin') {
// 				return view('touras-payment-decline', ['message' => 'Payment processing error']);
// 			}
// 			$redirectUrl = $this->frontendUrl . '/payment/decline?' . http_build_query([
// 				'success' => false,
// 				'message' => 'Payment processing error',
// 			]);
// 			return redirect($redirectUrl);
// 		}
// 	}

// 	/**
// 	 * Parse encrypted response from Touras (with optional hash verification)
// 	 */
// 	private function parseResponse($encryptedResponse)
// 	{
// 		try {
// 			$txnResponseEncrypted = $encryptedResponse['txn_response'] ?? '';

// 			if (empty($txnResponseEncrypted)) {
// 				throw new \Exception('Transaction response is empty');
// 			}

// 			$txnResponseDecrypted = $this->decryptAES($txnResponseEncrypted, $this->encryptionKey, 256);
// 			$hasTildeSeparator = strpos($txnResponseDecrypted, '~') !== false;

// 			$txnResponseActual = '';
// 			$txnResHash = '';
// 			$isGenuine = true;

// 			if ($hasTildeSeparator) {
// 				$txnResponseHash = explode('~', $txnResponseDecrypted);
// 				$txnResHash = $txnResponseHash[1] ?? '';
// 				$txnResponseActual = ($txnResponseHash[0] ?? '') . ($txnResponseHash[2] ?? '');
// 			} else {
// 				$txnResponseActual = $txnResponseDecrypted;
// 				$isGenuine = true; // Skip verification
// 			}

// 			$txnResponseArr = explode('|', $txnResponseActual);

// 			if ($hasTildeSeparator && !empty($txnResHash)) {
// 				// Hash format: status~me_id~order_no~amount~country~currency~ag_ref
// 				$hashString = ($txnResponseArr[10] ?? '') . '~' .
// 				($txnResponseArr[1] ?? '') . '~' .
// 				($txnResponseArr[2] ?? '') . '~' .
// 				($txnResponseArr[3] ?? '') . '~' .
// 				($txnResponseArr[4] ?? '') . '~' .
// 				($txnResponseArr[5] ?? '') . '~' .
// 				($txnResponseArr[8] ?? '');

// 				$checksum = hash("sha256", $hashString, false);
// 				$createHash = $this->encryptAES($checksum, $this->encryptionKey, 256);
// 				$isGenuine = ($txnResHash === $createHash);

// 				$strictHashVerification = env('TOURAS_STRICT_HASH_VERIFICATION', false);

// 				if (!$isGenuine && $strictHashVerification) {
// 					throw new \Exception('Hash verification failed - Response may be tampered');
// 				}
// 			}
// 			$response = [
// 				// 'protocol' => $isGenuine ? 'Genuine' : 'Unverified',
// 				// 'ag_id' => $txnResponseArr[0] ?? null,
// 				'payment_method' => $txnResponseArr[0] ?? null,
// 				'me_id' => $txnResponseArr[1] ?? null,
// 				'order_no' => $txnResponseArr[2] ?? null,
// 				'amount' => $txnResponseArr[3] ?? null,
// 				'country' => $txnResponseArr[4] ?? null,
// 				'currency' => $txnResponseArr[5] ?? null,
// 				'transaction_date' => $txnResponseArr[6] ?? null,
// 				'transaction_time' => $txnResponseArr[7] ?? null,
// 				// 'ag_ref' => $txnResponseArr[8] ?? null,
// 				'transaction_id' => $txnResponseArr[8] ?? null,
// 				// 'pg_ref' => $txnResponseArr[9] ?? null,
// 				'bank_ref_no' => $txnResponseArr[9] ?? null,
// 				'status' => $txnResponseArr[10] ?? null,
// 				'response_code' => $txnResponseArr[11] ?? null,
// 				'message' => $txnResponseArr[12] ?? null
// 			];
// 			return $response;

// 		} catch (\Exception $e) {
// 			throw $e;
// 		}
// 	}

// 	/**
// 	 * Encrypt data using AES-256-CBC (Touras specific implementation)
// 	 * EXACT copy from Touras source code
// 	 */
// 	private function encryptAES($text, $key, $type)
// 	{
// 		$iv = "0123456789abcdef";  // Static IV as per Touras
// 		$size = 16;
// 		$pad = $size - (strlen($text) % $size);
// 		$padtext = $text . str_repeat(chr($pad), $pad);

// 		$crypt = openssl_encrypt(
// 			$padtext,
// 			"AES-256-CBC",
// 			base64_decode($key),
// 			OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
// 			$iv
// 		);

// 		return base64_encode($crypt);
// 	}

// 	/**
// 	 * Decrypt data using AES-256-CBC (Touras specification)
// 	 */
// 	private function decryptAES($crypt, $key, $type = 256)
// 	{
// 		try {
// 			$iv = "0123456789abcdef";
// 			$cryptDecoded = base64_decode($crypt);

// 			if ($cryptDecoded === false) return false;

// 			$padtext = openssl_decrypt(
// 				$cryptDecoded,
// 				"AES-256-CBC",
// 				base64_decode($key),
// 				OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
// 				$iv
// 			);

// 			if ($padtext === false) return false;

// 			$pad = ord($padtext[strlen($padtext) - 1]);

// 			if ($pad > strlen($padtext)) return false;

// 			if (strspn($padtext, $padtext[strlen($padtext) - 1], strlen($padtext) - $pad) != $pad) {
// 				return "Error";
// 			}

// 			return substr($padtext, 0, -1 * $pad);

// 		} catch (\Exception $e) {
// 			return false;
// 		}
// 	}

// 	/**
// 	 * Decrypt response from Touras
// 	 */
// 	private function decryptData($encryptedText)
// 	{
// 		try {
// 			$key = base64_decode($this->encryptionKey);
// 			$iv = "0123456789abcdef";

// 			$decrypted = openssl_decrypt(
// 				base64_decode($encryptedText),
// 				"AES-256-CBC",
// 				$key,
// 				OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
// 				$iv
// 			);

// 			/* Remove manual padding */
// 			$pad = ord(substr($decrypted, -1));
// 			$decrypted = substr($decrypted, 0, -1 * $pad);

// 			return json_decode($decrypted, true);

// 		} catch (\Exception $e) {
// 			Log::error('Touras Decrypt Error', ['error' => $e->getMessage()]);
// 			return null;
// 		}
// 	}
// }



namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FrontEnd\Order;
use App\Jobs\Order\OrderPlacedMailJob;
use App\Jobs\Order\OrderReservedMailJob;
use App\Models\PaymentManagement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;

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
		$this->successUrl = config('app.backend_url').'/api/frontend/touras/callback';
		$this->failureUrl = config('app.backend_url').'/api/frontend/touras/callback';

		// $this->successUrls = 'http://pim.devs/api/frontend/touras/callback';
		// $this->failureUrl = 'http://pim.devs/api/frontend/touras/callback';
		 $this->frontendUrl = config('app.url');
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/touras/initiate",
	 *     summary="Initiate Touras payment",
	 *     tags={"FrontEnd-Touras"},
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

			/* Generate unique order number */
			$latestOrder = Order::orderBy('order_number', 'desc')->first();
			if ($latestOrder && is_numeric($latestOrder->order_number)) {
				$orderNumber = (int) $latestOrder->order_number + 1;
			} else {
				$orderNumber = in_array(config('app.website'), ['US', 'US_T'])
				? 10001
				: (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101);
			}
			$orderData['order_number'] = $orderNumber . 'H' . time();

			// Prepare payment request
			$paymentData = $this->preparePaymentRequest($orderData);

			Log::channel('testLog')->info('check');
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
	 * Create Touras payment link for Admin/Order
	 */
	public function createTourasPaymentLink(Order $order)
	{
		// Generate the payment link that points to our internal pay route
		// This route will render the form and auto-submit to Touras
		// We use the order ID to identify the order
		return route('frontend.touras.pay', ['order' => $order->id]);
	}

	/**
	 * Handle the payment link visit (GET request)
	 * Renders the view that auto-submits to Touras
	 */
	public function pay(Request $request, $orderId)
	{
		$order = Order::findOrFail($orderId);

		if ($order->is_paid) {
			return "Order is already paid.";
		}

		// Prepare data for Touras
		$orderData = [
			'amount' => $order->pending_amount > 0 ? $order->pending_amount : $order->total_amount,
			'order_number' => $order->order_number . 'H' . time(),
			'country' => 'ARE', // Default to UAE as per requirement
			'currency' => 'AED',
			'channel' => 'WEB',
			'customer' => [
				'cust_name' => $order->customer->name ?? '',
				'email_id' => $order->customer->email ?? '',
				'mobile_no' => $order->customer->mobile_number ?? '',
				'unique_id' => $order->customer_id ?? '',
				'is_logged_in' => 'N',
			],
			'billing' => [
				'bill_address' => $order->customerAddress->address ?? '',
				'bill_city' => $order->customerAddress->city ?? '',
				'bill_state' => $order->customerAddress->state ?? '',
				'bill_country' => $order->customerAddress->country ?? '',
				'bill_zip' => $order->customerAddress->zipcode ?? '',
			],
			'shipping' => [
				'ship_address' => $order->customerAddress->address ?? '',
				'ship_city' => $order->customerAddress->city ?? '',
				'ship_state' => $order->customerAddress->state ?? '',
				'ship_country' => $order->customerAddress->country ?? '',
				'ship_zip' => $order->customerAddress->zipcode ?? '',
				'ship_days' => $order->product_supplier['delivery_days'] ?? '2',
				'address_count' => '1',
			],
		];

		// Reuse the existing preparePaymentRequest logic
		$paymentData = $this->preparePaymentRequest($orderData, [
			'success_url' => $this->successUrl . '?source=admin',
			'failure_url' => $this->failureUrl . '?source=admin',
		]);

		return view('touras-payment-form', [
			'postUrl' => $paymentData['post_url'],
			'meId' => $paymentData['me_id'],
			'merchantRequest' => $paymentData['merchant_request'],
			'hash' => $paymentData['hash'],
		]);
	}

	/**
	 * Prepare payment request with encryption
	 */
	private function preparePaymentRequest($orderData, $overrides = [])
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
			$overrides['success_url'] ?? $this->successUrl,         // success_url
			$overrides['failure_url'] ?? $this->failureUrl,         // failure_url
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
		$hash = $this->encryptAES($checksum, $this->encryptionKey, 256);;

		return [
			'me_id' => $this->merchantId,
			'merchant_request' => $merchantRequest,
			'hash' => $hash,
			'post_url' => $this->postUrl,
			// 'order_number' => $orderData['order_number'],
		];
	}

	public function handleCallback(Request $request)
	{
		try {
			if (!$request->has('txn_response') || !$request->has('me_id')) {
				Log::error('Touras Callback: Missing txn_response or me_id', $request->all());
				if ($request->query('source') === 'admin') {
					return view('touras-payment-decline', ['message' => 'Invalid payment response']);
				}
				return redirect($this->frontendUrl . '/payment/decline?' . http_build_query([
					'success' => false,
					'message' => 'Invalid payment response',
				]));
			}

			$encryptedResponse = [
				'txn_response' => $request->input('txn_response'),
				'me_id' => $request->input('me_id'),
				'pg_details' => $request->input('pg_details'),
				'fraud_details' => $request->input('fraud_details'),
				'other_details' => $request->input('other_details'),
				'planId' => $request->input('planId'),
			];

			// Decrypt and parse response (with hash verification)
			$response = $this->parseResponse($encryptedResponse);

			// Log::channel('testLog')->info('Decrypt Reponse', $response);

			// Validate merchant ID
			if ($response['me_id'] !== $this->merchantId) {
				Log::error('Touras Callback: Merchant ID Mismatch', ['expected' => $this->merchantId, 'received' => $response['me_id']]);
				if ($request->query('source') === 'admin') {
					return view('touras-payment-decline', ['message' => 'Invalid merchant ID']);
				}
				return redirect($this->frontendUrl . '/payment/decline?' . http_build_query([
					'success' => false,
					'message' => 'Invalid merchant ID',
				]));
			}

			if (isset($response['response_code']) && $response['response_code'] === '0' && isset($response['message']) && strtolower($response['message']) === 'successful') {
				
				// Update Order Status in Database
				$orderNumber = $response['order_no'];
				
				/* Handle suffixed order number (e.g., 2398H1740012345 or 2398-1740012345) */
				$baseOrderNumber = $orderNumber;
				if (strpos($orderNumber, 'H') !== false) {
					$parts = explode('H', $orderNumber);
					$baseOrderNumber = $parts[0];
				} elseif (strpos($orderNumber, '-') !== false) {
					$parts = explode('-', $orderNumber);
					$baseOrderNumber = $parts[0];
				}

				$order = Order::where('order_number', $baseOrderNumber)->first();

				if ($order) {

					// Dispatch Email using Bus::batch
					try {
						if ($request->boolean('is_reserved')) {
							$batch = Bus::batch([])->name("Order Reserved by Backend - #{$order->order_number}")->dispatch();
							$batch->options['queue'] = config('app.website') . '_ORD_RES';
							$batch->add(new OrderReservedMailJob([
								'recordId' => $order->id
							]));
						} else {
							$batch = Bus::batch([])->name("Order Placed by Backend - #{$order->order_number}")->dispatch();
							$batch->options['queue'] = config('app.website') . '_ORD_PLC';
							$batch->add(new OrderPlacedMailJob([
								'recordId' => $order->id
							]));
						}
					} catch (\Exception $e) {
						Log::error('Touras Callback: Failed to dispatch email batch', [
							'error' => $e->getMessage(),
							'order_id' => $order->id
						]);
					}

					// Update Order Status in Database
					$order->update([
						'is_paid' => true,
						'paid_amount' => $response['amount'],
						'pending_amount' => 0,
						'payment_link' => null,
						'is_reserved' => false, // Set is_reserved to 0
					]);

					// Create Payment Record
					try {
						PaymentManagement::create([
							'order_id' => $order->id,
							'transaction_id' => $response['transaction_id'] ?? null,
							'payment_mode' => 'Credit Card', // Using 'Credit Card' as it's a valid ENUM value. 
							'amount' => $response['amount'],
							'status' => 'Success',
							'payment_date' => now(),
							'notes' => json_encode(['payment_gateway' => 'Touras', 'raw_response' => $response]),
							'payment_method' => 'Touras',
							'created_by' => auth()->id() ?? null,
						]);
					} catch (\Exception $e) {
						Log::error('Touras Callback: Failed to create payment record in payment_managements', [
							'error' => $e->getMessage(),
							'order_id' => $order->id
						]);
					}
				}

				if ($request->query('source') === 'admin') {
					return view('touras-payment-success');
				}

				$redirectUrl = $this->frontendUrl . '/review-checkout?' . http_build_query([
					'success' => true,
					'order_no' => $response['order_no'],
					'transaction_id' => $response['transaction_id'],
					'amount' => $response['amount'],
					'currency' => $response['currency'],
					'transaction_date' => $response['transaction_date'] ?? date('Y-m-d'),
					'transaction_time' => $response['transaction_time'] ?? date('H:i:s'),
					'status' => $response['status'],
				]);
				return redirect($redirectUrl);
			} else {
				Log::warning('Touras Payment Failed', $response);
				if ($request->query('source') === 'admin') {
					return view('touras-payment-decline', ['message' => $response['message'] ?? 'Payment declined']);
				}
				$redirectUrl = $this->frontendUrl . '/payment/decline?' . http_build_query([
					'success' => false,
					'order_no' => $response['order_no'],
					'message' => $response['message'] ?? 'Payment declined',
					'status' => $response['status'],
				]);
				return redirect($redirectUrl);
			}

		} catch (\Exception $e) {
			Log::error('Touras Callback Exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
			if ($request->query('source') === 'admin') {
				return view('touras-payment-decline', ['message' => 'Payment processing error']);
			}
			$redirectUrl = $this->frontendUrl . '/payment/decline?' . http_build_query([
				'success' => false,
				'message' => 'Payment processing error',
			]);
			return redirect($redirectUrl);
		}
	}

	/**
	 * Parse encrypted response from Touras (with optional hash verification)
	 */
	private function parseResponse($encryptedResponse)
	{
		try {
			$txnResponseEncrypted = $encryptedResponse['txn_response'] ?? '';

			if (empty($txnResponseEncrypted)) {
				throw new \Exception('Transaction response is empty');
			}

			$txnResponseDecrypted = $this->decryptAES($txnResponseEncrypted, $this->encryptionKey, 256);
			$hasTildeSeparator = strpos($txnResponseDecrypted, '~') !== false;

			$txnResponseActual = '';
			$txnResHash = '';
			$isGenuine = true;

			if ($hasTildeSeparator) {
				$txnResponseHash = explode('~', $txnResponseDecrypted);
				$txnResHash = $txnResponseHash[1] ?? '';
				$txnResponseActual = ($txnResponseHash[0] ?? '') . ($txnResponseHash[2] ?? '');
			} else {
				$txnResponseActual = $txnResponseDecrypted;
				$isGenuine = true; // Skip verification
			}

			$txnResponseArr = explode('|', $txnResponseActual);

			if ($hasTildeSeparator && !empty($txnResHash)) {
				// Hash format: status~me_id~order_no~amount~country~currency~ag_ref
				$hashString = ($txnResponseArr[10] ?? '') . '~' .
				($txnResponseArr[1] ?? '') . '~' .
				($txnResponseArr[2] ?? '') . '~' .
				($txnResponseArr[3] ?? '') . '~' .
				($txnResponseArr[4] ?? '') . '~' .
				($txnResponseArr[5] ?? '') . '~' .
				($txnResponseArr[8] ?? '');

				$checksum = hash("sha256", $hashString, false);
				$createHash = $this->encryptAES($checksum, $this->encryptionKey, 256);
				$isGenuine = ($txnResHash === $createHash);

				$strictHashVerification = env('TOURAS_STRICT_HASH_VERIFICATION', false);

				if (!$isGenuine && $strictHashVerification) {
					throw new \Exception('Hash verification failed - Response may be tampered');
				}
			}
			$response = [
				// 'protocol' => $isGenuine ? 'Genuine' : 'Unverified',
				// 'ag_id' => $txnResponseArr[0] ?? null,
				'payment_method' => $txnResponseArr[0] ?? null,
				'me_id' => $txnResponseArr[1] ?? null,
				'order_no' => $txnResponseArr[2] ?? null,
				'amount' => $txnResponseArr[3] ?? null,
				'country' => $txnResponseArr[4] ?? null,
				'currency' => $txnResponseArr[5] ?? null,
				'transaction_date' => $txnResponseArr[6] ?? null,
				'transaction_time' => $txnResponseArr[7] ?? null,
				// 'ag_ref' => $txnResponseArr[8] ?? null,
				'transaction_id' => $txnResponseArr[8] ?? null,
				// 'pg_ref' => $txnResponseArr[9] ?? null,
				'bank_ref_no' => $txnResponseArr[9] ?? null,
				'status' => $txnResponseArr[10] ?? null,
				'response_code' => $txnResponseArr[11] ?? null,
				'message' => $txnResponseArr[12] ?? null
			];
			return $response;

		} catch (\Exception $e) {
			throw $e;
		}
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
	 * Decrypt data using AES-256-CBC (Touras specification)
	 */
	private function decryptAES($crypt, $key, $type = 256)
	{
		try {
			$iv = "0123456789abcdef";
			$cryptDecoded = base64_decode($crypt);

			if ($cryptDecoded === false) return false;

			$padtext = openssl_decrypt(
				$cryptDecoded,
				"AES-256-CBC",
				base64_decode($key),
				OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
				$iv
			);

			if ($padtext === false) return false;

			$pad = ord($padtext[strlen($padtext) - 1]);

			if ($pad > strlen($padtext)) return false;

			if (strspn($padtext, $padtext[strlen($padtext) - 1], strlen($padtext) - $pad) != $pad) {
				return "Error";
			}

			return substr($padtext, 0, -1 * $pad);

		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Decrypt response from Touras
	 */
	private function decryptData($encryptedText)
	{
		try {
			$key = base64_decode($this->encryptionKey);
			$iv = "0123456789abcdef";

			$decrypted = openssl_decrypt(
				base64_decode($encryptedText),
				"AES-256-CBC",
				$key,
				OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
				$iv
			);

			/* Remove manual padding */
			$pad = ord(substr($decrypted, -1));
			$decrypted = substr($decrypted, 0, -1 * $pad);

			return json_decode($decrypted, true);

		} catch (\Exception $e) {
			Log::error('Touras Decrypt Error', ['error' => $e->getMessage()]);
			return null;
		}
	}
}