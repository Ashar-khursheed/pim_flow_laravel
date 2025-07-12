<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Exception\ClientException;
use Exception;


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
     * Debug endpoint to test payload without making API call
     */
    public function debugPayload(Request $request)
    {
        Log::info('Debug payload received:', $request->all());
        
        // Validate the request
        $validator = $this->validateRequest($request);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'debug_info' => [
                    'request_data' => $request->all(),
                    'stops_count' => count($request->get('stops', [])),
                    'stops_data' => $request->get('stops', [])
                ]
            ], 422);
        }

        // Transform the payload
        $payload = $this->transformPayload($request->all());
        
        return response()->json([
            'success' => true,
            'original_data' => $request->all(),
            'transformed_payload' => $payload,
            'validation_passed' => true
        ]);
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
     *                     @OA\Property(property="companyName", type="string", example="ABC Logistics"),
     *                     @OA\Property(property="streetAddress", type="string", example="123 Main St"),
     *                     @OA\Property(property="streetAddressTwo", type="string", example="Suite 200"),
     *                     @OA\Property(property="city", type="string", example="New York"),
     *                     @OA\Property(property="state", type="string", example="NY"),
     *                     @OA\Property(property="zipCode", type="string", example="10001"),
     *                     @OA\Property(property="country", type="string", example="USA"),
     *                     @OA\Property(property="contactName", type="string", example="John Doe"),
     *                     @OA\Property(property="phone", type="string", example="1234567890"),
     *                     @OA\Property(property="fax", type="string", example="1234567891"),
     *                     @OA\Property(property="email", type="string", example="john@abc.com"),
     *                     @OA\Property(property="instructions", type="string", example="Use loading dock"),
     *                     @OA\Property(property="notes", type="string", example="Fragile shipment"),
     *                     @OA\Property(property="referenceNumber", type="string", example="REF123"),
     *                     @OA\Property(property="estimatedReadyDateTime", type="string", format="date-time", example="2025-07-12T14:00:00Z"),
     *                     @OA\Property(property="estimatedCloseDateTime", type="string", format="date-time", example="2025-07-12T16:00:00Z"),
     *                     @OA\Property(property="appointmentReadyDateTime", type="string", format="date-time", example="2025-07-12T14:30:00Z"),
     *                     @OA\Property(property="appointmentCloseDateTime", type="string", format="date-time", example="2025-07-12T15:30:00Z"),
     *                     @OA\Property(property="actualArrivalDateTime", type="string", format="date-time", example="2025-07-12T15:00:00Z"),
     *                     @OA\Property(property="actualDepartureDateTime", type="string", format="date-time", example="2025-07-12T15:45:00Z"),
     *                     @OA\Property(property="stopType", type="string", example="Pickup"),
     *                     @OA\Property(property="shipmentStopReferenceNumbers", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="referenceType", type="string", example="Reference Number"),
     *                             @OA\Property(property="value", type="string", example="REF-999")
     *                         )
     *                     ),
     *                     @OA\Property(property="shipmentStopPickupCommodities", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="shipmentCommodityId", type="integer", example=1),
     *                             @OA\Property(property="pickupStopId", type="integer", example=1),
     *                             @OA\Property(property="deliveryStopId", type="integer", example=2)
     *                         )
     *                     ),
     *                     @OA\Property(property="shipmentStopDeliveryCommodities", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="shipmentCommodityId", type="integer", example=1),
     *                             @OA\Property(property="pickupStopId", type="integer", example=1),
     *                             @OA\Property(property="deliveryStopId", type="integer", example=2)
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="commodities", type="array",
     *                 @OA\Items(
     *                     required={"packagingType", "length", "width", "height", "weightTotal", "piecesTotal", "description"},
     *                     @OA\Property(property="shipmentCommodityId", type="integer", example=1),
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
        // Validate the request
        $validator = $this->validateRequest($request);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Transform the payload for Unisource API
            $payload = $this->transformPayload($request->all());
            
            // Log the payload for debugging
            Log::info('Unisource API Payload:', $payload);
            
            // Create HTTP client
            $client = new Client([
                'timeout' => 30,
                'verify' => false, // Set to true in production
            ]);

            // Make the API request
            $response = $client->post(env('UNISOURCE_API_BASE_URL') . '/Shipments', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => env('UNISOURCE_API_KEY')
                ],
                'json' => $payload
            ]);

            $responseData = json_decode($response->getBody(), true);
            
            // Log successful response
            Log::info('Unisource API Success Response:', $responseData);

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (ClientException $e) {
            // Handle client errors (4xx)
            $errorResponse = null;
            if ($e->hasResponse()) {
                $errorResponse = json_decode($e->getResponse()->getBody(), true);
            }
            
            Log::error('Unisource API Client Error:', [
                'status_code' => $e->getCode(),
                'message' => $e->getMessage(),
                'response' => $errorResponse
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Client error: ' . $e->getMessage(),
                'details' => $errorResponse
            ], $e->getCode());

        } catch (Exception $e) {
            // Handle server errors and other exceptions
            Log::error('Unisource API Server Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate the incoming request
     */
    private function validateRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Main shipment fields
            'customerReferenceNumber' => 'required|string|max:50',
            'carrierSCAC' => 'required|string|max:10',
            'customerId' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0',
            
            // Optional fields
            'customerStaffId' => 'nullable|integer|min:1',
            'tariffDescription' => 'nullable|string|max:100',
            'allowNewShipmentNotifications' => 'nullable|boolean',
            'isCommitted' => 'nullable|boolean',
            'rateShipment' => 'nullable|boolean',
            'mileage' => 'nullable|integer|min:0',
            'shipmentType' => 'nullable|string|max:50',
            'stackable' => 'nullable|boolean',
            'trailerType' => 'nullable|string|max:50',
            'trailerSize' => 'nullable|string|max:50',
            'weightUnits' => 'nullable|string|in:lbs,kg',
            'dimensionUnits' => 'nullable|string|in:in,cm',
            'serviceLevel' => 'nullable|string|max:50',
            'importExport' => 'nullable|string|in:Import,Export',
            
            // Stops validation
            'stops' => 'required|array|min:2',
            'stops.*.companyName' => 'required|string|max:100',
            'stops.*.streetAddress' => 'required|string|max:200',
            'stops.*.streetAddressTwo' => 'nullable|string|max:200',
            'stops.*.city' => 'required|string|max:100',
            'stops.*.state' => 'required|string|max:10',
            'stops.*.zipCode' => 'required|string|min:5|max:10',
            'stops.*.country' => 'required|string|max:50',
            'stops.*.contactName' => 'required|string|max:100',
            'stops.*.phone' => 'required|string|max:20',
            'stops.*.fax' => 'nullable|string|max:20',
            'stops.*.email' => 'required|email|max:100',
            'stops.*.instructions' => 'nullable|string|max:500',
            'stops.*.notes' => 'nullable|string|max:500',
            'stops.*.referenceNumber' => 'nullable|string|max:50',
            'stops.*.estimatedReadyDateTime' => 'nullable|date_format:Y-m-d\TH:i:s\Z',
            'stops.*.estimatedCloseDateTime' => 'nullable|date_format:Y-m-d\TH:i:s\Z',
            'stops.*.appointmentReadyDateTime' => 'nullable|date_format:Y-m-d\TH:i:s\Z',
            'stops.*.appointmentCloseDateTime' => 'nullable|date_format:Y-m-d\TH:i:s\Z',
            'stops.*.actualArrivalDateTime' => 'nullable|date_format:Y-m-d\TH:i:s\Z',
            'stops.*.actualDepartureDateTime' => 'nullable|date_format:Y-m-d\TH:i:s\Z',
            'stops.*.stopType' => 'required|string',
            'stops.*.shipmentStopReferenceNumbers' => 'nullable|array',
            'stops.*.shipmentStopReferenceNumbers.*.referenceType' => 'required_with:stops.*.shipmentStopReferenceNumbers|string|max:50',
            'stops.*.shipmentStopReferenceNumbers.*.value' => 'required_with:stops.*.shipmentStopReferenceNumbers|string|max:50',
            'stops.*.shipmentStopPickupCommodities' => 'nullable|array',
            'stops.*.shipmentStopPickupCommodities.*.shipmentCommodityId' => 'required_with:stops.*.shipmentStopPickupCommodities|integer|min:1',
            'stops.*.shipmentStopPickupCommodities.*.pickupStopId' => 'required_with:stops.*.shipmentStopPickupCommodities|integer|min:1',
            'stops.*.shipmentStopPickupCommodities.*.deliveryStopId' => 'required_with:stops.*.shipmentStopPickupCommodities|integer|min:1',
            'stops.*.shipmentStopDeliveryCommodities' => 'nullable|array',
            'stops.*.shipmentStopDeliveryCommodities.*.shipmentCommodityId' => 'required_with:stops.*.shipmentStopDeliveryCommodities|integer|min:1',
            'stops.*.shipmentStopDeliveryCommodities.*.pickupStopId' => 'required_with:stops.*.shipmentStopDeliveryCommodities|integer|min:1',
            'stops.*.shipmentStopDeliveryCommodities.*.deliveryStopId' => 'required_with:stops.*.shipmentStopDeliveryCommodities|integer|min:1',
            
            // Commodities validation
            'commodities' => 'required|array|min:1',
            'commodities.*.shipmentCommodityId' => 'nullable|integer|min:1',
            'commodities.*.handlingQuantity' => 'nullable|integer|min:1',
            'commodities.*.packagingType' => 'required|string|max:50',
            'commodities.*.length' => 'required|numeric|min:0.1',
            'commodities.*.width' => 'required|numeric|min:0.1',
            'commodities.*.height' => 'required|numeric|min:0.1',
            'commodities.*.weightTotal' => 'required|numeric|min:0.1',
            'commodities.*.hazardousMaterial' => 'nullable|boolean',
            'commodities.*.piecesTotal' => 'required|integer|min:1',
            'commodities.*.freightClass' => 'nullable|string|max:20',
            'commodities.*.description' => 'required|string|max:500',
        ]);

        // Custom validation for stops
        $validator->after(function ($validator) use ($request) {
            $stops = $request->get('stops', []);
            
            // Debug: Log the stops data
            Log::info('Stops validation debug:', [
                'stops_count' => count($stops),
                'stops_data' => $stops
            ]);
            
            // Check for at least one pickup and one delivery stop
            $hasPickup = false;
            $hasDelivery = false;
            
            foreach ($stops as $index => $stop) {
                $stopType = strtolower($stop['stopType'] ?? '');
                
                Log::info("Stop {$index} validation:", [
                    'original_stopType' => $stop['stopType'] ?? 'NOT_SET',
                    'lowercase_stopType' => $stopType
                ]);
                
                if (in_array($stopType, ['pickup', 'first pickup'])) {
                    $hasPickup = true;
                }
                
                if (in_array($stopType, ['delivery', 'final delivery'])) {
                    $hasDelivery = true;
                }
                
                // Validate zip code format (basic US zip code validation)
                if (isset($stop['zipCode']) && !preg_match('/^\d{5}(-\d{4})?$/', $stop['zipCode'])) {
                    $validator->errors()->add("stops.{$index}.zipCode", 'Invalid zip code format. Use 12345 or 12345-6789 format.');
                }
            }
            
            Log::info('Stop type validation results:', [
                'hasPickup' => $hasPickup,
                'hasDelivery' => $hasDelivery,
                'total_stops' => count($stops)
            ]);
            
            if (!$hasPickup) {
                $validator->errors()->add('stops', 'At least one pickup stop is required.');
            }
            
            if (!$hasDelivery) {
                $validator->errors()->add('stops', 'At least one delivery stop is required.');
            }
        });

        return $validator;
    }

    /**
     * Transform the payload to match Unisource API requirements
     */
  /**
 * Transform the payload to match Unisource API requirements
 */
