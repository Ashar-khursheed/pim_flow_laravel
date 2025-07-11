<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class UnisourceShipmentController extends Controller
{
    /**
     * Create a new shipment with Unisource API
     *
     * @OA\Post(
     *     path="/api/unisource/create-shipment",
     *     operationId="createUnisourceShipment",
     *     tags={"Unisource"},
     *     security={{"bearerAuth":{}}},
     *     summary="Create shipment via Unisource API",
     *     description="Creates a new shipment using Unisource API.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id", "name", "address", "city", "state", "zip", "phone", "weight", "length", "width", "height"},
     *             @OA\Property(property="order_id", type="string", example="ORD-123456"),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="address", type="string", example="123 Main Street"),
     *             @OA\Property(property="city", type="string", example="New York"),
     *             @OA\Property(property="state", type="string", example="NY"),
     *             @OA\Property(property="zip", type="string", example="10001"),
     *             @OA\Property(property="phone", type="string", example="1234567890"),
     *             @OA\Property(property="weight", type="number", format="float", example=5.0),
     *             @OA\Property(property="length", type="number", format="float", example=10.0),
     *             @OA\Property(property="width", type="number", format="float", example=5.0),
     *             @OA\Property(property="height", type="number", format="float", example=4.0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment successfully created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error creating shipment",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Authentication failed")
     *         )
     *     )
     * )
     */
    public function createShipment(Request $request)
    {
        $client = new Client();

        $apiUrl = env('UNISOURCE_API_BASE_URL', 'https://api.unisourceworldwide.com/v1/shipments');
        $apiKey = env('UNISOURCE_API_KEY');

        $payload = [
            'order_id' => $request->input('order_id'),
            'recipient' => [
                'name'    => $request->input('name'),
                'address' => $request->input('address'),
                'city'    => $request->input('city'),
                'state'   => $request->input('state'),
                'zip'     => $request->input('zip'),
                'phone'   => $request->input('phone'),
            ],
            'package' => [
                'weight' => $request->input('weight'),
                'dimensions' => [
                    'length' => $request->input('length'),
                    'width'  => $request->input('width'),
                    'height' => $request->input('height'),
                ],
            ],
            // Add more fields as per Unisource API
        ];

        try {
            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody(), true);

            return response()->json([
                'success' => true,
                'data' => $body
            ]);

        } catch (\Exception $e) {
            Log::error('Unisource Shipment Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
