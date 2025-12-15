<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TqlRateService;
use Illuminate\Support\Facades\Http;
 
use Illuminate\Support\Facades\Validator;

class TqlRateController extends Controller
{
     
 /**
 * @OA\Post(
 *     path="/api/frontend/tql-token",
 *     summary="Get TQL shipping rate (returns only the price amount)",
 *     tags={"Frontend-TQL Rates Shipping"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="carrierPriceId", type="string", example="7654433"),
 *             @OA\Property(property="customerEmailAddresses", type="string", example="name@gmail.com"),
 *             @OA\Property(property="pickLocationType", type="string", example="Commercial"),
 *             @OA\Property(property="dropLocationType", type="string", example="Commercial"),
 *             @OA\Property(property="shipmentDate", type="string", format="date-time", example="2025-12-09T17:11:52.705Z"),
 *             @OA\Property(
 *                 property="origin",
 *                 type="object",
 *                 @OA\Property(property="postalCode", type="string", example="33131"),
 *                 @OA\Property(property="city", type="string", example="Miami"),
 *                 @OA\Property(property="state", type="string", example="FL"),
 *                 @OA\Property(property="country", type="string", example="US")
 *             ),
 *             @OA\Property(
 *                 property="destination",
 *                 type="object",
 *                 @OA\Property(property="postalCode", type="string", example="90013"),
 *                 @OA\Property(property="city", type="string", example="Los Angeles"),
 *                 @OA\Property(property="state", type="string", example="CA"),
 *                 @OA\Property(property="country", type="string", example="US")
 *             ),
 *             @OA\Property(
 *                 property="pickupDetails",
 *                 type="object",
 *                 @OA\Property(property="address1", type="string", example="1234 SW 8th St"),
 *                 @OA\Property(property="address2", type="string", example="Suite 500"),
 *                 @OA\Property(property="stopName", type="string", example="ABC Manufacturing"),
 *                 @OA\Property(property="contactName", type="string", example="John Smith"),
 *                 @OA\Property(property="contactPhone", type="string", example="305-555-0198"),
 *                 @OA\Property(property="contactExtension", type="string", example="123"),
 *                 @OA\Property(property="hoursOpen", type="string", example="8:00 AM"),
 *                 @OA\Property(property="hoursClosed", type="string", example="5:00 PM"),
 *                 @OA\Property(property="puNumber", type="string", example="PU20251209-001")
 *             ),
 *             @OA\Property(
 *                 property="deliveryDetails",
 *                 type="object",
 *                 @OA\Property(property="address1", type="string", example="5678 E Olympic Blvd"),
 *                 @OA\Property(property="address2", type="string", example="Dock 12"),
 *                 @OA\Property(property="stopName", type="string", example="XYZ Distribution Center"),
 *                 @OA\Property(property="contactName", type="string", example="Maria Garcia"),
 *                 @OA\Property(property="contactPhone", type="string", example="213-555-0234"),
 *                 @OA\Property(property="contactExtension", type="string", example=""),
 *                 @OA\Property(property="hoursOpen", type="string", example="7:00 AM"),
 *                 @OA\Property(property="hoursClosed", type="string", example="4:00 PM"),
 *                 @OA\Property(property="deliveryPO", type="string", example="PO-987654")
 *             ),
 *             @OA\Property(
 *                 property="accessorials",
 *                 type="array",
 *                 @OA\Items(
 *                     type="string",
 *                     example="INPU=>Pickup – Inside"
 *                 ),
 *                 example={"INPU=>Pickup – Inside", "LIFT=>Liftgate at Pickup", "NOTIFY=>Notify Before Delivery"}
 *             ),
 *             @OA\Property(
 *                 property="quoteCommodities",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="freightClassCode", type="string", example="85"),
 *                     @OA\Property(property="unitTypeCode", type="string", example="PLT"),
 *                     @OA\Property(property="description", type="string", example="Electronics - Flat Screen TVs"),
 *                     @OA\Property(property="quantity", type="integer", example=6),
 *                     @OA\Property(property="weight", type="number", example=2400),
 *                     @OA\Property(property="dimensionLength", type="integer", example=48),
 *                     @OA\Property(property="dimensionWidth", type="integer", example=40),
 *                     @OA\Property(property="dimensionHeight", type="integer", example=72),
 *                     @OA\Property(property="nmfc", type="string", example="109980-02", nullable=true),
 *                     @OA\Property(property="pieceCaseCount", type="integer", example=6, nullable=true),
 *                     @OA\Property(property="isHazmat", type="boolean", example=false, nullable=true),
 *                     @OA\Property(property="isStackable", type="boolean", example=false, nullable=true)
 *                 ),
 *                 example={
 *                     {
 *                         "freightClassCode": "85",
 *                         "unitTypeCode": "PLT",
 *                         "description": "Electronics - Flat Screen TVs",
 *                         "quantity": 6,
 *                         "weight": 2400,
 *                         "dimensionLength": 48,
 *                         "dimensionWidth": 40,
 *                         "dimensionHeight": 72,
 *                         "nmfc": "109980-02",
 *                         "pieceCaseCount": 6,
 *                         "isHazmat": false,
 *                         "isStackable": false
 *                     },
 *                     {
 *                         "freightClassCode": "125",
 *                         "unitTypeCode": "CTN",
 *                         "description": "Clothing on hangers",
 *                         "quantity": 10,
 *                         "weight": 800,
 *                         "dimensionLength": 60,
 *                         "dimensionWidth": 48,
 *                         "dimensionHeight": 60,
 *                         "nmfc": "039760",
 *                         "pieceCaseCount": 10,
 *                         "isHazmat": false,
 *                         "isStackable": true
 *                     }
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Quote created"),
 *     @OA\Response(response=400, description="Invalid input"),
 *     @OA\Response(response=500, description="Server error")
 * )
 */
public function createLtlQuote(Request $request)
{ //dd($request->all());
    // 1. VALIDATION
    $validator = Validator::make($request->all(), [
        'carrierPriceId' => 'required|string',
        'customerEmailAddresses' => 'required|email',
        'pickLocationType' => 'required|string|in:Commercial,Residential',
        'dropLocationType' => 'required|string|in:Commercial,Residential',
        'shipmentDate' => 'required|date_format:Y-m-d\TH:i:s.v\Z',

        'origin.postalCode' => 'required|string',
        'origin.city' => 'required|string',
        'origin.state' => 'required|string|size:2',
        'origin.country' => 'required|string|size:2',

        'destination.postalCode' => 'required|string',
        'destination.city' => 'required|string',
        'destination.state' => 'required|string|size:2',
        'destination.country' => 'required|string|size:2',

        'pickupDetails.address1' => 'required|string',
        'pickupDetails.stopName' => 'required|string',
        'pickupDetails.contactName' => 'required|string',
        'pickupDetails.contactPhone' => 'required|string',
        'pickupDetails.hoursOpen' => 'required|string',
        'pickupDetails.hoursClosed' => 'required|string',

        'deliveryDetails.address1' => 'required|string',
        'deliveryDetails.stopName' => 'required|string',
        'deliveryDetails.contactName' => 'required|string',
        'deliveryDetails.contactPhone' => 'required|string',
        'deliveryDetails.hoursOpen' => 'required|string',
        'deliveryDetails.hoursClosed' => 'required|string',

        'accessorials' => 'nullable|array',
        'accessorials.*' => 'string',

        'quoteCommodities' => 'required|array|min:1',
        'quoteCommodities.*.freightClassCode' => 'required|string',
        'quoteCommodities.*.unitTypeCode' => 'required|string',
        'quoteCommodities.*.description' => 'required|string',
        'quoteCommodities.*.quantity' => 'required|integer|min:1',
        'quoteCommodities.*.weight' => 'required|numeric|min:1',
        'quoteCommodities.*.dimensionLength' => 'required|numeric|min:1',
        'quoteCommodities.*.dimensionWidth' => 'required|numeric|min:1',
        'quoteCommodities.*.dimensionHeight' => 'required|numeric|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }
   

    // 2. GET TOKEN
    $token = $this->getTqlToken();
 dd($token);
    if (!$token) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to generate token from TQL.',
        ], 500);
    }

 

// $token = Http::withHeaders([
//             'Content-Type' => 'application/json',
//             'Accept' => 'application/json',
//             // 'Authorization' => 'Bearer ' . config('services.tql.api_key'), // Uncomment if needed
//             'Ocp-Apim-Subscription-Key' => config('services.tql.api_key'),
//         ])->post('https://public.api.tql.com/identity/token', $request->all());

 

//     // 3. SEND POST REQUEST TO TQL QUOTES
    // $response = Http::withHeaders([
    //      'Authorization' => "Bearer $token",
    //     'Ocp-Apim-Subscription-Key' => config('services.tql.api_key'),
    //     'Content-Type' => 'application/json',
    // ])->post('https://public.api.tql.com/ftl/quotes/create', $request->all());
 
