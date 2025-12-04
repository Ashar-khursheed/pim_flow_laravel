<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TqlRateService;
 

class TqlRateController extends Controller
{
    
/**
 * @OA\Post(
 *     path="/api/frontend/tql-rate",
 *     summary="Get TQL shipping rate (returns only the price amount)",
 *     tags={"Frontend-TQL Rates Shipping"},
 *      security={{ "bearerAuth": {} }},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"origin_zip","dest_zip","weight"},
 *             @OA\Property(property="origin_zip", type="string", example="45202"),
 *             @OA\Property(property="dest_zip", type="string", example="90210"),
 *             @OA\Property(property="dimensionLength", type="integer", example=40),
 *             @OA\Property(property="dimensionWidth", type="integer", example=45),
 *             @OA\Property(property="dimensionHeight", type="integer", example=55),
 *             @OA\Property(property="freightClassCode", type="70", example=1500),
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Plain text price only",
 *         @OA\MediaType(mediaType="text/plain", example="1247.50")
 *     )
 * )
 */

    public function tqlRates(Request $request, TqlRateService $service)
    {
        $shipmentData = $request->validate([
            'origin_zip' => 'required|string|size:5',
            'dest_zip' => 'required|string|size:5',
            'dimensionLength' => 'required|numeric|min:1',
            'dimensionWidth' => 'required|numeric|min:1',
            'dimensionHeight' => 'required|numeric|min:1',
            'freightClassCode' => 'required|numeric|min:1',
        ]);

        // Add defaults or more fields as needed
        $shipmentData['dimensions'] = ['length' => $request->dimensionLength, 'width' => $request->dimensionWidth, 'height' => $request->dimensionHeight];
        $shipmentData['freight_class'] = $request->freightClassCode;

        $rates = $service->getRates($shipmentData);
        
        if(!$rates){
            return response()->json([            
                    'success' => false,
                    'message' => 'not found shipment charge.',
                    'shipment-rates'=>$rates
                ], 201);
        }
         return response()->json([            
            'success' => true,
            'message' => 'Successfully tendered shipment.',
            'shipment-rates'=>$rates
        ], 201);

}

}
