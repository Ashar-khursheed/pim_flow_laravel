<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;
use App\Http\Requests\PaymentRequest;
use App\Services\CCAvenueService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\Customer;
use App\Models\PaymentManagement;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\FrontEnd\Order;
use App\Helpers\CcavenueHelper;
use Illuminate\Support\Facades\Http;


class CcavenueController extends Controller
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
    // public function initiatePayment(PaymentRequest $request): JsonResponse
    // {
    //     try {
    //         $data = $request->validated();
    //         $data['amount'] = number_format((float) $data['amount'], 2, '.', '');

    //         // Get merchant ID
    //         $merchantId = $this->ccavenueService->getMerchantId();

    //         // Set default values
    //         $data['language'] = $data['language'] ?? 'EN';

    //          $data['redirect_url'] = env('CCAVENUE_REDIRECT_URL');
    //         $data['cancel_url'] = env('CCAVENUE_CANCEL_URL');

    //         // Define allowed parameters for CCAvenue
    //         $allowedKeys = [
    //             'order_id',
    //             'currency',
    //             'amount',
    //             'redirect_url',
    //             'cancel_url',
    //             'language',
    //             'billing_name',
    //             'billing_address',
    //             'billing_city',
    //             'billing_state',
    //             'billing_zip',
    //             'billing_country',
    //             'billing_tel',
    //             'billing_email',
    //             'delivery_name',
    //             'delivery_address',
    //             'delivery_city',
    //             'delivery_state',
    //             'delivery_zip',
    //             'delivery_country',
    //             'delivery_tel',
    //             'merchant_param1',
    //             'merchant_param2',
    //             'merchant_param3',
    //             'merchant_param4',
    //             'merchant_param5',
    //             'promo_code',
    //             'customer_identifier'
    //         ];

    //         // Build merchant data string - start with merchant_id
    //         $merchantData = "merchant_id={$merchantId}";

    //         foreach ($data as $key => $value) {
    //             if (in_array($key, $allowedKeys) && !empty($value)) {
    //                 $merchantData .= "&{$key}={$value}";
    //             }
    //         }

    //         // Validate required fields
    //         $requiredFields = ['order_id', 'amount', 'currency', 'redirect_url', 'cancel_url'];
    //         foreach ($requiredFields as $field) {
    //             if (empty($data[$field])) {
    //                 throw new \Exception("Required field '{$field}' is missing");
    //             }
    //         }

    //         \Log::info('Final merchant data:', [$merchantData]);
    //         \Log::info('Data being processed:', $data);

    //         // Generate payment URL
    //         $paymentUrl = $this->ccavenueService->generatePaymentUrl($merchantData);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Payment URL generated successfully',
    //             'data' => [
    //                 'payment_url' => $paymentUrl,
    //                 'order_id' => $data['order_id'],
    //                 'amount' => $data['amount'],
    //                 'currency' => $data['currency']
    //             ]
    //         ]);

    //     } catch (\Exception $e) {
    //         \Log::error('CCAvenue payment initiation failed:', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to generate payment URL',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function initiatePayment(PaymentRequest $request): JsonResponse
{
    try {
        $data = $request->validated();
        $data['amount'] = number_format((float) $data['amount'], 2, '.', '');

        // Get merchant ID
        $merchantId = $this->ccavenueService->getMerchantId();

        // Set default values
        $data['language'] = $data['language'] ?? 'EN';        
        $url = config('app.url');
        $backendUrl = config('app.backend_url');
        // Set default values   
        $data['language'] = $data['language'] ?? 'EN';       
        //$data['redirect_url'] =  url('/api/frontend/ccavenue/handle-response');
        $data['redirect_url'] =  $backendUrl.'/api/frontend/ccavenue/handle-response';     
        $data['currency'] = "AED";
        $data['order_id'] =  rand(00000000,99999999);
        $data['cancel_url'] = $backendUrl.'/api/frontend/ccavenue/failed';
        $data['notify_url'] = $backendUrl . '/api/payment/ccavenue/notify';
        // Allowed parameters for CCAvenue
        $allowedKeys = [
            'order_id', 'currency', 'amount', 'redirect_url', 'cancel_url', 'language',
            'billing_name', 'billing_address', 'billing_city', 'billing_state', 'billing_zip',
            'billing_country', 'billing_tel', 'billing_email',
            'delivery_name', 'delivery_address', 'delivery_city', 'delivery_state', 'delivery_zip',
            'delivery_country', 'delivery_tel',
            'merchant_param1','merchant_param2','merchant_param3','merchant_param4','merchant_param5',
            'promo_code', 'customer_identifier'
        ];

        // Build merchant data string
        $merchantData = "merchant_id={$merchantId}";
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedKeys) && !empty($value)) {
                $merchantData .= "&{$key}={$value}";
            }
        }

        // Validate required fields
        // $requiredFields = ['order_id', 'amount', 'currency'];
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
                'order_id'    => $data['order_id'],
                'amount'      => $data['amount'],
                'currency'    => $data['currency']
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
    // public function handleResponse(Request $request)
    // {
    //     try {
    //         $encResponse = $request->input('encResp');

    //         if (empty($encResponse)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Invalid encrypted response'
    //             ], 400);
    //         }

    //         // Decrypt and parse response
    //         $responseData = $this->ccavenueService->parseResponse($encResponse);

    //         // Determine payment status
    //         $orderStatus = $responseData['order_status'] ?? 'Unknown';
    //         $statusMessage = $this->getStatusMessage($orderStatus);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Payment response processed successfully',
    //             'data' => array_merge($responseData, [
    //                 'status_message' => $statusMessage,
    //                 'is_success' => $orderStatus === 'Success'
    //             ])
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to process payment response',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function handleResponse(Request $request)
    // {
    //     try {
    //         $encResponse = $request->input('encResp');

    //         if (!$encResponse) {
    //             return redirect(env('CCAVENUE_REDIRECT_URL') . '/payment/failure'); // fallback redirect
    //         }

    //         // Decrypt CCAvenue response
    //         $responseData = $this->ccavenueService->parseResponse($encResponse);

    //         $orderStatus = $responseData['order_status'] ?? 'Failure';
    //         $orderId     = $responseData['order_id'] ?? null;

    //         if ($orderStatus === 'Success') {
    //             // Payment successful
    //             return redirect(env('CCAVENUE_REDIRECT_URL') . "/payment/success?order_id={$orderId}");
    //         } else {
    //             // Payment failed
    //             return redirect(env('CCAVENUE_REDIRECT_URL') . "/payment/declined?order_id={$orderId}");
    //         }

    //     } catch (\Exception $e) {
    //         return redirect(env('CCAVENUE_REDIRECT_URL') . '/payment/failure');
    //     }
    // }
    public function handleResponse(Request $request)
{
    try {
        $encResponse = $request->input('encResp');

        if (empty($encResponse)) {
            \Log::warning('CCAvenue handleResponse called without encResp', $request->all());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment status',
                'error' => 'CCAvenue handleResponse called without encResp'
            ], 500);
        }

        // decrypt and parse
        $responseData = $this->ccavenueService->parseResponse($encResponse);
 
        \Log::info('CCAvenue decrypted response', $responseData);

       
        $orderId      = $responseData['order_id'] ?? null;
        $status       = $responseData['order_status'] ?? ($responseData['status'] ?? 'Unknown');
        // $amount       = $responseData['amount'] ?? null;
        // $currency     = $responseData['currency'] ?? null;
        // $trackingId   = $responseData['tracking_id'] ?? $responseData['bank_ref_no'] ?? null;
        // $bankRefNo    = $responseData['bank_ref_no'] ?? null;
        // $paymentMode  = $responseData['payment_mode'] ?? null;
        // $responseCode = $responseData['status_code'] ?? null;
        // $statusMsg    = $responseData['status_message'] ?? null;
        //  $transDate    = now();

        // // card info (if provided, likely masked)
        // $cardBrand    = $responseData['card_name'] ?? $responseData['card_type'] ?? null;
        // $cardHolder   = $responseData['card_holder_name'] ?? null;
        // $maskedCard   = $responseData['card_number'] ?? $responseData['card_no'] ?? null; // usually masked

        // merchant params (if you used them)
        // $merchantParam1 = $responseData['merchant_param1'] ?? null;
        // ... merchant_param2..5
        if($status=='Success'){
        $status =  'Completed';
        }else{
        $status =  'Failed';

        }
        
        // PaymentManagement::updateOrInsert(
        //     ['order_id' => $orderId],
        //     [
        //         'order_id'      => $orderId,
        //         'status'        => $status,
        //         'payment_method'  => "ccavenue",
        //         'amount'        => $amount,               
        //         'transaction_id'   => $trackingId,            
        //         'payment_mode'  => $paymentMode,
        //         'payment_date' => now(),
        //         'notes'=> json_encode($responseData),                
        //     ]
        // );
            $merchantData = "";
            foreach ($responseData as $key => $value) {
                 $merchantData .= "&{$key}={$value}";
            }
        $giveData = dataEncodeJsonBase64($merchantData);
       
            // Determine payment status   
            $url = config('app.url');
            return redirect($url.'/review-checkout?status=compete&encResp='.$giveData);

    } catch (\Exception $e) {
         return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment status',
                'error' => $e->getMessage()
            ], 500);
        
        
    }
}
    public function failed(Request $request)
    {
        try {
            $encResponse = $request->input('encResp');
            if (empty($encResponse)) {
                \Log::warning('CCAvenue handleResponse called without encResp', $request->all());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve payment status',
                    'error' => 'CCAvenue handleResponse called without encResp'
                ], 500);
            }

            // decrypt and parse
            $responseData = $this->ccavenueService->parseResponse($encResponse);
    
            \Log::info('CCAvenue decrypted response', $responseData);        
            $status       = $responseData['order_status'] ?? ($responseData['status'] ?? 'Unknown');
        
            // ... merchant_param2..5
            if($status=='Success'){
            $status =  'Completed';
            }else{
            $status =  'Failed';

            }
            
            // PaymentManagement::updateOrInsert(
            //     ['order_id' => $orderId],
            //     [
            //         'order_id'      => $orderId,
            //         'status'        => $status,
            //         'payment_method'  => "ccavenue",
            //         'amount'        => $amount,               
            //         'transaction_id'   => $trackingId,            
            //         'payment_mode'  => $paymentMode,
            //         'payment_date' => now(),
            //         'notes'=> json_encode($responseData),                
            //     ]
            // );
                $merchantData = "";
                foreach ($responseData as $key => $value) {
                    $merchantData .= "&{$key}={$value}";
                }
            $giveData = dataEncodeJsonBase64($merchantData);
        
                // Determine payment status   
                $url = config('app.url');
                return redirect($url.'/review-checkout?status=incompete&encResp='.$giveData);

        } catch (\Exception $e) {
            return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve payment status',
                    'error' => $e->getMessage()
                ], 500);            
        }
    }
    // public function handleResponse(Request $request)
    // {
    //     try {
    //         $encResponse = $request->input('encResp');

    //         if (!$encResponse) {
    //             \Log::warning('CCAvenue handleResponse called without encResp', $request->all());
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No encrypted response received'
    //             ], 400);
    //         }

    //         // Decrypt the response
    //         $responseData = $this->ccavenueService->parseResponse($encResponse);

    //         \Log::info('CCAvenue decrypted response', $responseData);

    //         // Save all data in DB
    //         \DB::table('payments')->updateOrInsert(
    //             ['order_id' => $responseData['order_id']],
    //             [
    //                 'order_id'       => $responseData['order_id'] ?? null,
    //                 'status'         => $responseData['order_status'] ?? null,
    //                 'amount'         => $responseData['amount'] ?? null,
    //                 'currency'       => $responseData['currency'] ?? null,
    //                 'tracking_id'    => $responseData['tracking_id'] ?? null,
    //                 'bank_ref_no'    => $responseData['bank_ref_no'] ?? null,
    //                 'payment_mode'   => $responseData['payment_mode'] ?? null,
    //                 'card_brand'     => $responseData['card_type'] ?? null,
    //                 'card_number'    => $responseData['card_number'] ?? null,
    //                 'card_holder'    => $responseData['card_holder_name'] ?? null,
    //                 'trans_date'     => $responseData['trans_date'] ?? null,
    //                 'response_raw'   => json_encode($responseData),
    //                 'updated_at'     => now(),
    //                 'created_at'     => now()
    //             ]
    //         );

    //         // Optional: trigger order creation
    //         if (strtolower($responseData['order_status'] ?? '') === 'success') {
    //             $this->orderService->createOrderFromPayment($responseData);
    //         }

    //         // Return JSON to frontend (React) with all payment info
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Payment processed successfully',
    //             'data' => $responseData
    //         ]);

    //     } catch (\Exception $e) {
    //         \Log::error('CCAvenue handleResponse exception', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to process payment response',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }




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
        $url = config('app.url');
        $backendUrl = config('app.backend_url');
        $customerAddress = CustomerAddress::find($order->customer_address_id);
        $customer = Customer::find($order->customer_id);
        $orderList = array();
        $orderList['order_id'] = $order->order_number;
        $orderList['redirect_url'] = $url.'/thanks';
        $orderList['cancel_url'] = $url.'/failed';
        $orderList['notify_url'] = $backendUrl.'/api/payment/ccavenue/notify';
        $orderList['currency'] = "AED";
        $orderList['amount'] = $order->pending_amount;
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
            'webhook_url',
            'notify_url',
            'language'
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

    /**
     * @OA\Post(
     *     path="/api/ccavenue/webhook",
     *     summary="ccavenue Payment Success Redirect",
     *     tags={"CCAvenue"},
     *     @OA\Response(
     *         response=200,
     *         description="Payment was successful"
     *     )
     * )
     */
    public function successhandleWebhook(Request $request)
    {
        $workingKey = env('CCAVENUE_WORKING_KEY');
        $accessCode = env('CCAVENUE_ACCESS_CODE');
        \Log::error('CCAvenue Webhook Received', $request->all());

        $encResponse = $request->input('encResp');
     //   $encResponse = $request->encResp;
        //This is the response sent by the CCAvenue Server
        $rcvdString = CcavenueHelper::decrypt($encResponse, $workingKey);
        //Crypto Decryption used as per the specified working key.

        $order_status = "";
        $decryptValues = explode('&', $rcvdString);
        $dataSize = sizeof($decryptValues);

        for ($i = 0; $i < $dataSize; $i++) {
            $information = explode('=', $decryptValues[$i]);
            if ($i == 3)
                $order_status = $information[1];
        }

        if ($order_status === "Success") {
            $msg = "Thank you for registering with us. We will be sending you the registration slip very soon on your email id.";

        } else if ($order_status === "Aborted") {
            $msg = "Sorry, you have not been registered with us.However,the transaction has been declined & Security Error. Illegal access detected.";

        } else if ($order_status === "Failure") {
            $msg = "Sorry, you have not been registered with us.However,the transaction has been declined.";
        } else {
            $msg = "Sorry, you have not been registered with us.However,the transaction has been declined & Security Error. Illegal access detected " . $order_status;

        }




        $information = array();
        foreach ($decryptValues as $value) {
            $t = explode('=', $value);
            $information[$t[0]] = urldecode($t[1]);
        }
        $information = json_decode(json_encode($information));


        $status = $order_status;
        if (isset($information)) {


            $order = Order::where('order_number', $information->order_id)->first();
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }
            $status = $information->order_status;
            switch ($status) {
                case 'complete':
                case 'Success':
                case 'succeeded':
                    $status = "Completed";
                    break;
                case 'processing':
                    $status = "Pending";
                    break;
                case 'canceled':
                case 'failed':
                case 'expired':
                    $status = "Failed";
                    break;
                case 'Invalid':
                    $status = "Invalid";
                    break;
                default:
                    $status = "Pending";
            }
        }

        if ($information->order_status == 'Success') {
            if ($order->amount_total == $information->amount) {
                // Mark order as paid and remove payment link
                $order->update([
                    'is_paid' => true,
                    'paid_amount' => $order->paid_amount + $information->amount,
                    'pending_amount' => $order->pending_amount - $information->amount,
                    'payment_link' => null,
                    'is_reserved' => false,
                ]);
            } else {
                $order->update([
                    'is_paid' => false,
                    'paid_amount' => $order->paid_amount + $information->amount,
                    'pending_amount' => $order->pending_amount - $information->amount,
                    'payment_link' => null,
                    'is_reserved' => false,
                ]);

            }
        }

        PaymentManagement::create([
            'order_id' => $information->order_id,
            'transaction_id' => $information->tracking_id,
            'payment_mode' => $information->payment_mode,
            'payment_method' => 'ccavenue',
            'amount' => $information->amount,
            'status' => $status,
            'payment_date' => date('Y-m-d H:i:s'),
            'notes' => $information->status_message,
            'payment_details' => ''
        ]);

        if ($information) {

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => $information
            ]);

        } else {

            return response()->json([
                'success' => false,
                'message' => $msg,
                'data' => $information
            ]);
        }

    }

    /**
     * @OA\Post(
     *     path="/api/ccavenue/failed",
     *     summary="CCAvenue Payment Cancel Redirect",
     *     tags={"CCAvenue"},
     *     @OA\Response(
     *         response=200,
     *         description="Payment was cancelled"
     *     )
     * )
     */
    public function paymentFailed(Request $request)
    {
        $workingKey = env('CCAVENUE_WORKING_KEY');
        $accessCode = env('CCAVENUE_ACCESS_CODE');
        $encResponse = $request->encResp;


        //This is the response sent by the CCAvenue Server
        $rcvdString = CcavenueHelper::decrypt($encResponse, $workingKey);


        //Crypto Decryption used as per the specified working key.

        $order_status = "";
        $decryptValues = explode('&', $rcvdString);
        $dataSize = sizeof($decryptValues);

        for ($i = 0; $i < $dataSize; $i++) {
            $information = explode('=', $decryptValues[$i]);
            if ($i == 3)
                $order_status = $information[1];
        }

        if ($order_status === "Success") {
            $msg = "Thank you for registering with us. We will be sending you the registration slip very soon on your email id.";

        } else if ($order_status === "Aborted") {
            $msg = "Sorry, you have not been registered with us.However,the transaction has been declined & Security Error. Illegal access detected.";

        } else if ($order_status === "Failure") {
            $msg = "Sorry, you have not been registered with us.However,the transaction has been declined.";
        } else {
            $msg = "Sorry, you have not been registered with us.However,the transaction has been declined & Security Error. Illegal access detected " . $order_status;

        }




        $information = array();
        foreach ($decryptValues as $value) {
            $t = explode('=', $value);
            $information[$t[0]] = urldecode($t[1]);
        }
        $information = json_decode(json_encode($information));


        $status = $order_status;
        if (isset($information)) {


            $order = Order::where('id', $information->order_id)->first();
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }
            $status = $information->order_status;
            switch ($status) {
                case 'complete':
                case 'Success':
                case 'succeeded':
                    $status = "Completed";
                    break;
                case 'processing':
                    $status = "Pending";
                    break;
                case 'canceled':
                case 'failed':
                case 'expired':
                    $status = "Failed";
                    break;
                case 'Invalid':
                    $status = "Invalid";
                    break;
                default:
                    $status = "Pending";
            }

        }



        PaymentManagement::create([
            'order_id' => $information->order_id,
            'transaction_id' => $information->tracking_id,
            'payment_mode' => $information->payment_mode,
            'payment_method' => 'ccavenue',
            'amount' => $information->amount,
            'status' => $status,
            'payment_date' => date('Y-m-d H:i:s'),
            'notes' => $information->status_message,
            'payment_details' => ''
        ]);


        if ($information) {

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => $information
            ]);

        } else {

            return response()->json([
                'success' => false,
                'message' => $msg,
                'data' => $information
            ]);
        }

    }



        function dataEncodeJsonBase64($o){
            $o = json_encode($o);
            $o = base64_encode($o);
            return $o;
            }
        function dataDecodeJsonBase64($o){
            $o = base64_decode($o);
            $o = json_decode($o); 
            
            return $o;
        }

    /**
     * @OA\Post(
     *     path="/api/ccavenue/dataEncodeCCavenue",
     *     summary="CCAvenue Payment Cancel Redirect",
     *     tags={"CCAvenue"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="encResp",
     *                 type="string",
     *                 example="b2dwx821x92x0a78sd89asd7as8d7as8d7a..."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment was cancelled"
     *     )
     * )
     */

    public function dataEncodeCCavenue(Request $request)
    {

        $encResponse = $request->encResp;
        $data = dataDecodeJsonBase64(o: $encResponse);    
        return response()->json([
                'success' => true,
                'message' => 'Data has been successfully decrypted',
                'data' => $data
            ]);

    }

}