<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;
use Square\Models\OfflinePaymentDetails;
use Square\Exceptions\ApiException;

class SquarePaymentController extends Controller
{
    private $squareClient;

    public function __construct()
    {
        $this->squareClient = new SquareClient([
            'accessToken' => env('SQUARE_ACCESS_TOKEN'),
            'environment' => env('SQUARE_ENV', 'sandbox'),
        ]);
        
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
        // Validate input
        $request->validate([
            'nonce' => 'required|string',
            'amount' => 'required|numeric|min:0.5',
            'currency' => 'required|string|size:3',
            'customer_id' => 'required|string',
            'location_id' => 'required|string',
            'team_member_id' => 'required|string',
            'buyer_email_address' => 'required|email',
        ]);

        $nonce = $request->input('nonce');
        $amount = $request->input('amount');
        $currency = strtoupper($request->input('currency'));
        $customerId = $request->input('customer_id');
        $locationId = $request->input('location_id');
        $teamMemberId = $request->input('team_member_id');
        $buyerEmailAddress = $request->input('buyer_email_address');

        try {
            $paymentsApi = $this->squareClient->getPaymentsApi();

            // Create the Money object for the payment amount
            $amountMoney = new Money();
            $amountMoney->setAmount((int)($amount * 100)); // Convert amount to cents
            $amountMoney->setCurrency($currency);

            // Create Offline Payment Details (if applicable)
            $offlinePaymentDetails = new OfflinePaymentDetails();

            // Create the payment request with necessary parameters
            $paymentRequest = new CreatePaymentRequest(
                $nonce,  // Nonce
                uniqid('payment_')  // Idempotency key for uniqueness
            );

            // Set amount_money using the Money object
            $paymentRequest->setAmountMoney($amountMoney);

            // Set additional fields
            $paymentRequest->setCustomerId($customerId);
            $paymentRequest->setLocationId($locationId);
            $paymentRequest->setTeamMemberId($teamMemberId);
            $paymentRequest->setReferenceId(uniqid('ref_'));  // Optional: Unique reference ID
            $paymentRequest->setAcceptPartialAuthorization(true);  // Accept partial authorization if needed
            $paymentRequest->setBuyerEmailAddress($buyerEmailAddress);
            $paymentRequest->setOfflinePaymentDetails($offlinePaymentDetails); // If needed

            // Send the payment request to Square API
            $response = $paymentsApi->createPayment($paymentRequest);

            if ($response->isSuccess()) {
                return response()->json([
                    'success' => true,
                    'payment' => $response->getResult()->getPayment(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'errors' => $response->getErrors(),
                ], 400);
            }
        } catch (ApiException $e) {
            return response()->json([
                'success' => false,
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
            'square_application_id' => env('SQUARE_APPLICATION_ID'),
        ]);
    }
}