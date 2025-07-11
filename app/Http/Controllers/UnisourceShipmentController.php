<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class UnisourceShipmentController extends Controller
{
    /**
     * Create a new shipment via Unisource Taicloud API
     *
     * @OA\Post(
     *     path="/api/unisource/create-shipment",
     *     tags={"Unisource"},
     *     summary="Create a shipment",
     *     description="Creates a shipment through the Unisource Taicloud API using Basic Auth (username & password from .env)",
     *     operationId="createUnisourceShipment",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "address", "city", "state", "zip", "weight", "order_id"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="address", type="string", example="456 Destination Rd"),
     *             @OA\Property(property="city", type="string", example="Los Angeles"),
     *             @OA\Property(property="state", type="string", example="CA"),
     *             @OA\Property(property="zip", type="string", example="90001"),
     *             @OA\Property(property="weight", type="number", format="float", example=15),
     *             @OA\Property(property="order_id", type="string", example="ORDER-0001")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Shipment created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function createShipment(Request $request)
    {
        $client = new Client();

        $apiUrl = env('UNISOURCE_API_BASE_URL') . '/Shipments';

        // Load from .env
        $username = env('UNISOURCE_API_USERNAME');
        $password = env('UNISOURCE_API_PASSWORD');

        // Validate credentials
        if (!$username || !$password) {
            return response()->json([
                'success' => false,
                'error' => 'API credentials are missing in .env file.'
            ], 500);
        }

        $payload = [
            'ShipmentDate' => now()->toIso8601String(),
            'CustomerReferenceNumber' => $request->input('order_id', 'ORD-' . rand(1000, 9999)),
            'OriginAddress' => [
                'CompanyName' => 'Your Warehouse',
                'AddressLine1' => '123 Origin St',
                'City' => 'New York',
                'StateProvince' => 'NY',
                'PostalCode' => '10001',
                'CountryCode' => 'US',
            ],
            'DestinationAddress' => [
                'CompanyName' => $request->input('name'),
                'AddressLine1' => $request->input('address'),
                'City' => $request->input('city'),
                'StateProvince' => $request->input('state'),
                'PostalCode' => $request->input('zip'),
                'CountryCode' => 'US',
            ],
            'Commodities' => [
                [
                    'Description' => 'Goods',
                    'Weight' => [
                        'Value' => $request->input('weight', 10),
                        'Unit' => 'LBS'
                    ],
                    'Quantity' => 1
                ]
            ]
        ];

        try {
            $response = $client->post($apiUrl, [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode("{$username}:{$password}"),
                ],
                'json' => $payload
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
