<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SquarePaymentController extends Controller
{
    private $squareClient;

    public function __construct()
    {
        try {
            $environment = config('services.square.environment', 'sandbox');
            $accessToken = config('services.square.access_token');
            
            if (!$accessToken) {
                throw new \Exception('Square access token not configured');
            }
            
            $this->squareClient = new SquareClient([
                'accessToken' => $accessToken,
                'environment' => $environment
            ]);
            
        } catch (\Exception $e) {
            Log::error('Square Client Initialization Failed:', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @OA\Post(
     *     path="/frontend/payment-square",
     *     operationId="createSquarePayment",
     *     tags={"Frontend-Payment"},
     *     summary="Create a payment using Square API",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nonce", "amount", "currency", "customer_id", "location_id", "team_member_id", "buyer_email_address"},
     *             @OA\Property(property="nonce", type="string", example="card_nonce_value"),
     *             @OA\Property(property="amount", type="number", format="float", example=10.50),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="customer_id", type="string"),
     *             @OA\Property(property="location_id", type="string"),
     *             @OA\Property(property="team_member_id", type="string"),
     *             @OA\Property(property="buyer_email_address", type="string", format="email", example="customer@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment successfully created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="payment", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation or payment error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function createPayment(Request $request)
    {
        // Debug: Log all incoming request data
        Log::info('Square Payment Request Data:', [
            'all_data' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method()
        ]);

        // Check if request is JSON and handle accordingly
        $requestData = $request->isJson() ? $request->json()->all() : $request->all();
        
        // Validate input
        $validator = Validator::make($requestData, [
            'nonce' => 'required|string',
            'amount' => 'required|numeric|min:0.5',
            'currency' => 'required|string|size:3',
            'customer_id' => 'nullable|string',
            'location_id' => 'required|string',
            'team_member_id' => 'nullable|string',
            'buyer_email_address' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            Log::error('Square Payment Validation Failed:', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $requestData
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Extract validated data
        $nonce = $requestData['nonce'];
        $amount = $requestData['amount'];
        $currency = strtoupper($requestData['currency']);
        $customerId = $requestData['customer_id'] ?? null;
        $locationId = $requestData['location_id'];
        $teamMemberId = $requestData['team_member_id'] ?? null;
        $buyerEmailAddress = $requestData['buyer_email_address'] ?? null;

        try {
            $paymentsApi = $this->squareClient->getPaymentsApi();

            // Create idempotency key
            $idempotencyKey = uniqid('payment_');

            // Create payment request
            $paymentRequest = new CreatePaymentRequest(
                $nonce,
                $idempotencyKey
            );

            // Create Money object for amount
            $money = new Money();
            $money->setAmount((int)($amount * 100)); // Convert to cents
            $money->setCurrency($currency);
            
            $paymentRequest->setAmountMoney($money);

            // Set optional fields
            if ($customerId) {
                $paymentRequest->setCustomerId($customerId);
            }
            
            if ($locationId) {
                $paymentRequest->setLocationId($locationId);
            }
            
            if ($teamMemberId) {
                $paymentRequest->setTeamMemberId($teamMemberId);
            }
            
            if ($buyerEmailAddress) {
                $paymentRequest->setBuyerEmailAddress($buyerEmailAddress);
            }

            // Set reference ID and other options
            $paymentRequest->setReferenceId(uniqid('ref_'));
            $paymentRequest->setAcceptPartialAuthorization(false);

            // Execute payment
            $response = $paymentsApi->createPayment($paymentRequest);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();
                
                Log::info('Square Payment Successful:', [
                    'payment_id' => $payment->getId(),
                    'amount' => $payment->getAmountMoney()->getAmount(),
                    'status' => $payment->getStatus()
                ]);
                
                return response()->json([
                    'success' => true,
                    'payment' => [
                        'id' => $payment->getId(),
                        'status' => $payment->getStatus(),
                        'amount' => $payment->getAmountMoney()->getAmount() / 100, // Convert back to dollars
                        'currency' => $payment->getAmountMoney()->getCurrency(),
                        'created_at' => $payment->getCreatedAt(),
                        'receipt_url' => $payment->getReceiptUrl(),
                    ],
                ]);
            } else {
                $errors = $response->getErrors();
                Log::error('Square Payment Failed:', [
                    'errors' => $errors
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed',
                    'errors' => $errors,
                ], 400);
            }
            
        } catch (ApiException $e) {
            Log::error('Square API Exception:', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'response_body' => $e->getResponseBody()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage(),
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('General Exception during payment:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

      /**
     * @OA\Get(
     *     path="/frontend/payment-form",
     *     operationId="paymentFormView",
     *     tags={"Frontend-Payment"},
     *     summary="Get payment form view",
     *     @OA\Response(
     *         response=200,
     *         description="Blade view with Square form"
     *     )
     * )
     */
    public function paymentForm()
    {
        return view('payment.form', [
            'square_application_id' => config('services.square.application_id'),
        ]);
    }
}