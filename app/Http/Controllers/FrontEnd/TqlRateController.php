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
     *     tags={"Frontend-TQL"},
     *     @OA\Response(
     *         response=200,
     *         description="TQL token generated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9"),
     *             @OA\Property(property="expires_in", type="integer", example=3600),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */

    public function tqltoken(Request $request)
    {
        $scope = 'https://tqlidentity.onmicrosoft.com/services_combined/LTLQuotes.Read';
        $token = $this->getTqlToken($scope);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate token from TQL.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'token' => $token,
        ], 200);
    }


    /**
     * @OA\Post(
     *     path="/api/frontend/tql-rate",
     *     summary="Get TQL shipping rate (returns only the price amount)",
     *     tags={"Frontend-TQL"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={
     *                  "pickLocationType",
     *                 "dropLocationType","shipmentDate","origin","destination","quoteCommodities"
     *             },
     *             @OA\Property(property="pickLocationType", type="string", example="Commercial"),
     *             @OA\Property(property="dropLocationType", type="string", example="Commercial"),
     *             @OA\Property(property="shipmentDate", type="string", format="date-time", example="2025-12-22T17:11:52Z"),
     *
     *             @OA\Property(
     *                 property="origin",
     *                 type="object",
     *                 required={"postalCode","city","state","country"},
     *                 @OA\Property(property="postalCode", type="string", example="11741"),
     *                 @OA\Property(property="city", type="string", example="Holbrook"),
     *                 @OA\Property(property="state", type="string", example="NY"),
     *                 @OA\Property(property="country", type="string", example="USA")
     *             ),
     *
     *             @OA\Property(
     *                 property="destination",
     *                 type="object",
     *                 required={"postalCode","city","state","country"},
     *                 @OA\Property(property="postalCode", type="string", example="45203"),
     *                 @OA\Property(property="city", type="string", example="Cincinnati"),
     *                 @OA\Property(property="state", type="string", example="OH"),
     *                 @OA\Property(property="country", type="string", example="USA")
     *             ),
     *
     *
     *
     *             @OA\Property(
     *                 property="quoteCommodities",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={
     *                         "freightClassCode","unitTypeCode","description",
     *                         "quantity","weight","dimensionLength","dimensionWidth","dimensionHeight"
     *                     },
     *                     @OA\Property(property="freightClassCode", type="number", example=85),
     *                     @OA\Property(property="unitTypeCode", type="string", example="PLT"),
     *                     @OA\Property(property="description", type="string", example="Electronics"),
     *                     @OA\Property(property="quantity", type="integer", example=6),
     *                     @OA\Property(property="weight", type="number", example=2400),
     *                     @OA\Property(property="dimensionLength", type="integer", example=48),
     *                     @OA\Property(property="dimensionWidth", type="integer", example=40),
     *                     @OA\Property(property="dimensionHeight", type="integer", example=72)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Rates retrieved"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */

    public function tqlRates(Request $request, TqlRateService $service)
    {
        // Full validation matching your Swagger schema
        $validator = Validator::make($request->all(), [
            // 'carrierPriceId' => 'required|string',
            // 'customerEmailAddresses' => 'required|email:strict',
            'pickLocationType' => 'required|string|in:Commercial,Residential',
            'dropLocationType' => 'required|string|in:Commercial,Residential',
            // 'shipmentDate' => 'required|date_format:Y-m-d\TH:i:s.v\Z',

            'origin.postalCode' => 'required|string',
            'origin.city' => 'required|string',
            'origin.state' => 'required|string',
            'origin.country' => 'required|string',

            'destination.postalCode' => 'required|string',
            'destination.city' => 'required|string',
            'destination.state' => 'required|string',
            'destination.country' => 'required|string',



            // 'accessorials' => 'nullable|array',
            // 'accessorials.*' => 'string',

            'quoteCommodities' => 'required|array|min:1',
            'quoteCommodities.*.freightClassCode' => 'required|in:50,55,60,65,70,77.5,85,92.5,100,110,125,150,175,200,250,300,400,500',
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
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $scope =  'https://tqlidentity.onmicrosoft.com/services_combined/LTLQuotes.Write';

        $token = $this->getTqlToken($scope);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate token from TQL.',
            ], 500);
        }

        // Pass the fully validated data directly to your service
        $shipmentData = $request->all();

        try {
            $rates = $service->getRates($shipmentData, $token);

            if (!$rates || empty($rates)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rates found or service unavailable.',
                    'data' => null
                ], 404);
            }



            $carrierPrices = collect($rates->json()['content']['carrierPrices']);
            $content =  $rates->json()['content'];

            if ($carrierPrices->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No carrier prices available.',
                ], 404);
            }

            $cheapest = $carrierPrices->sortBy('customerRate')->first();
            $fastest = $carrierPrices
                ->reject(fn($c) => $c['carrierQuoteId'] === $cheapest['carrierQuoteId'])
                ->sortBy('transitDays')
                ->first();
            $bestValue = $carrierPrices
                ->reject(
                    fn($c) =>
                    in_array($c['carrierQuoteId'], [
                        $cheapest['carrierQuoteId'],
                        optional($fastest)['carrierQuoteId']
                    ])
                )
                ->map(function ($item) {
                    $item['score'] = ($item['customerRate'] * 0.7)
                        + ($item['transitDays'] * 0.3);
                    return $item;
                })
                ->sortBy('score')
                ->first();
            $finalCarriers = collect([
                'Cheapest'   => $cheapest,
                //  'Fastest'    => $fastest,
                //'Best Value' => $bestValue

            ])->filter()->map(function ($carrier, $label) {
                $carrier['label'] = $label;
                return $carrier;
            })->values();


            return response()->json([
                'success' => true,
                'message' => 'Rates retrieved successfully.',
                'data' => $finalCarriers,
                'quoteId' => $content['quoteId'] ?? null,
                'commodityId' => $content['quoteCommodities'][0]['commodityId'] ?? null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching rates.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/tql-createQuote",
     *     summary="Create TQL)",
     *     tags={"Frontend-TQL"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={
     *                  "pickLocationType",
     *                 "dropLocationType","shipmentDate","origin","destination","quoteCommodities"
     *             },
     *             @OA\Property(property="pickLocationType", type="string", example="Commercial"),
     *             @OA\Property(property="dropLocationType", type="string", example="Commercial"),
     *             @OA\Property(property="shipmentDate", type="string", format="date-time", example="2025-12-22T17:11:52Z"),
     *
     *             @OA\Property(
     *                 property="origin",
     *                 type="object",
     *                 required={"postalCode","city","state","country"},
     *                 @OA\Property(property="postalCode", type="string", example="11741"),
     *                 @OA\Property(property="city", type="string", example="Holbrook"),
     *                 @OA\Property(property="state", type="string", example="NY"),
     *                 @OA\Property(property="country", type="string", example="USA")
     *             ),
     *
     *             @OA\Property(
     *                 property="destination",
     *                 type="object",
     *                 required={"postalCode","city","state","country"},
     *                 @OA\Property(property="postalCode", type="string", example="45203"),
     *                 @OA\Property(property="city", type="string", example="Cincinnati"),
     *                 @OA\Property(property="state", type="string", example="OH"),
     *                 @OA\Property(property="country", type="string", example="USA")
     *             ),
     *
     *
     *
     *             @OA\Property(
     *                 property="quoteCommodities",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={
     *                         "freightClassCode","unitTypeCode","description",
     *                         "quantity","weight","dimensionLength","dimensionWidth","dimensionHeight"
     *                     },
     *                     @OA\Property(property="freightClassCode", type="number", example=85),
     *                     @OA\Property(property="unitTypeCode", type="string", example="PLT"),
     *                     @OA\Property(property="description", type="string", example="Electronics"),
     *                     @OA\Property(property="quantity", type="integer", example=6),
     *                     @OA\Property(property="weight", type="number", example=2400),
     *                     @OA\Property(property="dimensionLength", type="integer", example=48),
     *                     @OA\Property(property="dimensionWidth", type="integer", example=40),
     *                     @OA\Property(property="dimensionHeight", type="integer", example=72)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Rates retrieved"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */

    public function createQuote(Request $request, TqlRateService $service)
    {
        // Full validation matching your Swagger schema
        $validator = Validator::make($request->all(), [
            // 'carrierPriceId' => 'required|string',
            // 'customerEmailAddresses' => 'required|email:strict',
            'pickLocationType' => 'required|string|in:Commercial,Residential',
            'dropLocationType' => 'required|string|in:Commercial,Residential',
            // 'shipmentDate' => 'required|date_format:Y-m-d\TH:i:s.v\Z',

            'origin.postalCode' => 'required|string',
            'origin.city' => 'required|string',
            'origin.state' => 'required|string',
            'origin.country' => 'required|string',

            'destination.postalCode' => 'required|string',
            'destination.city' => 'required|string',
            'destination.state' => 'required|string',
            'destination.country' => 'required|string',



            // 'accessorials' => 'nullable|array',
            // 'accessorials.*' => 'string',

            'quoteCommodities' => 'required|array|min:1',
            'quoteCommodities.*.freightClassCode' => 'required|in:50,55,60,65,70,77.5,85,92.5,100,110,125,150,175,200,250,300,400,500',
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
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $scope =  'https://tqlidentity.onmicrosoft.com/services_combined/LTLQuotes.Write';

        $token = $this->getTqlToken($scope);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate token from TQL.',
            ], 500);
        }

        // Pass the fully validated data directly to your service
        $shipmentData = $request->all();

        try {
            $rates = $service->getRates($shipmentData, $token);

            if (!$rates || empty($rates)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rates found or service unavailable.',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Rates retrieved successfully.',
                'data' => $rates->json()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching rates.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/tql-tenderShipment",
     *     summary="Create TQL Tender Shipment",
     *     tags={"Frontend-TQL"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={
     *                 "carrierPriceId",
     *                 "customerEmailAddresses",
     *                 "shipmentDate"
     *             },
     *
     *             @OA\Property(
     *                 property="carrierPriceId",
     *                 type="string",
     *                 example="180037922"
     *             ),
     *
     *             @OA\Property(
     *                 property="customerEmailAddresses",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="string",
     *                     format="email",
     *                     example="john.doe@company.com"
     *                 )
     *             ),
     *
     *             @OA\Property(
     *                 property="shipmentDate",
     *                 type="string",
     *                 format="date-time",
     *                 example="2025-12-22T17:11:52Z"
     *             ),
     *
     *             @OA\Property(
     *                 property="pickupDetails",
     *                 type="object",
     *                 @OA\Property(property="puNumber", type="string", example="PU12345"),
     *                 @OA\Property(property="stopName", type="string", example="New York Warehouse"),
     *                 @OA\Property(property="contactName", type="string", example="John Smith"),
     *                 @OA\Property(property="contactPhone", type="string", example="5165557890"),
     *                 @OA\Property(property="contactExtension", type="string", example="101"),
     *                 @OA\Property(property="address1", type="string", example="250 Express Drive South"),
     *                 @OA\Property(property="address2", type="string", example="Suite 100"),
     *                 @OA\Property(property="hoursOpen", type="string", example="9:00 AM"),
     *                 @OA\Property(property="hoursClose", type="string", example="5:00 PM")
     *             ),
     *
     *             @OA\Property(
     *                 property="deliveryDetails",
     *                 type="object",
     *                 @OA\Property(property="deliveryPO", type="string", example="PO789456"),
     *                 @OA\Property(property="stopName", type="string", example="Ohio Distribution Center"),
     *                 @OA\Property(property="contactName", type="string", example="Michael Brown"),
     *                 @OA\Property(property="contactPhone", type="string", example="5135554321"),
     *                 @OA\Property(property="contactExtension", type="string", example="202"),
     *                 @OA\Property(property="address1", type="string", example="1200 Central Parkway"),
     *                 @OA\Property(property="address2", type="string", example="Dock 4"),
     *                 @OA\Property(property="hoursOpen", type="string", example="9:00 AM"),
     *                 @OA\Property(property="hoursClose", type="string", example="5:00 PM")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tender shipment created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */


    public function tenderShipment(Request $request)
    {
        $validator = Validator::make($request->all(), [

            // Required top-level fields
            'carrierPriceId' => 'required|string',

            'customerEmailAddresses' => 'required|array|min:1',
            'customerEmailAddresses.*' => 'required|email:strict',

            'shipmentDate' => 'required|date',

            // Pickup Details
            'pickupDetails' => 'nullable|array',
            'pickupDetails.puNumber' => 'nullable|string',
            'pickupDetails.stopName' => 'nullable|string',
            'pickupDetails.contactName' => 'nullable|string',
            'pickupDetails.contactPhone' => 'nullable|string',
            'pickupDetails.contactExtension' => 'nullable|string',
            'pickupDetails.address1' => 'nullable|string',
            'pickupDetails.address2' => 'nullable|string',
            'pickupDetails.hoursOpen' => 'nullable|string',
            'pickupDetails.hoursClose' => 'nullable|string',

            // Delivery Details
            'deliveryDetails' => 'nullable|array',
            'deliveryDetails.deliveryPO' => 'nullable|string',
            'deliveryDetails.stopName' => 'nullable|string',
            'deliveryDetails.contactName' => 'nullable|string',
            'deliveryDetails.contactPhone' => 'nullable|string',
            'deliveryDetails.contactExtension' => 'nullable|string',
            'deliveryDetails.address1' => 'nullable|string',
            'deliveryDetails.address2' => 'nullable|string',
            'deliveryDetails.hoursOpen' => 'nullable|string',
            'deliveryDetails.hoursClose' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $scope = 'https://tqlidentity.onmicrosoft.com/services_combined/LTLQuotes.Write';
        $token = $this->getTqlToken($scope);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate TQL token'
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Ocp-Apim-Subscription-Key' => config('services.tql.subscription_key'),
                'Content-Type' => 'application/json',
            ])->post(
                'https://public.api.tql.com/ltl/quotes/tender',
                $request->all()
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'TQL API Error',
                    'status' => $response->status(),
                    'response' => $response->json()
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'message' => 'Tender shipment created successfully',
                'data' => $response->json()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
 * @OA\Post(
 *     path="/api/frontend/tql-loads-tender",
 *     summary="Request TQL loads Tendering",
 *     tags={"Frontend-TQL"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *
 *             @OA\Property(
 *                 property="customerEmailAddresses",
 *                 type="array",
 *                 @OA\Items(type="string", example="test@tql.com")
 *             ),
 *
 *             @OA\Property(property="scac", type="string", example="BBFG"),
 *             @OA\Property(property="serviceLevel", type="string", example="Standard"),
 *             @OA\Property(property="customerReference", type="string", example="Sample Reference"),
 *             @OA\Property(property="poNumber", type="string", example="34996030"),
 *             @OA\Property(property="soNumber", type="string", example="34996030"),
 *             @OA\Property(property="specialInstructions", type="string", example="Handle with care"),
 *
 *             @OA\Property(
 *                 property="commodities",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="unitTypeCode", type="string", example="PLT"),
 *                     @OA\Property(property="freightClassCode", type="string", example="55"),
 *                     @OA\Property(property="description", type="string", example="Fresh Tomatoes"),
 *                     @OA\Property(property="quantity", type="integer", example=4),
 *                     @OA\Property(property="weight", type="number", format="float", example=1200.50),
 *                     @OA\Property(property="dimensionLength", type="integer", example=48),
 *                     @OA\Property(property="dimensionWidth", type="integer", example=40),
 *                     @OA\Property(property="dimensionHeight", type="integer", example=60),
 *                     @OA\Property(property="isStackable", type="boolean", example=false),
 *                     @OA\Property(property="isHazmat", type="boolean", example=false)
 *                 )
 *             ),
 *
 *             @OA\Property(
 *                 property="accessorials",
 *                 type="array",
 *                 @OA\Items(type="string", example="Liftgate Pickup")
 *             ),
 *
 *             @OA\Property(
 *                 property="shipmentDate",
 *                 type="string",
 *                 format="date-time",
 *                 example="2026-01-16T00:00:00-05:00"
 *             ),
 *
 *             @OA\Property(
 *                 property="pickupDetails",
 *                 type="object",
 *                 @OA\Property(property="puNumber", type="string", example="PU-789456"),
 *                 @OA\Property(property="address1", type="string", example="617 Test Street"),
 *                 @OA\Property(property="address2", type="string", example="Suite 200"),
 *                 @OA\Property(property="city", type="string", example="Cincinnati"),
 *                 @OA\Property(property="state", type="string", example="OH"),
 *                 @OA\Property(property="postalCode", type="string", example="45203"),
 *                 @OA\Property(property="country", type="string", example="USA"),
 *                 @OA\Property(property="stopName", type="string", example="Cincinnati Distribution Center"),
 *                 @OA\Property(property="locationType", type="string", example="Commercial"),
 *                 @OA\Property(property="contactName", type="string", example="John Smith"),
 *                 @OA\Property(property="contactPhone", type="string", example="2169582529"),
 *                 @OA\Property(property="contactExtension", type="string", example="569"),
 *                 @OA\Property(property="hoursOpen", type="string", example="09:30 AM"),
 *                 @OA\Property(property="hoursClose", type="string", example="09:30 PM")
 *             ),
 *
 *             @OA\Property(
 *                 property="deliveryDetails",
 *                 type="object",
 *                 @OA\Property(property="deliveryPO", type="string", example="DEL-123456"),
 *                 @OA\Property(property="address1", type="string", example="383 Test Drive"),
 *                 @OA\Property(property="address2", type="string", example="Warehouse B"),
 *                 @OA\Property(property="city", type="string", example="Fresno"),
 *                 @OA\Property(property="state", type="string", example="CA"),
 *                 @OA\Property(property="postalCode", type="string", example="93714"),
 *                 @OA\Property(property="country", type="string", example="USA"),
 *                 @OA\Property(property="stopName", type="string", example="Fresno Receiving Facility"),
 *                 @OA\Property(property="locationType", type="string", example="Commercial"),
 *                 @OA\Property(property="contactName", type="string", example="David Johnson"),
 *                 @OA\Property(property="contactPhone", type="string", example="9995771510"),
 *                 @OA\Property(property="contactExtension", type="string", example="195"),
 *                 @OA\Property(property="hoursOpen", type="string", example="11:00 AM"),
 *                 @OA\Property(property="hoursClose", type="string", example="04:00 AM")
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Shipment created successfully"
 *     )
 * )
 */



    public function tqlLoadsTender(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customerEmailAddresses' => 'required|array|min:1',
            'customerEmailAddresses.*' => 'required|email:strict',

            'scac' => 'required|string|max:10',
            'serviceLevel' => 'required|string|max:50',
            'customerReference' => 'nullable|string|max:100',
            'poNumber' => 'nullable|string|max:100',
            'soNumber' => 'nullable|string|max:100',
            'specialInstructions' => 'nullable|string|max:500',

            'shipmentDate' => 'required|date',


            'commodities' => 'required|array|min:1',

            'commodities.*.unitTypeCode' => 'required|string|max:10',
            'commodities.*.freightClassCode' => 'required|string|max:10',
            'commodities.*.description' => 'required|string|max:255',
            'commodities.*.quantity' => 'required|integer|min:1',
            'commodities.*.weight' => 'required|numeric|min:0.01',

            'commodities.*.dimensionLength' => 'required|integer|min:1',
            'commodities.*.dimensionWidth' => 'required|integer|min:1',
            'commodities.*.dimensionHeight' => 'required|integer|min:1',

            'commodities.*.isStackable' => 'required|boolean',
            'commodities.*.isHazmat' => 'required|boolean',



            'accessorials' => 'nullable|array',
            'accessorials.*' => 'string|max:100',



            'pickupDetails' => 'required|array',
            'pickupDetails.puNumber' => 'nullable|string|max:50',
            'pickupDetails.address1' => 'required|string|max:255',
            'pickupDetails.address2' => 'nullable|string|max:255',
            'pickupDetails.city' => 'required|string|max:100',
            'pickupDetails.state' => 'required|string|max:10',
            'pickupDetails.postalCode' => 'required|string|max:20',
            'pickupDetails.country' => 'required|string|max:10',
            'pickupDetails.stopName' => 'nullable|string|max:100',
            'pickupDetails.locationType' => 'required|string|max:50',
            'pickupDetails.contactName' => 'required|string|max:100',
            'pickupDetails.contactPhone' => 'required|string|max:20',
            'pickupDetails.contactExtension' => 'nullable|string|max:10',
            'pickupDetails.hoursOpen' => 'required|string|max:20',
            'pickupDetails.hoursClose' => 'required|string|max:20',



            'deliveryDetails' => 'required|array',
            'deliveryDetails.deliveryPO' => 'nullable|string|max:50',
            'deliveryDetails.address1' => 'required|string|max:255',
            'deliveryDetails.address2' => 'nullable|string|max:255',
            'deliveryDetails.city' => 'required|string|max:100',
            'deliveryDetails.state' => 'required|string|max:10',
            'deliveryDetails.postalCode' => 'required|string|max:20',
            'deliveryDetails.country' => 'required|string|max:10',
            'deliveryDetails.stopName' => 'nullable|string|max:100',
            'deliveryDetails.locationType' => 'required|string|max:50',
            'deliveryDetails.contactName' => 'required|string|max:100',
            'deliveryDetails.contactPhone' => 'required|string|max:20',
            'deliveryDetails.contactExtension' => 'nullable|string|max:10',
            'deliveryDetails.hoursOpen' => 'required|string|max:20',
            'deliveryDetails.hoursClose' => 'required|string|max:20',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $scope = 'https://tqlidentity.onmicrosoft.com/services_combined/LTLQuotes.Write';
        $token = $this->getTqlToken($scope);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate TQL token'
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Ocp-Apim-Subscription-Key' => config('services.tql.subscription_key'),
                'Content-Type' => 'application/json',
            ])->post(
                'https://public.api.tql.com/test-ltl/loads/tender',
                $request->all()
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'TQL API Error',
                    'status' => $response->status(),
                    'response' => $response->json()
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'message' => 'Loads Tender created successfully',
                'data' => $response->json()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function getTqlToken($scope)
    {

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => config('services.tql.subscription_key'),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])
            ->asForm()
            ->post(config('services.tql.token_url'), [
                'client_id'     => config('services.tql.client_id'),
                'client_secret' => config('services.tql.client_secret'),
                'scope'         => $scope,
                'grant_type' => 'password',
                'username'      => config('services.tql.username'),
                'password'      => config('services.tql.password')
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


    /**
     * @OA\Get(
     *     path="/api/frontend/tql-getQuote/{quoteId}",
     *     summary="Get TQL by quoteId)",
     *     tags={"Frontend-TQL"},
     *
     *     @OA\Parameter(
     *         name="quoteId",
     *         in="path",
     *         required=true,
     *         description="TQL Quote ID",
     *         @OA\Schema(
     *             type="string",
     *             example="8828101"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Quote retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */


    public function getQuote(Request $request, $quoteId)
    {
        $scope =  'https://tqlidentity.onmicrosoft.com/services_combined/LTLQuotes.Write';

        $token = $this->getTqlToken($scope);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate token from TQL.',
            ], 500);
        }

        // Pass the fully validated data directly to your service
        $shipmentData = $request->all();


        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Ocp-Apim-Subscription-Key' => config('services.tql.subscription_key'),
            'Content-Type' => 'application/json'
        ])

            ->get(config('services.tql.base_url') . '/quotes/' . $quoteId, $shipmentData);


        if (!$response || empty($response)) {
            return response()->json([
                'success' => false,
                'message' => 'No rates found or service unavailable.',
                'data' => null
            ], 404);
        }



        return response()->json([
            'success' => true,
            'message' => 'Rates retrieved successfully.',
            'data' =>   $response->json()
        ], 200);
    }
    /**
     * @OA\Get(
     *     path="/api/frontend/tql-tracking/{poNumber}",
     *     summary="Get TQL tracking by poNumber)",
     *     tags={"Frontend-TQL"},
     *
     *     @OA\Parameter(
     *         name="poNumber",
     *         in="path",
     *         required=true,
     *         description="TQL poNumber id",
     *         @OA\Schema(
     *             type="string",
     *             example="34996030"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Quote retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */

    public function getTracking(Request $request, $poNumber)
    {
        $scope =  'https://tqlidentity.onmicrosoft.com/services_combined/LTLQuotes.Write';

        $token = $this->getTqlToken($scope);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate token from TQL.',
            ], 500);
        }

        // Pass the fully validated data directly to your service
        $shipmentData = $request->all();


        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Ocp-Apim-Subscription-Key' => config('services.tql.subscription_key'),
            'Content-Type' => 'application/json'
        ])

            ->get('https://public.api.tql.com/tracking/' . $poNumber, $shipmentData);


        if (!$response || empty($response)) {
            return response()->json([
                'success' => false,
                'message' => 'No rates found or service unavailable.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rates retrieved successfully.',
            'data' =>    $response->json(),
        ], 200);
    }
}
