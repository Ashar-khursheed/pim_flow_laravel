<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FrontEnd\TqlQuote;
use App\Models\FrontEnd\TqlCarrierPrice;
use App\Models\FrontEnd\TqlCommodity;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class TqlQuoteController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/tql/tqlquotes",
     *     tags={"TQL Quotes"},
     *     summary="Create a quote",
     *     operationId="createQuote",
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="pickLocationType", type="string", example="Commercial"),
     *             @OA\Property(property="dropLocationType", type="string", example="Commercial"),
     *             @OA\Property(property="shipmentDate", type="string", format="date-time"),
     *             @OA\Property(property="origin", type="object",
     *                 @OA\Property(property="postalCode", type="string"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="state", type="string"),
     *                 @OA\Property(property="country", type="string")
     *             ),
     *             @OA\Property(property="destination", type="object",
     *                 @OA\Property(property="postalCode", type="string"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="state", type="string"),
     *                 @OA\Property(property="country", type="string")
     *             ),
     *             @OA\Property(property="accessorials", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="quoteCommodities", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="freightClassCode", type="string"),
     *                     @OA\Property(property="unitTypeCode", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="quantity", type="integer"),
     *                     @OA\Property(property="weight", type="number"),
     *                     @OA\Property(property="dimensionLength", type="integer"),
     *                     @OA\Property(property="dimensionWidth", type="integer"),
     *                     @OA\Property(property="dimensionHeight", type="integer"),
     *                     @OA\Property(property="nmfc", type="string", nullable=true),
     *                     @OA\Property(property="pieceCaseCount", type="integer", nullable=true),
     *                     @OA\Property(property="isHazmat", type="boolean", nullable=true),
     *                     @OA\Property(property="isStackable", type="boolean", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response="201", description="Quote created")
     * )
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'pickLocationType' => ['required', Rule::in(['Commercial', 'Limited Access', 'Residential', 'Trade Show'])],
            'dropLocationType' => ['required', Rule::in(['Commercial', 'Limited Access', 'Residential', 'Trade Show'])],
            'shipmentDate' => 'required|date',
            'origin.postalCode' => 'required|string',
            'origin.city' => 'required|string',
            'origin.state' => 'required|string|max:2',
            'origin.country' => ['required', Rule::in(['USA', 'CAN', 'MEX'])],
            'destination.postalCode' => 'required|string',
            'destination.city' => 'required|string',
            'destination.state' => 'required|string|max:2',
            'destination.country' => ['required', Rule::in(['USA', 'CAN', 'MEX'])],
            'accessorials' => 'array',
            'accessorials.*' => Rule::in(['INPU', 'LGPU', 'INDEL', 'LGDEL', 'APPTDEL', 'NOTIFY', 'SORTDEL', 'BLIND_S', 'BLIND_D', 'BOND', 'PFZ']),
            'quoteCommodities' => 'required|array|min:1',
            'quoteCommodities.*.freightClassCode' => ['required', Rule::in(['50', '55', '60', '65', '70', '77.5', '85', '92.5', '100', '110', '125', '150', '175', '200', '250', '300', '400', '500'])],
            'quoteCommodities.*.unitTypeCode' => ['required', Rule::in(['BOX', 'BUNDLE', 'CARTON', 'CRATE', 'DRUM', 'PLT', 'ROLL', 'PIECES', 'CASE'])],
            'quoteCommodities.*.description' => 'required|string|max:255',
            'quoteCommodities.*.quantity' => 'required|integer|min:1|max:315',
            'quoteCommodities.*.weight' => 'required|numeric|min:0.1',
            'quoteCommodities.*.dimensionLength' => 'required|integer|min:1|max:636',
            'quoteCommodities.*.dimensionWidth' => 'required|integer|min:1|max:102',
            'quoteCommodities.*.dimensionHeight' => 'required|integer|min:1|max:102',
            'quoteCommodities.*.nmfc' => 'nullable|string|max:9|regex:/^[0-9-]+$/',

            'quoteCommodities.*.pieceCaseCount' => 'nullable|integer|min:1',
            'quoteCommodities.*.isHazmat' => 'nullable|boolean',
            'quoteCommodities.*.isStackable' => 'nullable|boolean',
            
        ]);

        $quote = TqlQuote::create([
            'user_id' => auth()->id(),
            'pick_location_type' => $validated['pickLocationType'],
            'drop_location_type' => $validated['dropLocationType'],
            'shipment_date' => $validated['shipmentDate'],
            'origin' => $validated['origin'],
            'destination' => $validated['destination'],
            'pickup_details' => $request->pickupDetails ?? null,
            'delivery_details' => $request->deliveryDetails ?? null,
            'accessorials' => $validated['accessorials'] ?? [],
            'created_date' => now(),
        ]);

        foreach ($validated['quoteCommodities'] as $comm) {
            $quote->commodities()->create([
                'description' => $comm['description'],
                'quantity' => $comm['quantity'],
                'weight' => $comm['weight'],
                'dimension_length' => $comm['dimensionLength'],
                'dimension_width' => $comm['dimensionWidth'],
                'dimension_height' => $comm['dimensionHeight'],
                'is_hazmat' => $comm['isHazmat'] ?? false,
                'freight_class_code' => $comm['freightClassCode'],
                'unit_type_code' => $comm['unitTypeCode'],
                'nmfc' => $comm['nmfc'] ?? null,
                'piece_case_count' => $comm['pieceCaseCount'] ?? null,
                'is_stackable' => $comm['isStackable'] ?? false,
            ]);
        }

        // Mock carrier prices
        $mockCarriers = [
            [
                'carrier' => 'Estes Express',
                'scac' => 'EXLA',
                'customer_rate' => 200.00,
                'carrier_quote_id' => 'ABC123',
                'service_level' => 'Volume',
                'service_type' => 'UNSPECIFIED',
                'transit_days' => 2,
                'max_liability_new' => 200.00,
                'max_liability_used' => 200.00,
                'service_level_description' => 'Volume and Truckload Basic',
                'price_charges' => [],
                'is_preferred' => true,
                'is_economy' => false,
            ],
            // Add more mock as per PDF sample
        ];

        foreach ($mockCarriers as $carrier) {
            $quote->carrierPrices()->create($carrier);
        }

        $responseCommodities = $quote->commodities->map(function ($comm) {
            return [
                'commodityId' => $comm->id,
                'description' => $comm->description,
                'quantity' => $comm->quantity,
                'weight' => $comm->weight,
                'dimensionLength' => $comm->dimension_length,
                'dimensionWidth' => $comm->dimension_width,
                'dimensionHeight' => $comm->dimension_height,
                'isHazmat' => $comm->is_hazmat,
                'freightClassCode' => $comm->freight_class_code,
                'unitTypeCode' => $comm->unit_type_code,
            ];
        });

        $responseCarrierPrices = $quote->carrierPrices->map(function ($price) {
            return [
                'id' => $price->id,
                'carrier' => $price->carrier,
                'scac' => $price->scac,
                'customerRate' => $price->customer_rate,
                'carrierQuoteId' => $price->carrier_quote_id,
                'serviceLevel' => $price->service_level,
                'serviceType' => $price->service_type,
                'transitDays' => $price->transit_days,
                'maxLiabilityNew' => $price->max_liability_new,
                'maxLiabilityUsed' => $price->max_liability_used,
                'serviceLevelDescription' => $price->service_level_description,
                'priceCharges' => $price->price_charges,
                'isPreferred' => $price->is_preferred,
                'isEconomy' => $price->is_economy,
            ];
        });

        return response()->json([
            'content' => [
                'quoteId' => $quote->id,
                'quoteCommodities' => $responseCommodities,
                'carrierPrices' => $responseCarrierPrices,
                'createdDate' => $quote->created_date,
                'shipmentDate' => $quote->shipment_date,
            ],
            'statusCode' => 201,
            'informationalMessage' => 'Successfully created quote.'
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/tql/tqlquotes/{id}",
     *     tags={"TQL Quotes"},
     *     summary="Get a quote",
     *     operationId="getQuote",
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response="200", description="Quote retrieved")
     * )
     */
    public function get($id)
    {
        $quote = TqlQuote::with('commodities', 'carrierPrices')->findOrFail($id);

        if ($quote->user_id !== auth()->id()) {
            abort(403, 'Forbidden');
        }

        $responseCommodities = $quote->commodities->map(function ($comm) {
            return [
                'commodityId' => $comm->id,
                'description' => $comm->description,
                'quantity' => $comm->quantity,
                'weight' => $comm->weight,
                'dimensionLength' => $comm->dimension_length,
                'dimensionWidth' => $comm->dimension_width,
                'dimensionHeight' => $comm->dimension_height,
                'isHazmat' => $comm->is_hazmat,
                'freightClassCode' => $comm->freight_class_code,
                'unitTypeCode' => $comm->unit_type_code,
                'hazmatDetails' => $comm->hazmat_details,
            ];
        });

        $responseCarrierPrices = $quote->carrierPrices->map(function ($price) {
            return [
                'id' => $price->id,
                'carrier' => $price->carrier,
                'scac' => $price->scac,
                'customerRate' => $price->customer_rate,
                'carrierQuoteId' => $price->carrier_quote_id,
                'serviceLevel' => $price->service_level,
                'serviceType' => $price->service_type,
                'transitDays' => $price->transit_days,
                'maxLiabilityNew' => $price->max_liability_new,
                'maxLiabilityUsed' => $price->max_liability_used,
                'serviceLevelDescription' => $price->service_level_description,
                'priceCharges' => $price->price_charges,
                'isPreferred' => $price->is_preferred,
                'isEconomy' => $price->is_economy,
            ];
        });

        return response()->json([
            'content' => [
                'quoteId' => $quote->id,
                'poNumber' => $quote->po_number,
                'pickLocationType' => $quote->pick_location_type,
                'dropLocationType' => $quote->drop_location_type,
                'createdDate' => $quote->created_date,
                'tenderedDate' => $quote->tendered_date,
                'shipmentDate' => $quote->shipment_date,
                'expirationDate' => $quote->expiration_date ?? $quote->created_date->addWeek(),
                'pickupDetails' => $quote->pickup_details ?? [],
                'deliveryDetails' => $quote->delivery_details ?? [],
                'accessorials' => $quote->accessorials,
                'quoteCommodities' => $responseCommodities,
                'carrierPrices' => $responseCarrierPrices,
            ],
            'statusCode' => 200,
            'informationalMessage' => 'Successfully retrieved quote.'
        ]);
    }
}
