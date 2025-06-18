<?php
namespace App\Http\Controllers\FrontEnd;
use Illuminate\Http\Request;
use App\Helpers\CcavenueHelper;
use App\Http\Controllers\Controller;
class CcavenueController extends Controller
{
    private $accessCode;
    private $workingKey;
    private $merchantId;
    private $redirectUrl;

    public function __construct()
    {
        $this->accessCode = env('CCAVENUE_ACCESS_CODE');
        $this->workingKey = env('CCAVENUE_WORKING_KEY');
        $this->merchantId = env('CCAVENUE_MERCHANT_ID');
        $this->redirectUrl = env('CCAVENUE_REDIRECT_URL');
    }

    /**
     * @OA\Post(
     *     path="/frontend/ccavenue/payment",
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
        $orderId = uniqid();

        $postData = [
            "merchant_id" => $this->merchantId,
            "order_id" => $orderId,
            "currency" => "AED",
            "amount" => $request->amount,
            "redirect_url" => $this->redirectUrl,
            "cancel_url" => $this->redirectUrl,
            "language" => "EN",
            "billing_name" => $request->name ?? 'Test User',
            "billing_email" => $request->email ?? 'test@example.com',
            "billing_tel" => $request->phone ?? '9999999999',
            "billing_address" => $request->address ?? 'Test Address',
            "billing_city" => "Mumbai",
            "billing_state" => "MH",
            "billing_zip" => "400001",
            "billing_country" => "India",
        ];

        $merchantData = http_build_query($postData);
        $encryptedData = CcavenueHelper::encrypt($merchantData, $this->workingKey);

        return view('ccavenue.payment', [
            'encRequest' => $encryptedData,
            'accessCode' => $this->accessCode,
        ]);
    }

   /**
     * @OA\Post(
     *     path="/ccavenue/response",
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
        $encResponse = $request->input("encResp");
        $decryptedString = CcavenueHelper::decrypt($encResponse, $this->workingKey);

        parse_str($decryptedString, $output);

        if ($output['order_status'] === "Success") {
            return response()->json(['status' => 'success', 'data' => $output]);
        } else {
            return response()->json(['status' => 'failed', 'data' => $output]);
        }
    }
}
