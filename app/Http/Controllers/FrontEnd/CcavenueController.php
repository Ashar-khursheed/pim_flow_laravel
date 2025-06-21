<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;
use App\Helpers\CcavenueHelper;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CcavenueController extends Controller
{
    private $accessCode;
    private $workingKey;
    private $merchantId;
    private $redirectUrl;
    private $cancelUrl;

    public function __construct()
    {
        $this->accessCode = config('ccavenue.access_code');
        $this->workingKey = config('ccavenue.working_key');
        $this->merchantId = config('ccavenue.merchant_id');
        $this->redirectUrl = config('ccavenue.redirect_url');
        $this->cancelUrl = config('ccavenue.cancel_url');
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/ccavenue/initiate",
     *     tags={"Payments"},
     *     summary="Initiate CCAvenue Payment",
     *     description="Initiates a payment request to CCAvenue and returns a redirect HTML form.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "name", "email", "phone", "address"},
     *             @OA\Property(property="amount", type="number", example=500.00),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", example="9876543210"),
     *             @OA\Property(property="address", type="string", example="123 Street Name, City")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="HTML form auto-submitting to CCAvenue"
     *     )
     * )
     */
    public function initiatePayment(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate unique order ID
            $orderId = 'ORD_' . time() . '_' . rand(1000, 9999);

            // Prepare post data for CCAvenue
            $postData = [
                "merchant_id" => $this->merchantId,
                "order_id" => $orderId,
                "currency" => "AED",
                "amount" => number_format($request->amount, 2, '.', ''),
                "redirect_url" => $this->redirectUrl,
                "cancel_url" => $this->cancelUrl,
                "language" => "EN",
                
                // Billing information
                "billing_name" => $request->name,
                "billing_email" => $request->email,
                "billing_tel" => $request->phone,
                "billing_address" => $request->address,
                "billing_city" => $request->city ?? "Dubai",
                "billing_state" => $request->state ?? "DU",
                "billing_zip" => $request->zip ?? "000000",
                "billing_country" => "United Arab Emirates",
                
                // Optional delivery information (same as billing for now)
                "delivery_name" => $request->name,
                "delivery_address" => $request->address,
                "delivery_city" => $request->city ?? "Dubai",
                "delivery_state" => $request->state ?? "DU",
                "delivery_zip" => $request->zip ?? "000000",
                "delivery_country" => "United Arab Emirates",
                "delivery_tel" => $request->phone,
            ];

            // Convert to query string
            $merchantData = http_build_query($postData);
            
            // Log the merchant data for debugging (remove in production)
            Log::info('CCAvenue Payment Initiated', [
                'order_id' => $orderId,
                'amount' => $request->amount,
                'customer' => $request->name
            ]);

            // Encrypt the request
            $encryptedData = CcavenueHelper::encrypt($merchantData, $this->workingKey);

            // Return the form view for web requests
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'order_id' => $orderId,
                    'redirect_form' => view('ccavenue.payment', [
                        'encRequest' => $encryptedData,
                        'accessCode' => $this->accessCode,
                    ])->render()
                ]);
            }

            return view('ccavenue.payment', [
                'encRequest' => $encryptedData,
                'accessCode' => $this->accessCode,
            ]);

        } catch (\Exception $e) {
            Log::error('CCAvenue Payment Initiation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment initiation failed. Please try again.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/ccavenue/response",
     *     tags={"Payments"},
     *     summary="Handle CCAvenue Payment Response",
     *     description="Handles the encrypted response from CCAvenue and returns payment status.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"encResp"},
     *                 @OA\Property(property="encResp", type="string", example="encrypted_response_string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment status result",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="order_id", type="string", example="ORD123456"),
     *                 @OA\Property(property="order_status", type="string", example="Success"),
     *                 @OA\Property(property="tracking_id", type="string", example="987654321"),
     *                 @OA\Property(property="bank_ref_no", type="string", example="REF987654")
     *             )
     *         )
     *     )
     * )
     */
    public function handleResponse(Request $request)
    {
        try {
            $encResponse = $request->input("encResp");
            
            if (!$encResponse) {
                Log::warning('CCAvenue Response: No encrypted response received');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid response from payment gateway'
                ], 400);
            }

            // Decrypt the response
            $decryptedString = CcavenueHelper::decrypt($encResponse, $this->workingKey);
            
            // Parse the decrypted string
            parse_str($decryptedString, $output);

            // Log the response for debugging (remove sensitive data in production)
            Log::info('CCAvenue Payment Response', [
                'order_id' => $output['order_id'] ?? 'N/A',
                'order_status' => $output['order_status'] ?? 'N/A',
                'tracking_id' => $output['tracking_id'] ?? 'N/A'
            ]);

            // Check payment status
            if (isset($output['order_status']) && $output['order_status'] === "Success") {
                // Payment successful - you can add your business logic here
                // e.g., update order status in database, send confirmation email, etc.
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment completed successfully',
                    'data' => [
                        'order_id' => $output['order_id'] ?? '',
                        'order_status' => $output['order_status'] ?? '',
                        'tracking_id' => $output['tracking_id'] ?? '',
                        'bank_ref_no' => $output['bank_ref_no'] ?? '',
                        'amount' => $output['amount'] ?? '',
                        'status_code' => $output['status_code'] ?? '',
                        'status_message' => $output['status_message'] ?? ''
                    ]
                ]);
            } else {
                // Payment failed
                return response()->json([
                    'status' => 'failed',
                    'message' => $output['status_message'] ?? 'Payment failed',
                    'data' => [
                        'order_id' => $output['order_id'] ?? '',
                        'order_status' => $output['order_status'] ?? '',
                        'failure_message' => $output['failure_message'] ?? '',
                        'status_code' => $output['status_code'] ?? '',
                        'status_message' => $output['status_message'] ?? ''
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('CCAvenue Response Handling Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Response processing failed'
            ], 500);
        }
    }

    /**
     * Test endpoint to verify CCAvenue configuration
     */
    public function testConfiguration()
    {
        $config = [
            'access_code' => $this->accessCode ? 'Set' : 'Not Set',
            'working_key' => $this->workingKey ? 'Set' : 'Not Set',
            'merchant_id' => $this->merchantId ? 'Set' : 'Not Set',
            'redirect_url' => $this->redirectUrl ?: 'Not Set',
            'cancel_url' => $this->cancelUrl ?: 'Not Set',
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'CCAvenue Configuration Status',
            'config' => $config
        ]);
    }
}