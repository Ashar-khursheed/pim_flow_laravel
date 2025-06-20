<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;

class SquarePaymentController extends Controller
{
    private $squareClient;

    public function __construct()
    {
        $environment = env('SQUARE_ENV', 'sandbox');
        $this->squareClient = new SquareClient(env('SQUARE_ACCESS_TOKEN'), $environment);
    }

    public function createPayment(Request $request)
    {
        // Debug: Log all incoming request data
        Log::info('Square Payment Request Data:', [
            'all_data' => $request->all(),
            'json_data' => $request->json()->all(),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method()
        ]);

        // Check if request is JSON and handle accordingly
        $requestData = $request->isJson() ? $request->json()->all() : $request->all();
        
        // Validate input with more flexible handling
        $validator = validator($requestData, [
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
                'received_data' => $requestData // Include for debugging
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
            $paymentsApi = $this->squareClient->payments;

            $paymentRequest = new CreatePaymentRequest(
                $nonce,
                uniqid('payment_')
            );

            $paymentRequest->setAmountMoney([
                'amount' => (int)($amount * 100),
                'currency' => $currency
            ]);

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

            $paymentRequest->setReferenceId(uniqid('ref_'));
            $paymentRequest->setAcceptPartialAuthorization(false);

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
            Log::error('Square API Exception:', [
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('General Exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function paymentForm()
    {
        return view('payment.form', [
            'square_application_id' => env('SQUARE_APPLICATION_ID'),
        ]);
    }
}