    // 4. HANDLE TQL RESPONSE
    if (!$response->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'TQL API error.',
            'response' => $response->json(),
        ], $response->status());
    }

    return response()->json([
        'success' => true,
        'data' => $response->json(),
    ], 200);
}


/**
 * @OA\Post(
 *     path="/api/frontend/tql-rate",
 *     summary="Get TQL shipping rate (returns only the price amount)",
 *     tags={"Frontend-TQL Rates Shipping"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="carrierPriceId", type="string", example="7654433"),
 *             @OA\Property(property="customerEmailAddresses", type="string", example="name@gmail.com"),
 *             @OA\Property(property="pickLocationType", type="string", example="Commercial"),
 *             @OA\Property(property="dropLocationType", type="string", example="Commercial"),
 *             @OA\Property(property="shipmentDate", type="string", format="date-time", example="2025-12-09T17:11:52.705Z"),
 *             @OA\Property(
 *                 property="origin",
 *                 type="object",
 *                 @OA\Property(property="postalCode", type="string", example="33131"),
 *                 @OA\Property(property="city", type="string", example="Miami"),
 *                 @OA\Property(property="state", type="string", example="FL"),
 *                 @OA\Property(property="country", type="string", example="US")
 *             ),
 *             @OA\Property(
 *                 property="destination",
 *                 type="object",
 *                 @OA\Property(property="postalCode", type="string", example="90013"),
 *                 @OA\Property(property="city", type="string", example="Los Angeles"),
 *                 @OA\Property(property="state", type="string", example="CA"),
 *                 @OA\Property(property="country", type="string", example="US")
 *             ),
 *             @OA\Property(
 *                 property="pickupDetails",
 *                 type="object",
 *                 @OA\Property(property="address1", type="string", example="1234 SW 8th St"),
 *                 @OA\Property(property="address2", type="string", example="Suite 500"),
 *                 @OA\Property(property="stopName", type="string", example="ABC Manufacturing"),
 *                 @OA\Property(property="contactName", type="string", example="John Smith"),
 *                 @OA\Property(property="contactPhone", type="string", example="305-555-0198"),
 *                 @OA\Property(property="contactExtension", type="string", example="123"),
 *                 @OA\Property(property="hoursOpen", type="string", example="8:00 AM"),
 *                 @OA\Property(property="hoursClosed", type="string", example="5:00 PM"),
 *                 @OA\Property(property="puNumber", type="string", example="PU20251209-001")
 *             ),
 *             @OA\Property(
 *                 property="deliveryDetails",
 *                 type="object",
 *                 @OA\Property(property="address1", type="string", example="5678 E Olympic Blvd"),
 *                 @OA\Property(property="address2", type="string", example="Dock 12"),
 *                 @OA\Property(property="stopName", type="string", example="XYZ Distribution Center"),
 *                 @OA\Property(property="contactName", type="string", example="Maria Garcia"),
 *                 @OA\Property(property="contactPhone", type="string", example="213-555-0234"),
 *                 @OA\Property(property="contactExtension", type="string", example=""),
 *                 @OA\Property(property="hoursOpen", type="string", example="7:00 AM"),
 *                 @OA\Property(property="hoursClosed", type="string", example="4:00 PM"),
 *                 @OA\Property(property="deliveryPO", type="string", example="PO-987654")
 *             ),
 *             @OA\Property(
 *                 property="accessorials",
 *                 type="array",
 *                 @OA\Items(
 *                     type="string",
 *                     example="INPU=>Pickup – Inside"
 *                 ),
 *                 example={"INPU=>Pickup – Inside", "LIFT=>Liftgate at Pickup", "NOTIFY=>Notify Before Delivery"}
 *             ),
 *             @OA\Property(
 *                 property="quoteCommodities",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="freightClassCode", type="string", example="85"),
 *                     @OA\Property(property="unitTypeCode", type="string", example="PLT"),
 *                     @OA\Property(property="description", type="string", example="Electronics - Flat Screen TVs"),
 *                     @OA\Property(property="quantity", type="integer", example=6),
 *                     @OA\Property(property="weight", type="number", example=2400),
 *                     @OA\Property(property="dimensionLength", type="integer", example=48),
 *                     @OA\Property(property="dimensionWidth", type="integer", example=40),
 *                     @OA\Property(property="dimensionHeight", type="integer", example=72),
 *                     @OA\Property(property="nmfc", type="string", example="109980-02", nullable=true),
 *                     @OA\Property(property="pieceCaseCount", type="integer", example=6, nullable=true),
 *                     @OA\Property(property="isHazmat", type="boolean", example=false, nullable=true),
 *                     @OA\Property(property="isStackable", type="boolean", example=false, nullable=true)
 *                 ),
 *                 example={
 *                     {
 *                         "freightClassCode": "85",
 *                         "unitTypeCode": "PLT",
 *                         "description": "Electronics - Flat Screen TVs",
 *                         "quantity": 6,
 *                         "weight": 2400,
 *                         "dimensionLength": 48,
 *                         "dimensionWidth": 40,
 *                         "dimensionHeight": 72,
 *                         "nmfc": "109980-02",
 *                         "pieceCaseCount": 6,
 *                         "isHazmat": false,
 *                         "isStackable": false
 *                     },
 *                     {
 *                         "freightClassCode": "125",
 *                         "unitTypeCode": "CTN",
 *                         "description": "Clothing on hangers",
 *                         "quantity": 10,
 *                         "weight": 800,
 *                         "dimensionLength": 60,
 *                         "dimensionWidth": 48,
 *                         "dimensionHeight": 60,
 *                         "nmfc": "039760",
 *                         "pieceCaseCount": 10,
 *                         "isHazmat": false,
 *                         "isStackable": true
 *                     }
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Quote created"),
 *     @OA\Response(response=400, description="Invalid input"),
 *     @OA\Response(response=500, description="Server error")
 * )
 */
   public function tqlRates(Request $request, TqlRateService $service)
{
    // Full validation matching your Swagger schema
    $validator = Validator::make($request->all(), [
        'carrierPriceId' => 'required|string',
        'customerEmailAddresses' => 'required|email',
        'pickLocationType' => 'required|string|in:Commercial,Residential',
        'dropLocationType' => 'required|string|in:Commercial,Residential',
        'shipmentDate' => 'required|date_format:Y-m-d\TH:i:s.v\Z', // Matches 2025-12-09T17:11:52.705Z

        'origin.postalCode' => 'required|string',
        'origin.city' => 'required|string',
        'origin.state' => 'required|string|size:2',
        'origin.country' => 'required|string|size:2',

        'destination.postalCode' => 'required|string',
        'destination.city' => 'required|string',
        'destination.state' => 'required|string|size:2',
        'destination.country' => 'required|string|size:2',

        'pickupDetails.address1' => 'required|string',
        'pickupDetails.address2' => 'nullable|string',
        'pickupDetails.stopName' => 'required|string',
        'pickupDetails.contactName' => 'required|string',
        'pickupDetails.contactPhone' => 'required|string',
        'pickupDetails.contactExtension' => 'nullable|string',
        'pickupDetails.hoursOpen' => 'required|string',
        'pickupDetails.hoursClosed' => 'required|string',
        'pickupDetails.puNumber' => 'nullable|string',

        'deliveryDetails.address1' => 'required|string',
        'deliveryDetails.address2' => 'nullable|string',
        'deliveryDetails.stopName' => 'required|string',
        'deliveryDetails.contactName' => 'required|string',
        'deliveryDetails.contactPhone' => 'required|string',
        'deliveryDetails.contactExtension' => 'nullable|string',
        'deliveryDetails.hoursOpen' => 'required|string',
        'deliveryDetails.hoursClosed' => 'required|string',
        'deliveryDetails.deliveryPO' => 'nullable|string',

        'accessorials' => 'nullable|array',
        'accessorials.*' => 'string',

        'quoteCommodities' => 'required|array|min:1',
        'quoteCommodities.*.freightClassCode' => 'required|string|in:50,55,60,65,70,77.5,85,92.5,100,110,125,150,175,200,250,300,400,500',
        'quoteCommodities.*.unitTypeCode' => 'required|string',
        'quoteCommodities.*.description' => 'required|string',
        'quoteCommodities.*.quantity' => 'required|integer|min:1',
        'quoteCommodities.*.weight' => 'required|numeric|min:1',
        'quoteCommodities.*.dimensionLength' => 'required|numeric|min:1',
        'quoteCommodities.*.dimensionWidth' => 'required|numeric|min:1',
        'quoteCommodities.*.dimensionHeight' => 'required|numeric|min:1',
        'quoteCommodities.*.nmfc' => 'nullable|string',
        'quoteCommodities.*.pieceCaseCount' => 'nullable|integer',
        'quoteCommodities.*.isHazmat' => 'nullable|boolean',
        'quoteCommodities.*.isStackable' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    // Pass the fully validated data directly to your service
    $shipmentData = $request->all();

    try {
        $rates = $service->getRates($shipmentData);

        if (!$rates || empty($rates)) {
            return response()->json([
                'success' => false,
                'message' => 'No rates found or service unavailable.',
                'shipment-rates' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rates retrieved successfully.',
            'shipment-rates' => $rates
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while fetching rates.',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function getTqlToken()
{
    $response = Http::withHeaders([
        'Ocp-Apim-Subscription-Key' => config('services.tql.subscription_key'),
        'Content-Type' => 'application/x-www-form-urlencoded',
    ])
    ->asForm()  
    ->post(config('services.tql.token_url'), [
        'client_id'     => config('services.tql.client_id'),
        'client_secret' => config('services.tql.client_secret'),
        'grant_type'    => 'password',
        'username'      => config('services.tql.username'),
        'password'      => config('services.tql.password'),
    ]);

    // Debug response if needed
    if (!$response->successful()) {
        dd(
            $response->status(),
            $response->body()
        );
    }

    return $response->json('access_token');
}


// public function getTqlToken()
// {  //dd(config('services.tql.username'));
//     $response = Http::withHeaders([
//         'Content-Type'=>'application/x-www-form-urlencoded',
//         'Ocp-Apim-Subscription-Key' => config('services.tql.subscription_key'),
//     ])->post(config('services.tql.token_url'), [
//         'client_id'     => config('services.tql.client_id'),
//         'client_secret' => config('services.tql.client_secret'),
//         'scope' => 'scope',
//         'grant_type'    => 'password',
//         'username'    => config('services.tql.username'),
//         'password'    => config('services.tql.password')
//     ]);
//  dd($response);
//     if (!$response->successful()) {
//         return null;
//     }

//     return $response->json()['access_token'];
// }

}
