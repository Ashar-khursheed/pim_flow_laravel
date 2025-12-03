<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/tracking/{poNumber}",
     *     tags={"Tracking"},
     *     summary="Track shipment",
     *     operationId="trackShipment",
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="poNumber",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response="200", description="Tracking info")
     * )
     */
    public function track($poNumber)
    {
        // Mock response
        return response()->json([
            'poNumber' => $poNumber,
            'status' => 'DELIVERED',
            'firstPick' => 'Anderson Twp, OH',
            'lastDrop' => 'Fairfax, VA',
            'nextStop' => 'Shipment Completed',
            'trackingDetails' => [
                [
                    'time' => now()->toDateTimeString(),
                    'status' => 'In Transit',
                    'location' => 'Reston, VA',
                    'latitude' => '38.916815',
                    'longitude' => '-77.056474',
                    'remarks' => 'Check Call'
                ]
            ],
            'stopDetails' => [
                // mock stops
            ]
        ]);
    }
}
