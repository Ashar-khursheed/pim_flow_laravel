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
 *     summary="Create a shipment with Unisource-compliant payload",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"customerReferenceNumber", "carrierSCAC", "customerId", "amount", "stops", "commodities"},
 *             @OA\Property(property="customerReferenceNumber", type="string", example="REF123456"),
 *             @OA\Property(property="customerStaffId", type="integer", example=1),
 *             @OA\Property(property="tariffDescription", type="string", example="Standard"),
 *             @OA\Property(property="allowNewShipmentNotifications", type="boolean", example=true),
 *             @OA\Property(property="isCommitted", type="boolean", example=true),
 *             @OA\Property(property="rateShipment", type="boolean", example=true),
 *             @OA\Property(property="carrierSCAC", type="string", example="SCAC"),
 *             @OA\Property(property="amount", type="number", example=100),
 *             @OA\Property(property="customerId", type="integer", example=40),
 *             @OA\Property(property="mileage", type="integer", example=0),
 *             @OA\Property(property="shipmentType", type="string", example="Small Package"),
 *             @OA\Property(property="stackable", type="boolean", example=true),
 *             @OA\Property(property="trailerType", type="string", example="None"),
 *             @OA\Property(property="trailerSize", type="string", example="Full"),
 *             @OA\Property(property="weightUnits", type="string", example="lbs"),
 *             @OA\Property(property="dimensionUnits", type="string", example="in"),
 *             @OA\Property(property="serviceLevel", type="string", example="Normal"),
 *             @OA\Property(property="importExport", type="string", example="Import"),
 *             @OA\Property(property="stops", type="array",
 *                 @OA\Items(
 *                     required={"companyName", "streetAddress", "city", "state", "zipCode", "country", "contactName", "phone", "email", "stopType"},
 *                     @OA\Property(property="companyName", type="string"),
 *                     @OA\Property(property="streetAddress", type="string"),
 *                     @OA\Property(property="streetAddressTwo", type="string"),
 *                     @OA\Property(property="city", type="string"),
 *                     @OA\Property(property="state", type="string"),
 *                     @OA\Property(property="zipCode", type="string"),
 *                     @OA\Property(property="country", type="string"),
 *                     @OA\Property(property="contactName", type="string"),
 *                     @OA\Property(property="phone", type="string"),
 *                     @OA\Property(property="fax", type="string"),
 *                     @OA\Property(property="email", type="string"),
 *                     @OA\Property(property="instructions", type="string"),
 *                     @OA\Property(property="notes", type="string"),
 *                     @OA\Property(property="referenceNumber", type="string"),
 *                     @OA\Property(property="estimatedReadyDateTime", type="string", format="date-time"),
 *                     @OA\Property(property="estimatedCloseDateTime", type="string", format="date-time"),
 *                     @OA\Property(property="appointmentReadyDateTime", type="string", format="date-time"),
 *                     @OA\Property(property="appointmentCloseDateTime", type="string", format="date-time"),
 *                     @OA\Property(property="actualArrivalDateTime", type="string", format="date-time"),
 *                     @OA\Property(property="actualDepartureDateTime", type="string", format="date-time"),
 *                     @OA\Property(property="stopType", type="string", example="First Pickup"),
 *                     @OA\Property(property="shipmentStopReferenceNumbers", type="array",
 *                         @OA\Items(
 *                             @OA\Property(property="referenceType", type="string"),
 *                             @OA\Property(property="value", type="string")
 *                         )
 *                     ),
 *                     @OA\Property(property="shipmentStopPickupCommodities", type="array",
 *                         @OA\Items(
 *                             @OA\Property(property="shipmentCommodityId", type="integer"),
 *                             @OA\Property(property="pickupStopId", type="integer"),
 *                             @OA\Property(property="deliveryStopId", type="integer")
 *                         )
 *                     ),
 *                     @OA\Property(property="shipmentStopDeliveryCommodities", type="array",
 *                         @OA\Items(
 *                             @OA\Property(property="shipmentCommodityId", type="integer"),
 *                             @OA\Property(property="pickupStopId", type="integer"),
 *                             @OA\Property(property="deliveryStopId", type="integer")
 *                         )
 *                     )
 *                 )
 *             ),
 *             @OA\Property(property="commodities", type="array",
 *                 @OA\Items(
 *                     required={"packagingType", "length", "width", "height", "weightTotal", "piecesTotal", "description"},
 *                     @OA\Property(property="shipmentCommodityId", type="integer", example=0),
 *                     @OA\Property(property="handlingQuantity", type="integer", example=1),
 *                     @OA\Property(property="packagingType", type="string", example="Box"),
 *                     @OA\Property(property="length", type="number", example=12),
 *                     @OA\Property(property="width", type="number", example=10),
 *                     @OA\Property(property="height", type="number", example=8),
 *                     @OA\Property(property="weightTotal", type="number", example=15),
 *                     @OA\Property(property="hazardousMaterial", type="boolean", example=false),
 *                     @OA\Property(property="piecesTotal", type="integer", example=1),
 *                     @OA\Property(property="freightClass", type="string", example="92.5"),
 *                     @OA\Property(property="description", type="string", example="Box of electronics")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Shipment created successfully",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="data", type="object"))
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation Error",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="errors", type="object"))
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Server Error",
 *         @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="error", type="string"))
 *     )
 * )
 */
