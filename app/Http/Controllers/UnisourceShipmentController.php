<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class UnisourceShipmentController extends Controller
{
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
     * @OA\Post(
     *     path="/api/unisource/create-shipment",
     *     tags={"Unisource"},
     *     security={{"bearerAuth":{}}},
     *     summary="Create a shipment",
     *     description="Creates a shipment using Unisource API with x-api-key",
     *     operationId="createShipmentWithApiKey",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "address", "city", "state", "zip", "order_id", "commodities"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="address", type="string", example="456 Destination Rd"),
     *             @OA\Property(property="city", type="string", example="Los Angeles"),
     *             @OA\Property(property="state", type="string", example="CA"),
     *             @OA\Property(property="zip", type="string", example="90001"),
     *             @OA\Property(property="order_id", type="string", example="ORDER-0001"),
     *             @OA\Property(
     *                 property="commodities",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"description", "weight", "piece_total"},
     *                     @OA\Property(property="description", type="string", example="Box of electronics"),
     *                     @OA\Property(property="weight", type="number", format="float", example=15),
     *                     @OA\Property(property="piece_total", type="integer", example=1)
     *                 )
     *             )
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
     *         response=500,
     *         description="Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="x-api-key",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Your Unisource API key"
     *     )
     * )
     */
    public function createShipment(Request $request)
    {
        // 1. Validate request input
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'zip' => 'required|string',
            'order_id' => 'required|string',
            'commodities' => 'required|array|min:1',
            'commodities.*.description' => 'required|string',
            'commodities.*.weight' => 'required|numeric|min:0.1',
            'commodities.*.piece_total' => 'required|integer|min:1',
        ]);
    
        // 2. Prepare commodities
        $commodities = $request->input('commodities', []);
        $formattedCommodities = [];
    
        foreach ($commodities as $item) {
            $formattedCommodities[] = [
                'Description'   => $item['description'],
                'WeightTotal'   => (float) $item['weight'],
                'PieceTotal'    => (int) $item['piece_total'],
                'PackageType'   => 'BOX',
                'WeightUnit'    => 'LBS',
                'FreightClass'  => '92.5',
                'Dimensions'    => [
                    'Length' => $item['length'] ?? 10,
                    'Width'  => $item['width'] ?? 10,
                    'Height' => $item['height'] ?? 10,
                    'Unit'   => 'IN',
                ]
            ];
        }
    
        // 3. Prepare full payload
        $payload = [
            'ShipmentDate' => now()->toIso8601String(),
            'CustomerReferenceNumber' => $request->input('order_id'),
            'OriginAddress' => [
                'CompanyName'   => 'Your Warehouse',
                'AddressLine1'  => '123 Origin St',
                'City'          => 'New York',
                'StateProvince' => 'NY',
                'PostalCode'    => '10001', // ✅ Make sure this is not null/empty
                'CountryCode'   => 'US',
            ],
            'DestinationAddress' => [
                'CompanyName'   => $request->input('name'),
                'AddressLine1'  => $request->input('address'),
                'City'          => $request->input('city'),
                'StateProvince' => $request->input('state'),
                'PostalCode'    => $request->input('zip'), // ✅ Make sure this is not null/empty
                'CountryCode'   => 'US',
            ],
            'Commodities' => $formattedCommodities
        ];
    
        // 4. Log payload to debug
        \Log::info('Taicloud payload:', $payload);
    
        // 5. Send to Taicloud
        try {
            $client = new \GuzzleHttp\Client();
    
            $response = $client->post(rtrim(env('UNISOURCE_API_BASE_URL'), '/') . '/Shipments', [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key'    => env('UNISOURCE_API_KEY'),
                ],
                'json' => $payload,
            ]);
    
            return response()->json([
                'success' => true,
                'data' => json_decode($response->getBody(), true),
            ]);
    
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $body = $e->getResponse()->getBody()->getContents();
            \Log::error('Taicloud error: ' . $body);
    
            return response()->json([
                'success' => false,
                'error' => json_decode($body, true) ?? $body,
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Unexpected error: ' . $e->getMessage());
    
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

     
}
