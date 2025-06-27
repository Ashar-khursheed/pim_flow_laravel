<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TamaraController extends Controller
{/**
 * Create Tamara checkout session
 *
 * @OA\Post(
 *     path="/api/frontend/tamara/checkout",
 *     summary="Create Tamara Checkout",
 *     tags={"Tamara"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="order_reference_id", type="string", example="ORDER-123456"),
 *             @OA\Property(property="order_number", type="string", example="INV-00123"),
 *             @OA\Property(
 *                 property="total_amount",
 *                 type="object",
 *                 @OA\Property(property="amount", type="number", example=100),
 *                 @OA\Property(property="currency", type="string", example="SAR")
 *             ),
 *             @OA\Property(
 *                 property="consumer",
 *                 type="object",
 *                 @OA\Property(property="first_name", type="string", example="John"),
 *                 @OA\Property(property="last_name", type="string", example="Doe"),
 *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
 *                 @OA\Property(property="phone_number", type="string", example="566027755")
 *             ),
 *             @OA\Property(property="country_code", type="string", example="SA"),
 *             @OA\Property(
 *                 property="items",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="name", type="string", example="Sample Item"),
 *                     @OA\Property(property="type", type="string", example="Physical"),
 *                     @OA\Property(property="reference_id", type="string", example="ref-123"),
 *                     @OA\Property(property="sku", type="string", example="SKU-001"),
 *                     @OA\Property(property="quantity", type="integer", example=1),
 *                     @OA\Property(
 *                         property="unit_price",
 *                         type="object",
 *                         @OA\Property(property="amount", type="number", example=100),
 *                         @OA\Property(property="currency", type="string", example="SAR")
 *                     ),
 *                     @OA\Property(
 *                         property="total_amount",
 *                         type="object",
 *                         @OA\Property(property="amount", type="number", example=100),
 *                         @OA\Property(property="currency", type="string", example="SAR")
 *                     ),
 *                     @OA\Property(
 *                         property="discount_amount",
 *                         type="object",
 *                         @OA\Property(property="amount", type="number", example=0),
 *                         @OA\Property(property="currency", type="string", example="SAR")
 *                     ),
 *                     @OA\Property(
 *                         property="tax_amount",
 *                         type="object",
 *                         @OA\Property(property="amount", type="number", example=0),
 *                         @OA\Property(property="currency", type="string", example="SAR")
 *                     )
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="merchant_url",
 *                 type="object",
 *                 @OA\Property(property="success", type="string", example="https://yourdomain.com/success"),
 *                 @OA\Property(property="failure", type="string", example="https://yourdomain.com/failure"),
 *                 @OA\Property(property="cancel", type="string", example="https://yourdomain.com/cancel"),
 *                 @OA\Property(property="notification", type="string", example="https://yourdomain.com/api/frontend/tamara/webhook")
 *             ),
 *             @OA\Property(property="platform", type="string", example="LaravelReact"),
 *             @OA\Property(property="is_mobile", type="boolean", example=false),
 *             @OA\Property(property="locale", type="string", example="en_SA")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Tamara checkout URL returned"),
 *     @OA\Response(response=500, description="Internal Server Error"),
 *     @OA\Response(response=400, description="Invalid Payload or Tamara API Error")
 * )
 */
public function createCheckout(Request $request)
{
    try {
        $data = $request->all();

        $tamaraResponse = Http::withToken(config('services.tamara.token'))
            ->post(config('services.tamara.url') . '/checkout', [
                "order_reference_id" => $data['order_reference_id'] ?? 'ORDER-' . uniqid(),
                "order_number" => $data['order_number'] ?? $data['order_reference_id'],
                "total_amount" => $data['total_amount'],
                "consumer" => $data['consumer'],
                "country_code" => $data['country_code'] ?? "SA",
                "items" => $data['items'],
                "merchant_url" => $data['merchant_url'],
                "platform" => $data['platform'] ?? "LaravelReact",
                "is_mobile" => $data['is_mobile'] ?? false,
                "locale" => $data['locale'] ?? "en_SA",
            ]);

        if ($tamaraResponse->successful()) {
            return response()->json([
                'checkout_url' => $tamaraResponse->json()['checkout_url']
            ]);
        } else {
            Log::error('Tamara API Error', [
                'status' => $tamaraResponse->status(),
                'response' => $tamaraResponse->body(),
            ]);

            return response()->json([
                'error' => 'Tamara API Error',
                'status' => $tamaraResponse->status(),
                'message' => json_decode($tamaraResponse->body(), true)
            ], $tamaraResponse->status());
        }
    } catch (\Exception $e) {
        Log::error('Internal Server Error', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
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
