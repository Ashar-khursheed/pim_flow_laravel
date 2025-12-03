<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FrontEnd\TqlCarrierPrice;
use App\Models\FrontEnd\TqlQuote;
use App\Models\FrontEnd\TqlCommodity;
use Illuminate\Validation\Rule;
class TenderController extends Controller
{
    /**
     * @OA\Post(
     *     path="/ltl/quotes/tender",
     *     tags={"Tendering"},
     *     summary="Tender a shipment",
     *     operationId="tenderShipment",
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="carrierPriceId", type="integer"),
     *             @OA\Property(property="customerEmailAddresses", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="shipmentDate", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="pickupDetails", type="object",
     *                 @OA\Property(property="address1", type="string"),
     *                 @OA\Property(property="stopName", type="string"),
     *                 @OA\Property(property="contactPhone", type="string"),
     *                 @OA\Property(property="hoursOpen", type="string"),
     *                 @OA\Property(property="hoursClosed", type="string"),
     *                 // etc
     *             ),
     *             @OA\Property(property="deliveryDetails", type="object",
     *                 // similar
     *             )
     *         )
     *     ),
     *     @OA\Response(response="201", description="Shipment tendered")
     * )
     */
    public function tender(Request $request)
    {
        $validated = $request->validate([
            'carrierPriceId' => 'required|exists:carrier_prices,id',
            'customerEmailAddresses' => 'array',
            'customerEmailAddresses.*' => 'email|max:100',
            'shipmentDate' => 'date',
            'pickupDetails.address1' => 'required|string|max:50',
            'pickupDetails.stopName' => 'required|string|max:50',
            'pickupDetails.contactPhone' => 'required|regex:/^[+]?(1- |1\s|1)?(\(\d{3}\))|\d{3})(-|\s)?(\d{3})(-|\s)?(\d{4})$/',
            'pickupDetails.hoursOpen' => 'required',
            'pickupDetails.hoursClosed' => 'required',
            // Add more validation for deliveryDetails, commodities if updating hazmat
        ]);

        $carrierPrice = TqlCarrierPrice::findOrFail($validated['carrierPriceId']);

        if ($carrierPrice->quote->user_id !== auth()->id()) {
            abort(403);
        }

        $quote = $carrierPrice->quote;

        $quote->pickup_details = array_merge($quote->pickup_details ?? [], $request->pickupDetails ?? []);
        $quote->delivery_details = array_merge($quote->delivery_details ?? [], $request->deliveryDetails ?? []);
        $quote->shipment_date = $request->shipmentDate ?? $quote->shipment_date;
        $quote->tendered_date = now();
        $quote->po_number = rand(10000000, 99999999); // Mock PO
        $quote->save();

        // Mock email BOL to customerEmailAddresses

        return response()->json([
            'content' => [
                'quoteId' => $quote->id,
                'poNumber' => $quote->po_number,
                'errors' => []
            ],
            'statusCode' => 201,
            'informationalMessage' => 'Successfully tendered shipment.'
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/ltl/loads/tender",
     *     tags={"Tendering"},
     *     summary="Tender by SCAC",
     *     operationId="tenderByScac",
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="customerEmailAddresses", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="scac", type="string"),
     *             @OA\Property(property="serviceLevel", type="string"),
     *             @OA\Property(property="shipmentDate", type="string", format="date-time"),
     *             // Add other properties as per PDF: customerReference, poNumber, commodities, etc.
     *         )
     *     ),
     *     @OA\Response(response="201", description="Quote created and tendered")
     * )
     */
    public function tenderByScac(Request $request)
    {
        $validated = $request->validate([
            'scac' => 'required|string',
            'serviceLevel' => ['required', Rule::in(['Standard', 'Volume', 'Guaranteed', 'Guaranteed 12 PM', 'Guaranteed 3 PM', 'Guaranteed 3:30 PM', 'Guaranteed 5 PM'])],
            'shipmentDate' => 'required|date',
            // Validate other fields like commodities, pickupDetails, etc. similar to create quote
        ]);

        // Mock create quote first
        // ... (call create logic internally, then find matching carrier by scac and serviceLevel)

        $quote = new TqlQuote; // Mock creation similar to create()
        // Assume created, then tender

        // Mock selected carrier
        $selectedCarrier = [ // Mock
            'id' => rand(1000000, 9999999),
            'carrier' => 'Mock Carrier',
            'scac' => $validated['scac'],
            'customerRate' => 342.21,
            'serviceLevel' => $validated['serviceLevel'],
            // etc
        ];

        $quote->po_number = rand(10000000, 99999999);
        $quote->save();

        return response()->json([
            'content' => [
                'quoteId' => $quote->id,
                'poNumber' => $quote->po_number,
                'shipmentDate' => $validated['shipmentDate'],
                'selectedCarrierPrice' => $selectedCarrier,
            ],
            'statusCode' => 201,
            'informationalMessage' => 'Successfully created quote and tendered shipment.'
        ], 201);
    }
}
