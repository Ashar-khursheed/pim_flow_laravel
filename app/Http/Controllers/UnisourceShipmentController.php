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
     *     summary="Create a shipment with full payload",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"billToType", "billToAccountNumber", "billToAddress", "priceDetail", "originAddress", "destinationAddress", "commodities"},
     *             @OA\Property(property="billToType", type="string", example="Third"),
     *             @OA\Property(property="billToAccountNumber", type="string", example="123456"),
     *             @OA\Property(property="billToAddress", type="object",
     *                 @OA\Property(property="companyName", type="string", example="Company A"),
     *                 @OA\Property(property="streetAddress", type="string", example="123 Main St"),
     *                 @OA\Property(property="streetAddressTwo", type="string", example="Suite 4B"),
     *                 @OA\Property(property="city", type="string", example="New York"),
     *                 @OA\Property(property="state", type="string", example="NY"),
     *                 @OA\Property(property="zipCode", type="string", example="10001"),
     *                 @OA\Property(property="email", type="string", example="billing@company.com"),
     *                 @OA\Property(property="country", type="string", example="USA"),
     *                 @OA\Property(property="fax", type="string", example="123-456-7890"),
     *                 @OA\Property(property="phone", type="string", example="123-456-7890")
     *             ),
     *             @OA\Property(property="originAddress", type="object",
     *                 @OA\Property(property="companyName", type="string", example="Origin Company"),
     *                 @OA\Property(property="streetAddress", type="string", example="789 Origin St"),
     *                 @OA\Property(property="city", type="string", example="Origin City"),
     *                 @OA\Property(property="state", type="string", example="CA"),
     *                 @OA\Property(property="zipCode", type="string", example="90210"),
     *                 @OA\Property(property="email", type="string", example="origin@company.com"),
     *                 @OA\Property(property="country", type="string", example="USA"),
     *                 @OA\Property(property="phone", type="string", example="555-123-4567")
     *             ),
     *             @OA\Property(property="destinationAddress", type="object",
     *                 @OA\Property(property="companyName", type="string", example="Destination Company"),
     *                 @OA\Property(property="streetAddress", type="string", example="456 Destination Rd"),
     *                 @OA\Property(property="city", type="string", example="Los Angeles"),
     *                 @OA\Property(property="state", type="string", example="CA"),
     *                 @OA\Property(property="zipCode", type="string", example="90001"),
     *                 @OA\Property(property="email", type="string", example="destination@company.com"),
     *                 @OA\Property(property="country", type="string", example="USA"),
     *                 @OA\Property(property="phone", type="string", example="555-987-6543")
     *             ),
     *             @OA\Property(property="commodities", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="description", type="string", example="Box of electronics"),
     *                     @OA\Property(property="weight", type="number", example=15),
     *                     @OA\Property(property="pieceTotal", type="integer", example=1),
     *                     @OA\Property(property="length", type="number", example=12),
     *                     @OA\Property(property="width", type="number", example=10),
     *                     @OA\Property(property="height", type="number", example=8),
     *                     @OA\Property(property="declaredValue", type="number", example=100.00),
     *                     @OA\Property(property="commodityClass", type="string", example="92.5")
     *                 )
     *             ),
     *             @OA\Property(property="priceDetail", type="object",
     *                 @OA\Property(property="carrierSCAC", type="string"),
     *                 @OA\Property(property="carrierName", type="string"),
     *                 @OA\Property(property="tariffDescription", type="string"),
     *                 @OA\Property(property="transitTime", type="integer"),
     *                 @OA\Property(property="serviceLevel", type="string"),
     *                 @OA\Property(property="priceLineHaul", type="number"),
     *                 @OA\Property(property="priceFuelSurcharge", type="number"),
     *                 @OA\Property(property="priceAccessorials", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="accessorialCode", type="string"),
     *                         @OA\Property(property="accessorialPrice", type="number")
     *                     )
     *                 ),
     *                 @OA\Property(property="priceInsurance", type="number"),
     *                 @OA\Property(property="insuranceQuoteNumber", type="string"),
     *                 @OA\Property(property="priceTotal", type="number"),
     *                 @OA\Property(property="pricingInstructions", type="string"),
     *                 @OA\Property(property="usedLiabilityCoverage", type="number"),
     *                 @OA\Property(property="newLiabilityCoverage", type="number"),
     *                 @OA\Property(property="tsaCompliance", type="string"),
     *                 @OA\Property(property="apiQuoteNumber", type="string")
     *             ),
     *             @OA\Property(property="shipmentDate", type="string", format="date", example="2025-07-15"),
     *             @OA\Property(property="deliveryDate", type="string", format="date", example="2025-07-20"),
     *             @OA\Property(property="specialInstructions", type="string", example="Fragile - Handle with care")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function createShipment(Request $request)
    {
        // Validate the incoming request
        $validator = \Validator::make($request->all(), [
            'billToType' => 'required|string',
            'billToAccountNumber' => 'required|string',
            'billToAddress' => 'required|array',
            'billToAddress.companyName' => 'required|string',
            'billToAddress.streetAddress' => 'required|string',
            'billToAddress.city' => 'required|string',
            'billToAddress.state' => 'required|string',
            'billToAddress.zipCode' => 'required|string',
            'billToAddress.email' => 'required|email',
            'billToAddress.country' => 'required|string',
            'billToAddress.phone' => 'required|string',
            
            'originAddress' => 'required|array',
            'originAddress.companyName' => 'required|string',
            'originAddress.streetAddress' => 'required|string',
            'originAddress.city' => 'required|string',
            'originAddress.state' => 'required|string',
            'originAddress.zipCode' => 'required|string',
            'originAddress.email' => 'required|email',
            'originAddress.country' => 'required|string',
            'originAddress.phone' => 'required|string',
            
            'destinationAddress' => 'required|array',
            'destinationAddress.companyName' => 'required|string',
            'destinationAddress.streetAddress' => 'required|string',
            'destinationAddress.city' => 'required|string',
            'destinationAddress.state' => 'required|string',
            'destinationAddress.zipCode' => 'required|string',
            'destinationAddress.email' => 'required|email',
            'destinationAddress.country' => 'required|string',
            'destinationAddress.phone' => 'required|string',
            
            'commodities' => 'required|array|min:1',
            'commodities.*.description' => 'required|string',
            'commodities.*.weight' => 'required|numeric|min:0.1',
            'commodities.*.pieceTotal' => 'required|integer|min:1',
            'commodities.*.length' => 'required|numeric|min:0.1',
            'commodities.*.width' => 'required|numeric|min:0.1',
            'commodities.*.height' => 'required|numeric|min:0.1',
            'commodities.*.declaredValue' => 'nullable|numeric|min:0',
            'commodities.*.commodityClass' => 'nullable|string',
            
            'priceDetail' => 'required|array',
            'priceDetail.carrierSCAC' => 'required|string',
            'priceDetail.carrierName' => 'required|string',
            'priceDetail.priceTotal' => 'required|numeric|min:0',
            
            'shipmentDate' => 'nullable|date',
            'deliveryDate' => 'nullable|date',
            'specialInstructions' => 'nullable|string'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
    
        $client = new Client();
        
        $apiKey = env('UNISOURCE_API_KEY');
        $apiUrl = rtrim(env('UNISOURCE_API_BASE_URL'), '/') . '/Shipments';
    
        // Transform the payload if needed
        $payload = $this->transformPayload($request->all());
    
        try {
            $response = $client->post($apiUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $apiKey,
                ],
                'json' => $payload,
            ]);
    
            $data = json_decode($response->getBody(), true);
    
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
    
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            
            Log::error('Create shipment client error: ' . $e->getMessage(), [
                'response' => $responseBody,
                'payload' => $payload
            ]);
    
            return response()->json([
                'success' => false,
                'error' => 'Client error: ' . $e->getMessage(),
                'details' => json_decode($responseBody, true)
            ], $e->getResponse()->getStatusCode());
    
        } catch (\Exception $e) {
            Log::error('Create shipment failed: ' . $e->getMessage(), [
                'payload' => $payload
            ]);
    
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Transform the payload to match the expected API format
     */
    private function transformPayload(array $data): array
    {
        // Ensure commodities have correct field names
        if (isset($data['commodities'])) {
            foreach ($data['commodities'] as &$commodity) {
                // Transform piece_total to pieceTotal if needed
                if (isset($commodity['piece_total'])) {
                    $commodity['pieceTotal'] = $commodity['piece_total'];
                    unset($commodity['piece_total']);
                }
                
                // Ensure pieceTotal is at least 1
                if (!isset($commodity['pieceTotal']) || $commodity['pieceTotal'] < 1) {
                    $commodity['pieceTotal'] = 1;
                }
            }
        }
    
        return $data;
    }
     


}
