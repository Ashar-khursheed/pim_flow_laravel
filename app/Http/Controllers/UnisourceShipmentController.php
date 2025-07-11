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
     *             required={"billToType", "billToAccountNumber", "billToAddress", "priceDetail"},
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
     *             )
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
        $client = new Client();

        $apiKey = env('UNISOURCE_API_KEY');
        $apiUrl = rtrim(env('UNISOURCE_API_BASE_URL'), '/') . '/Shipments';

        $payload = $request->all();

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

        } catch (\Exception $e) {
            Log::error('Create shipment failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
     
}
