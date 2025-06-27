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
     *             @OA\Property(property="amount", type="number", example=100),
     *             @OA\Property(property="currency", type="string", example="SAR"),
     *             @OA\Property(
     *                 property="consumer",
     *                 type="object",
     *                 @OA\Property(property="first_name", type="string"),
     *                 @OA\Property(property="last_name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone_number", type="string")
     *             ),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="sku", type="string"),
     *                     @OA\Property(property="quantity", type="integer"),
     *                     @OA\Property(property="unit_price", type="number")
     *                 )
     *             ),
     *             @OA\Property(property="success_url", type="string"),
     *             @OA\Property(property="failure_url", type="string"),
     *             @OA\Property(property="cancel_url", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Checkout URL"),
     *     @OA\Response(response=500, description="Tamara Checkout Failed")
     * )
     */
    public function createCheckout(Request $request)
    {
        try {
            $data = $request->all();

            $tamaraResponse = Http::withToken(config('services.tamara.token'))
                ->post(config('services.tamara.url') . '/checkout', [
                    "order_reference_id" => $data['order_reference_id'] ?? 'ORDER-' . uniqid(),
                   "total_amount" => $data['total_amount'],
                    "consumer" => $data['consumer'],
                    "country_code" => "SA",
                    "payment_type" => "PAY_BY_INSTALMENTS",
                    'items' => array_map(function ($item) {
                        return [
                            "name" => $item['name'],
                            "sku" => $item['sku'],
                            "quantity" => $item['quantity'],
                           "unit_price" => $item['unit_price'],
                        ];
                    }, $data['items']),
                    "success_url" => $data['success_url'],
                    "failure_url" => $data['failure_url'],
                    "cancel_url" => $data['cancel_url'],
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
