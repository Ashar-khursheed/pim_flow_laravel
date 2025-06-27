<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TamaraController extends Controller
{
  /**
     * Create Tamara checkout session
     *
     * @OA\Post(
     *     path="/api/frontend/tamara/checkout",
     *     summary="Create Tamara Checkout",
     *     tags={"Tamara"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="order_reference_id", type="string", example="abd12331-a123-1234-4567-fbde34ae"),
     *             @OA\Property(property="order_number", type="string", example="A123125"),
     *             @OA\Property(property="total_amount", type="object",
     *                 @OA\Property(property="amount", type="number", example=300),
     *                 @OA\Property(property="currency", type="string", example="SAR")
     *             ),
     *             @OA\Property(property="shipping_amount", type="object",
     *                 @OA\Property(property="amount", type="number", example=1),
     *                 @OA\Property(property="currency", type="string", example="SAR")
     *             ),
     *             @OA\Property(property="tax_amount", type="object",
     *                 @OA\Property(property="amount", type="number", example=1),
     *                 @OA\Property(property="currency", type="string", example="SAR")
     *             ),
     *             @OA\Property(property="discount", type="object",
     *                 @OA\Property(property="name", type="string", example="Voucher A"),
     *                 @OA\Property(property="amount", type="object",
     *                     @OA\Property(property="amount", type="number", example=0),
     *                     @OA\Property(property="currency", type="string", example="SAR")
     *                 )
     *             ),
     *             @OA\Property(property="items", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string", example="Lego City 8601"),
     *                     @OA\Property(property="type", type="string", example="Digital"),
     *                     @OA\Property(property="reference_id", type="string", example="123"),
     *                     @OA\Property(property="sku", type="string", example="SA-12436"),
     *                     @OA\Property(property="quantity", type="integer", example=1),
     *                     @OA\Property(property="unit_price", type="object",
     *                         @OA\Property(property="amount", type="number", example=490),
     *                         @OA\Property(property="currency", type="string", example="SAR")
     *                     ),
     *                     @OA\Property(property="total_amount", type="object",
     *                         @OA\Property(property="amount", type="number", example=100),
     *                         @OA\Property(property="currency", type="string", example="SAR")
     *                     ),
     *                     @OA\Property(property="discount_amount", type="object",
     *                         @OA\Property(property="amount", type="number", example=100),
     *                         @OA\Property(property="currency", type="string", example="SAR")
     *                     ),
     *                     @OA\Property(property="tax_amount", type="object",
     *                         @OA\Property(property="amount", type="number", example=10),
     *                         @OA\Property(property="currency", type="string", example="SAR")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="consumer", type="object",
     *                 @OA\Property(property="first_name", type="string", example="Mona"),
     *                 @OA\Property(property="last_name", type="string", example="Lisa"),
     *                 @OA\Property(property="email", type="string", example="customer@email.com"),
     *                 @OA\Property(property="phone_number", type="string", example="566027755")
     *             ),
     *             @OA\Property(property="country_code", type="string", example="SA"),
     *             @OA\Property(property="description", type="string", example="Enter order description here."),
     *             @OA\Property(property="merchant_url", type="object",
     *                 @OA\Property(property="success", type="string", example="http://example.com/#/success"),
     *                 @OA\Property(property="failure", type="string", example="http://example.com/#/fail"),
     *                 @OA\Property(property="cancel", type="string", example="http://example.com/#/cancel"),
     *                 @OA\Property(property="notification", type="string", example="https://example.com/notifications")
     *             ),
     *             @OA\Property(property="billing_address", type="object",
     *                 @OA\Property(property="city", type="string", example="Riyadh"),
     *                 @OA\Property(property="country_code", type="string", example="SA"),
     *                 @OA\Property(property="first_name", type="string", example="Mona"),
     *                 @OA\Property(property="last_name", type="string", example="Lisa"),
     *                 @OA\Property(property="line1", type="string", example="3764 Al Urubah Rd"),
     *                 @OA\Property(property="line2", type="string", example="string"),
     *                 @OA\Property(property="phone_number", type="string", example="532298658"),
     *                 @OA\Property(property="region", type="string", example="As Sulimaniyah")
     *             ),
     *                  @OA\Property(property="shipping_address", type="object",
     *                     @OA\Property(property="city", type="string", example="Riyadh"),
     *                     @OA\Property(property="country_code", type="string", example="SA"),
     *                     @OA\Property(property="first_name", type="string", example="Mona"),
     *                      @OA\Property(property="last_name", type="string", example="Lisa"),
     *                     @OA\Property(property="line1", type="string", example="3764 Al Urubah Rd"),
     *                     @OA\Property(property="line2", type="string", example="string"),
     *                      @OA\Property(property="phone_number", type="string", example="532298658"),
     *                      @OA\Property(property="region", type="string", example="As Sulimaniyah")
     *                  ),
     *             @OA\Property(property="platform", type="string", example="LaravelReact"),
     *             @OA\Property(property="is_mobile", type="boolean", example=false),
     *             @OA\Property(property="locale", type="string", example="ar_SA"),
     *             @OA\Property(property="risk_assessment", type="object",
     *                 @OA\Property(property="customer_age", type="integer", example=21),
     *                 @OA\Property(property="customer_dob", type="string", example="01-12-2000"),
     *                 @OA\Property(property="customer_gender", type="string", example="Female"),
     *                 @OA\Property(property="customer_nationality", type="string", example="SA"),
     *                 @OA\Property(property="is_premium_customer", type="boolean", example=false),
     *                 @OA\Property(property="is_existing_customer", type="boolean", example=false),
     *                 @OA\Property(property="is_guest_user", type="boolean", example=false),
     *                 @OA\Property(property="account_creation_date", type="string", example="12-06-2020"),
     *                 @OA\Property(property="platform_account_creation_date", type="string", example="12-06-2020"),
     *                 @OA\Property(property="date_of_first_transaction", type="string", example="12-06-2020"),
     *                 @OA\Property(property="is_card_on_file", type="boolean", example=false),
     *                 @OA\Property(property="is_COD_customer", type="boolean", example=false),
     *                 @OA\Property(property="has_delivered_order", type="boolean", example=true),
     *                 @OA\Property(property="is_phone_verified", type="boolean", example=false),
     *                 @OA\Property(property="is_fraudulent_customer", type="boolean", example=false),
     *                 @OA\Property(property="total_ltv", type="number", example=200),
     *                 @OA\Property(property="total_order_count", type="integer", example=15),
     *                 @OA\Property(property="order_amount_last3months", type="number", example=2000),
     *                 @OA\Property(property="order_count_last3months", type="integer", example=10),
     *                 @OA\Property(property="last_order_date", type="string", example="12-06-2020"),
     *                 @OA\Property(property="last_order_amount", type="number", example=2000),
     *                 @OA\Property(property="reward_program_enrolled", type="boolean", example=false),
     *                 @OA\Property(property="reward_program_points", type="number", example=2000)
     *             ),
     *             @OA\Property(property="additional_data", type="object",
     *                 @OA\Property(property="delivery_method", type="string", example="Home Delivery"),
     *                 @OA\Property(property="pickup_store", type="string", example="Store A"),
     *                 @OA\Property(property="store_code", type="string", example="Branch A"),
     *                 @OA\Property(property="vendor_amount", type="number", example=0),
     *                 @OA\Property(property="merchant_settlement_amount", type="number", example=0),
     *                 @OA\Property(property="vendor_reference_code", type="string", example="AZ1234")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Tamara checkout URL returned"),
     *     @OA\Response(response=400, description="Bad Request or Invalid Payload"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function createCheckout(Request $request)
    {
        try {
            $data = $request->all();
    
            $response = Http::withToken(config('services.tamara.token'))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post(config('services.tamara.url') . '/checkout', [
                    "order_reference_id" => $data['order_reference_id'],
                    "order_number" => $data['order_number'],
                    "total_amount" => $data['total_amount'],
                    "shipping_amount" => $data['shipping_amount'] ?? ["amount" => 0, "currency" => "SAR"],
                    "tax_amount" => $data['tax_amount'] ?? ["amount" => 0, "currency" => "SAR"],
                    "discount" => $data['discount'] ?? ["name" => "None", "amount" => ["amount" => 0, "currency" => "SAR"]],
                    "items" => $data['items'],
                    "consumer" => $data['consumer'],
                    "country_code" => $data['country_code'],
                    "merchant_url" => $data['merchant_url'],
                    "billing_address" => $data['billing_address'] ?? [],
                    "shipping_address" => $data['shipping_address'] ?? [],
                    "description" => $data['description'] ?? 'Tamara Order',
                    "platform" => $data['platform'] ?? 'LaravelReact',
                    "is_mobile" => $data['is_mobile'] ?? false,
                    "locale" => $data['locale'] ?? 'ar_SA',
                    "risk_assessment" => $data['risk_assessment'] ?? [],
                    "additional_data" => $data['additional_data'] ?? []
                ]);
    
            if ($response->successful()) {
                return response()->json([
                    'checkout_url' => $response->json()['checkout_url']
                ]);
            }
    
            Log::error('Tamara API Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
    
            return response()->json([
                'error' => 'Tamara API Error',
                'status' => $response->status(),
                'message' => json_decode($response->body(), true)
            ], $response->status());
    
        } catch (\Exception $e) {
            Log::error('Tamara Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    



    /**
     * Handle Tamara Webhook
     *
     * @OA\Post(
     *     path="/api/frontend/tamara/webhook",
     *     summary="Tamara Webhook Listener",
     *     tags={"Tamara"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="order_reference_id", type="string"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="reason", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Webhook received"),
     *     @OA\Response(response=401, description="Unauthorized webhook")
     * )
     */
    public function handleWebhook(Request $request)
    {
        $token = $request->header('Authorization');

        if ($token !== env('TAMARA_NOTIFICATION_TOKEN')) {
            Log::warning('Unauthorized Tamara webhook attempt', ['token' => $token]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->all();

        Log::info('Tamara webhook received', $data);

        // Handle order update logic here (e.g., mark order as paid, failed, etc.)

        return response()->json(['message' => 'Webhook received']);
    }
}