public function createShipment(Request $request)
{
    $validator = \Validator::make($request->all(), [
        'customerReferenceNumber' => 'required|string',
        'carrierSCAC' => 'required|string',
        'customerId' => 'required|integer',
        'amount' => 'required|numeric',
        'stops' => 'required|array|min:2',
        'stops.*.companyName' => 'required|string',
        'stops.*.streetAddress' => 'required|string',
        'stops.*.city' => 'required|string',
        'stops.*.state' => 'required|string',
        'stops.*.zipCode' => 'required|string',
        'stops.*.country' => 'required|string',
        'stops.*.contactName' => 'required|string',
        'stops.*.phone' => 'required|string',
        'stops.*.email' => 'required|email',
        'stops.*.stopType' => 'required|string',

        'commodities' => 'required|array|min:1',
        'commodities.*.packagingType' => 'required|string',
        'commodities.*.length' => 'required|numeric|min:0.1',
        'commodities.*.width' => 'required|numeric|min:0.1',
        'commodities.*.height' => 'required|numeric|min:0.1',
        'commodities.*.weightTotal' => 'required|numeric|min:0.1',
        'commodities.*.piecesTotal' => 'required|integer|min:1',
        'commodities.*.description' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $payload = $this->transformPayload($request->all());
        $client = new \GuzzleHttp\Client();

        $response = $client->post(env('UNISOURCE_API_BASE_URL') . '/Shipments', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => env('UNISOURCE_API_KEY')
            ],
            'json' => $payload
        ]);

        return response()->json([
            'success' => true,
            'data' => json_decode($response->getBody(), true)
        ]);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        return response()->json([
            'success' => false,
            'error' => 'Client error: ' . $e->getMessage(),
            'details' => json_decode($e->getResponse()->getBody(), true)
        ], $e->getCode());
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

private function transformPayload(array $data): array
{
    foreach ($data['commodities'] as &$commodity) {
        $commodity = [
            'shipmentCommodityId' => $commodity['shipmentCommodityId'] ?? 0,
            'handlingQuantity' => $commodity['handlingQuantity'] ?? 1,
            'packagingType' => $commodity['packagingType'],
            'length' => (float) $commodity['length'],
            'width' => (float) $commodity['width'],
            'height' => (float) $commodity['height'],
            'weightTotal' => (float) $commodity['weightTotal'],
            'hazardousMaterial' => $commodity['hazardousMaterial'] ?? false,
            'piecesTotal' => (int) $commodity['piecesTotal'],
            'freightClass' => $commodity['freightClass'] ?? 'No Class',
            'description' => $commodity['description']
        ];
    }

    // You can also clean/normalize stop subfields if needed
    return $data;
}

    


}
