<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class UnisourceShipmentController extends Controller
{   
     /**
    * Authenticate with Unisource API
    *
    * @OA\Post(
    *     path="/api/unisource/authenticate",
    *     tags={"Unisource"},
    *     summary="Authenticate with Unisource API",
    *     description="Logs in with Unisource credentials and returns an access token.",
    *     operationId="authenticateWithUnisource",
    *     @OA\RequestBody(
    *         required=false,
    *         @OA\JsonContent()
    *     ),
    *     @OA\Response(
    *         response=200,
    *         description="Token retrieved successfully",
    *         @OA\JsonContent(
    *             @OA\Property(property="success", type="boolean", example=true),
    *             @OA\Property(property="token", type="string", example="eyJhbGciOiJIUz...")
    *         )
    *     ),
    *     @OA\Response(
    *         response=401,
    *         description="Unauthorized",
    *         @OA\JsonContent(
    *             @OA\Property(property="success", type="boolean", example=false),
    *             @OA\Property(property="error", type="string", example="Invalid credentials")
    *         )
    *     )
    * )
    */

    public function authenticateWithUnisource()
    {
        $client = new \GuzzleHttp\Client();

        $username = env('UNISOURCE_API_USERNAME');
        $password = env('UNISOURCE_API_PASSWORD');

        try {
            $response = $client->post('https://unisourceshipping.taicloud.net/PublicApi/Account/Login', [
                'json' => [
                    'username' => $username,
                    'password' => $password
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            return $data['token'] ?? null;

        } catch (\Exception $e) {
            \Log::error('Unisource Login Error: ' . $e->getMessage());
            return null;
        }
    }

        /**
     * Create a shipment in Unisource API
     *
     * @OA\Post(
     *     path="/api/unisource/create-shipment",
     *     tags={"Unisource"},
     *     summary="Create a shipment",
     *     description="Creates a shipment using the Unisource Taicloud API with bearer token authorization.",
     *     operationId="createShipment",
     *     security={{ "bearerAuth": {} }},
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
     *             @OA\Property(property="weight", type="number", example=15),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="API error message")
     *         )
     *     )
     * )
     */

    public function createShipment(Request $request)
    {
        $token = $this->authenticateWithUnisource();
    
        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to authenticate with Unisource API.'
            ], 401);
        }
    
        $client = new \GuzzleHttp\Client();
        $apiUrl = env('UNISOURCE_API_BASE_URL') . '/Shipments';
    
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
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'json' => $payload,
            ]);
    
            $body = json_decode($response->getBody(), true);
    
            return response()->json([
                'success' => true,
                'data' => $body,
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Unisource Shipment Error: ' . $e->getMessage());
    
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
}
