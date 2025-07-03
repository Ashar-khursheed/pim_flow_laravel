<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TaxJarService;


class TaxController extends Controller
{
    protected $taxService;

    public function __construct(TaxJarService $taxService)
    {
        $this->taxService = $taxService;
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/tax/rate",
     *     operationId="getTaxRate",
     *     tags={"Tax"},
     *     summary="Get tax rate by ZIP and optional address",
     *     @OA\Parameter(
     *         name="zip",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", default="US")
     *     ),
     *     @OA\Parameter(
     *         name="state",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="street",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax rate retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="TaxJar error"
     *     )
     * )
     */
    public function getRate(Request $request)
    {
        $zip = $request->input('zip');
        $country = $request->input('country', 'US');
        $params = [
            'country' => $country,
            'state' => $request->input('state'),
            'city' => $request->input('city'),
            'street' => $request->input('street'),
        ];

        try {
            $rate = $this->taxService->getTaxRate($zip, $params);
            return response()->json($rate);
        } catch (\Exception $e) {
            return response()->json(['error' => 'TaxJar error: ' . $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="api/frontend/tax/calculate",
     *     operationId="calculateTax",
     *     tags={"Tax"},
     *     summary="Calculate sales tax for an order",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_country", "from_zip", "to_country", "to_zip", "amount", "shipping"},
     *             @OA\Property(property="from_country", type="string", example="US"),
     *             @OA\Property(property="from_zip", type="string", example="92093"),
     *             @OA\Property(property="from_state", type="string", example="CA"),
     *             @OA\Property(property="from_city", type="string", example="San Diego"),
     *             @OA\Property(property="from_street", type="string", example="9500 Gilman Drive"),
     *             @OA\Property(property="to_country", type="string", example="US"),
     *             @OA\Property(property="to_zip", type="string", example="90002"),
     *             @OA\Property(property="to_state", type="string", example="CA"),
     *             @OA\Property(property="to_city", type="string", example="Los Angeles"),
     *             @OA\Property(property="to_street", type="string", example="123 Main St"),
     *             @OA\Property(property="amount", type="number", format="float", example=15.0),
     *             @OA\Property(property="shipping", type="number", format="float", example=1.5),
     *             @OA\Property(property="nexus_addresses", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="line_items", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax calculated successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Tax calculation failed"
     *     )
     * )
     */
    public function calculateTax(Request $request)
    {
        $order = $request->only([
            'from_country', 'from_zip', 'from_state', 'from_city', 'from_street',
            'to_country', 'to_zip', 'to_state', 'to_city', 'to_street',
            'amount', 'shipping', 'nexus_addresses', 'line_items'
        ]);

        try {
            $tax = $this->taxService->calculateSalesTax($order);
            return response()->json($tax);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Tax calculation failed: ' . $e->getMessage()], 400);
        }
    }
}