private function transformPayload(array $data): array
{
    // Set default values
    $payload = [
        'customerReferenceNumber' => $data['customerReferenceNumber'],
        'customerStaffId' => $data['customerStaffId'] ?? 1,
        'tariffDescription' => $data['tariffDescription'] ?? 'Standard',
        'allowNewShipmentNotifications' => $data['allowNewShipmentNotifications'] ?? true,
        'isCommitted' => $data['isCommitted'] ?? true,
        'rateShipment' => $data['rateShipment'] ?? true,
        'carrierSCAC' => $data['carrierSCAC'],
        'amount' => (float) $data['amount'],
        'customerId' => (int) $data['customerId'],
        'mileage' => $data['mileage'] ?? 0,
        'shipmentType' => $data['shipmentType'] ?? 'Small Package',
        'stackable' => $data['stackable'] ?? true,
        'trailerType' => $data['trailerType'] ?? 'None',
        'trailerSize' => $data['trailerSize'] ?? 'Full',
        'weightUnits' => $data['weightUnits'] ?? 'lbs',
        'dimensionUnits' => $data['dimensionUnits'] ?? 'in',
        'serviceLevel' => $data['serviceLevel'] ?? 'Normal',
        'importExport' => $data['importExport'] ?? 'Import',
    ];

    // Transform stops with proper stopType mapping
    $payload['stops'] = [];
    foreach ($data['stops'] as $index => $stop) {
        // Map stopType values to what Unisource API expects
        $stopType = $this->mapStopType($stop['stopType']);
        
        $transformedStop = [
            'companyName' => $stop['companyName'],
            'streetAddress' => $stop['streetAddress'],
            'city' => $stop['city'],
            'state' => $stop['state'],
            'zipCode' => $stop['zipCode'],
            'country' => $stop['country'],
            'contactName' => $stop['contactName'],
            'phone' => $stop['phone'],
            'email' => $stop['email'],
            'stopType' => $stopType, // Use mapped stopType
        ];

        // Add optional fields if they exist
        $optionalFields = [
            'streetAddressTwo', 'fax', 'instructions', 'notes', 'referenceNumber',
            'estimatedReadyDateTime', 'estimatedCloseDateTime', 'appointmentReadyDateTime',
            'appointmentCloseDateTime', 'actualArrivalDateTime', 'actualDepartureDateTime',
            'shipmentStopReferenceNumbers', 'shipmentStopPickupCommodities', 'shipmentStopDeliveryCommodities'
        ];

        foreach ($optionalFields as $field) {
            if (isset($stop[$field]) && !empty($stop[$field])) {
                $transformedStop[$field] = $stop[$field];
            }
        }

        $payload['stops'][] = $transformedStop;
    }

    // Transform commodities
    $payload['commodities'] = [];
    foreach ($data['commodities'] as $commodity) {
        $transformedCommodity = [
            'shipmentCommodityId' => $commodity['shipmentCommodityId'] ?? 1,
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

        $payload['commodities'][] = $transformedCommodity;
    }

    return $payload;
}

/**
 * Map stopType values to what Unisource API expects
 */
private function mapStopType($stopType): string
{
    $stopType = strtolower(trim($stopType));
    
    $mapping = [
        'pickup' => 'Pickup',
        'first pickup' => 'First Pickup',
        'delivery' => 'Delivery', 
        'final delivery' => 'Final Delivery',
        'drop' => 'Drop',
        'stop' => 'Stop'
    ];
    
    return $mapping[$stopType] ?? ucwords($stopType);
}

    /**
     * Get shipment status
     */
    public function getShipmentStatus(Request $request, $shipmentId)
    {
        try {
            $client = new Client([
                'timeout' => 30,
                'verify' => false,
            ]);

            $response = $client->get(env('UNISOURCE_API_BASE_URL') . '/Shipments/' . $shipmentId, [
                'headers' => [
                    'Accept' => 'application/json',
                    'x-api-key' => env('UNISOURCE_API_KEY')
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => json_decode($response->getBody(), true)
            ]);

        } catch (ClientException $e) {
            $errorResponse = null;
            if ($e->hasResponse()) {
                $errorResponse = json_decode($e->getResponse()->getBody(), true);
            }

            return response()->json([
                'success' => false,
                'error' => 'Client error: ' . $e->getMessage(),
                'details' => $errorResponse
            ], $e->getCode());

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel shipment
     */
    public function cancelShipment(Request $request, $shipmentId)
    {
        try {
            $client = new Client([
                'timeout' => 30,
                'verify' => false,
            ]);

            $response = $client->delete(env('UNISOURCE_API_BASE_URL') . '/Shipments/' . $shipmentId, [
                'headers' => [
                    'Accept' => 'application/json',
                    'x-api-key' => env('UNISOURCE_API_KEY')
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => json_decode($response->getBody(), true)
            ]);

        } catch (ClientException $e) {
            $errorResponse = null;
            if ($e->hasResponse()) {
                $errorResponse = json_decode($e->getResponse()->getBody(), true);
            }

            return response()->json([
                'success' => false,
                'error' => 'Client error: ' . $e->getMessage(),
                'details' => $errorResponse
            ], $e->getCode());

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
