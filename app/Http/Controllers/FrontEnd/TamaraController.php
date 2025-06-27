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
     *             @OA\Property(property="order_reference_id", type="string"),
     *             @OA\Property(property="order_number", type="string"),
     *             @OA\Property(
     *                 property="total_amount",
     *                 type="object",
     *                 @OA\Property(property="amount", type="number", example=100),
     *                 @OA\Property(property="currency", type="string", example="SAR")
     *             ),
     *             @OA\Property(
     *                 property="consumer",
     *                 type="object",
     *                 @OA\Property(property="first_name", type="string"),
     *                 @OA\Property(property="last_name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone_number", type="string")
     *             ),
     *             @OA\Property(property="country_code", type="string", example="SA"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="reference_id", type="string"),
     *                     @OA\Property(property="sku", type="string"),
     *                     @OA\Property(property="quantity", type="integer"),
     *                     @OA\Property(
     *                         property="unit_price",
     *                         type="object",
     *                         @OA\Property(property="amount", type="number"),
     *                         @OA\Property(property="currency", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="total_amount",
     *                         type="object",
     *                         @OA\Property(property="amount", type="number"),
     *                         @OA\Property(property="currency", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="discount_amount",
     *                         type="object",
     *                         @OA\Property(property="amount", type="number"),
     *                         @OA\Property(property="currency", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="tax_amount",
     *                         type="object",
     *                         @OA\Property(property="amount", type="number"),
     *                         @OA\Property(property="currency", type="string")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="merchant_url",
     *                 type="object",
     *                 @OA\Property(property="success", type="string"),
     *                 @OA\Property(property="failure", type="string"),
     *                 @OA\Property(property="cancel", type="string"),
     *                 @OA\Property(property="notification", type="string")
     *             ),
     *             @OA\Property(property="platform", type="string"),
     *             @OA\Property(property="is_mobile", type="boolean"),
     *             @OA\Property(property="locale", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Tamara checkout URL returned"),
     *     @OA\Response(response=500, description="Internal Server Error"),
     *     @OA\Response(response=400, description="Bad Request or Invalid Payload")
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
                    "consumer" => $data['consumer'],
                    "country_code" => $data['country_code'],
                    "items" => $data['items'],
                    "merchant_url" => $data['merchant_url'],
                    "platform" => $data['platform'] ?? 'LaravelReact',
                    "is_mobile" => $data['is_mobile'] ?? false,
                    "locale" => $data['locale'] ?? 'en_SA'
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
