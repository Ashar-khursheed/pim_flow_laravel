<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;
use App\Http\Requests\PaymentRequest;
use App\Services\CCAvenueService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\Customer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class CCavenueController extends Controller
{
    protected $ccavenueService;

    public function __construct(CCavenueService $ccavenueService)
    {
        $this->ccavenueService = $ccavenueService;
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/ccavenue/initiate-payment",
     *     summary="Initiate CCAvenue payment",
     *     description="Create a payment request and get payment URL",
     *     operationId="initiatePayment",
     *     tags={"CCAvenue"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id", "amount", "currency", "redirect_url", "cancel_url"},
     *             @OA\Property(property="order_id", type="string", example="ORD123456789", description="Unique order identifier"),
     *             @OA\Property(property="amount", type="number", format="float", example=100.50, description="Payment amount"),
     *             @OA\Property(property="currency", type="string", example="INR", enum={"INR", "USD", "EUR", "GBP", "AED"}),
     *             @OA\Property(property="redirect_url", type="string", format="uri", example="https://yoursite.com/payment/success"),
     *             @OA\Property(property="cancel_url", type="string", format="uri", example="https://yoursite.com/payment/cancel"),
     *             @OA\Property(property="language", type="string", example="EN", enum={"EN", "HI"}),
     *             @OA\Property(property="billing_name", type="string", example="John Doe"),
     *             @OA\Property(property="billing_address", type="string", example="123 Main Street"),
     *             @OA\Property(property="billing_city", type="string", example="Mumbai"),
     *             @OA\Property(property="billing_state", type="string", example="Maharashtra"),
     *             @OA\Property(property="billing_zip", type="string", example="400001"),
     *             @OA\Property(property="billing_country", type="string", example="India"),
     *             @OA\Property(property="billing_tel", type="string", example="9876543210"),
     *             @OA\Property(property="billing_email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="delivery_name", type="string", example="John Doe"),
     *             @OA\Property(property="delivery_address", type="string", example="456 Oak Avenue"),
     *             @OA\Property(property="delivery_city", type="string", example="Delhi"),
     *             @OA\Property(property="delivery_state", type="string", example="Delhi"),
     *             @OA\Property(property="delivery_zip", type="string", example="110001"),
     *             @OA\Property(property="delivery_country", type="string", example="India"),
     *             @OA\Property(property="delivery_tel", type="string", example="9876543210"),
     *             @OA\Property(property="merchant_param1", type="string", example="Additional info 1"),
     *             @OA\Property(property="merchant_param2", type="string", example="Additional info 2"),
     *             @OA\Property(property="merchant_param3", type="string", example="Additional info 3"),
     *             @OA\Property(property="merchant_param4", type="string", example="Additional info 4"),
     *             @OA\Property(property="merchant_param5", type="string", example="Additional info 5"),
     *             @OA\Property(property="promo_code", type="string", example="DISCOUNT10"),
     *             @OA\Property(property="customer_identifier", type="string", example="CUST123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment URL generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment URL generated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="payment_url", type="string", example="https://secure.ccavenue.ae/transaction/transaction.do?command=initiateTransaction&encRequest=..."),
     *                 @OA\Property(property="order_id", type="string", example="ORD123456789"),
     *                 @OA\Property(property="amount", type="number", example=100.50),
     *                 @OA\Property(property="currency", type="string", example="INR")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to generate payment URL")
     *         )
     *     )
     * )
     */
    public function initiatePayment(PaymentRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['amount'] = number_format((float) $data['amount'], 2, '.', '');

            // Get merchant ID
            $merchantId = $this->ccavenueService->getMerchantId();

            // Set default values
            $data['language'] = $data['language'] ?? 'EN';

            // Define allowed parameters for CCAvenue
            $allowedKeys = [
                'order_id',
                'currency',
                'amount',
                'redirect_url',
                'cancel_url',
                'language',
                'billing_name',
                'billing_address',
                'billing_city',
                'billing_state',
                'billing_zip',
                'billing_country',
                'billing_tel',
                'billing_email',
                'delivery_name',
                'delivery_address',
                'delivery_city',
                'delivery_state',
                'delivery_zip',
                'delivery_country',
                'delivery_tel',
                'merchant_param1',
                'merchant_param2',
                'merchant_param3',
                'merchant_param4',
                'merchant_param5',
                'promo_code',
                'customer_identifier'
            ];

            // Build merchant data string - start with merchant_id
            $merchantData = "merchant_id={$merchantId}";

            foreach ($data as $key => $value) {
                if (in_array($key, $allowedKeys) && !empty($value)) {
                    $merchantData .= "&{$key}={$value}";
                }
            }

            // Validate required fields
            $requiredFields = ['order_id', 'amount', 'currency', 'redirect_url', 'cancel_url'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new \Exception("Required field '{$field}' is missing");
                }
            }

            \Log::info('Final merchant data:', [$merchantData]);
            \Log::info('Data being processed:', $data);

            // Generate payment URL
            $paymentUrl = $this->ccavenueService->generatePaymentUrl($merchantData);

            return response()->json([
                'success' => true,
                'message' => 'Payment URL generated successfully',
                'data' => [
                    'payment_url' => $paymentUrl,
                    'order_id' => $data['order_id'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency']
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('CCAvenue payment initiation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payment URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/ccavenue/handle-response",
     *     summary="Handle CCAvenue payment response",
     *     description="Process the encrypted response from CCAvenue after payment",
     *     operationId="handleResponse",
     *     tags={"CCAvenue"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"encResp"},
     *             @OA\Property(property="encResp", type="string", description="Encrypted response from CCAvenue")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment response processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment response processed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="order_status", type="string", example="Success"),
     *                 @OA\Property(property="order_id", type="string", example="ORD123456789"),
     *                 @OA\Property(property="tracking_id", type="string", example="123456789"),
     *                 @OA\Property(property="bank_ref_no", type="string", example="987654321"),
     *                 @OA\Property(property="amount", type="string", example="100.50"),
     *                 @OA\Property(property="currency", type="string", example="INR"),
     *                 @OA\Property(property="payment_mode", type="string", example="Credit Card"),
     *                 @OA\Property(property="status_message", type="string", example="Transaction Successful"),
     *                 @OA\Property(property="response_code", type="string", example="0"),
     *                 @OA\Property(property="trans_date", type="string", example="01/12/2023 10:30:45")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or missing encrypted response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid encrypted response")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to process payment response")
     *         )
     *     )
     * )
     */
    public function handleResponse(Request $request): JsonResponse
    {
        try {
            $encResponse = $request->input('encResp');

            if (empty($encResponse)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid encrypted response'
                ], 400);
            }

            // Decrypt and parse response
            $responseData = $this->ccavenueService->parseResponse($encResponse);

            // Determine payment status
            $orderStatus = $responseData['order_status'] ?? 'Unknown';
            $statusMessage = $this->getStatusMessage($orderStatus);

            return response()->json([
                'success' => true,
                'message' => 'Payment response processed successfully',
                'data' => array_merge($responseData, [
                    'status_message' => $statusMessage,
                    'is_success' => $orderStatus === 'Success'
                ])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment response',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/ccavenue/payment-status/{orderId}",
     *     summary="Get payment status by order ID",
     *     description="Check the status of a payment using order ID",
     *     operationId="getPaymentStatus", 
     *     tags={"CCAvenue"},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         required=true,
     *         description="Order ID to check status for",
     *         @OA\Schema(type="string", example="ORD123456789")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment status retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="order_id", type="string", example="ORD123456789"),
     *                 @OA\Property(property="status", type="string", example="Pending"),
     *                 @OA\Property(property="created_at", type="string", example="2023-12-01T10:30:45Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2023-12-01T10:30:45Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Order not found")
     *         )
     *     )
     * )
     */
    public function getPaymentStatus(string $orderId): JsonResponse
    {
        try {
            // Here you would typically query your database to get order status
            // This is a placeholder implementation

            return response()->json([
                'success' => true,
                'message' => 'Payment status retrieved successfully',
                'data' => [
                    'order_id' => $orderId,
                    'status' => 'Pending', // You would get this from your database
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get human-readable status message
     */
    private function getStatusMessage(string $status): string
    {
        switch ($status) {
            case 'Success':
                return 'Transaction completed successfully';
            case 'Aborted':
                return 'Transaction was aborted by user';
            case 'Failure':
                return 'Transaction failed';
            default:
                return 'Transaction status unknown';
        }
    }
    public function createCCavenuePaymentLink($order)
    {

        $customerAddress = CustomerAddress::find($order->customer_address_id);
        $customer = Customer::find($order->customer_id);
        $orderList = array();
        $orderList['order_id'] = $order->id;
        $orderList['redirect_url'] = url('/payment/success');
        $orderList['cancel_url'] = url('/payment/cancel');
        $orderList['currency'] = "AED";
        $orderList['amount'] = $order->amount;
        $orderList['language'] = "EN";
        $orderList['tax_percentage'] = $order->tax_percentage;
        $orderList['billing_city'] = $customerAddress->city;
        $orderList['billing_state'] = $customerAddress->state;
        $orderList['billing_country'] = $customerAddress->country;
        $orderList['billing_name'] = $customer->name;
        $orderList['billing_email'] = $customer->email;
        $orderList['delivery_tel'] = $customer->mobile_number;
        $orderList['delivery_zip'] = "";


        $allowedKeys = [
            'order_id',
            'currency',
            'amount',
            'redirect_url',
            'cancel_url',
            'language',
            'billing_name',
            'billing_address',
            'billing_city',
            'billing_state',
            'billing_zip',
            'billing_country',
            'billing_tel',
            'billing_email',
            'delivery_name',
            'delivery_address',
            'delivery_city',
            'delivery_state',
            'delivery_zip',
            'delivery_country',
            'delivery_tel',
            'merchant_param1',
            'merchant_param2',
            'merchant_param3',
            'merchant_param4',
            'merchant_param5',
            'promo_code',
            'customer_identifier'
        ];
        $merchantId = $this->ccavenueService->getMerchantId();

        // Build merchant data string - start with merchant_id
        $merchantData = "merchant_id={$merchantId}";

        foreach ($orderList as $key => $value) {
            if (in_array($key, $allowedKeys) && !empty($value)) {
                $merchantData .= "&{$key}={$value}";
            }
        }

        $requiredFields = ['order_id', 'amount', 'currency', 'redirect_url', 'cancel_url'];
        foreach ($requiredFields as $field) {
            if (empty($orderList[$field])) {
                throw new \Exception("Required field '{$field}' is missing");
            }
        }

        $paymentUrl = $this->ccavenueService->generatePaymentUrl($merchantData);
        return $paymentUrl;

    }


}