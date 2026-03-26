<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;

use App\Helpers\CurrencyConverter;

class CurrencyController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/convert-currency",
	 *     summary="Convert currency",
	 *     description="Convert amount from source currency to all currencies or a specific target currency.",
	 *     tags={"Frontend-Currencies"},
	 *     @OA\Parameter(name="from", in="query", required=true, description="Source currency code", example="AED", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="amount", in="query", required=true, description="Amount to convert", example=100, @OA\Schema(type="number", minimum=0)),
	 *     @OA\Parameter(name="to", in="query", required=false, description="Target currency code", example="INR", @OA\Schema(type="string")),
	 *     @OA\Response(response=200, description="Currency converted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function convertAll(Request $request)
	{
	    $request->validate([
	        'from'   => 'required|string|size:3',
	        'amount' => 'required|numeric|min:0',
	        'to'     => 'nullable|string|size:3',
	    ]);

	    $from   = strtoupper($request->input('from'));
	    $amount = (float) $request->input('amount');
	    $to     = $request->filled('to') ? strtoupper($request->input('to')) : null;

	    $availableCurrencies = CurrencyConverter::getAvailableCurrencies();

	    if (empty($availableCurrencies)) {
	        return response()->json(['success' => false, 'message' => 'Exchange rates not available.']);
	    }

	    if (!in_array($from, $availableCurrencies)) {
	        return response()->json(['success' => false, 'message' => "Source currency '{$from}' not found."]);
	    }

	    /* If "to" is provided, return single conversion */
	    if ($to) {
	        if (!in_array($to, $availableCurrencies)) {
	            return response()->json(['success' => false, 'message' => "Target currency '{$to}' not found."]);
	        }

	        return response()->json([
	            'success' => true,
	            'data'    => [
	                $to => CurrencyConverter::convertCurrency($from, $to, $amount),
	            ],
	        ], 200);
	    }

	    /* Otherwise return all currencies */
	    $conversions = [];
	    foreach ($availableCurrencies as $currency) {
	        $conversions[$currency] = CurrencyConverter::convertCurrency($from, $currency, $amount);
	    }

	    return response()->json([
	        'success' => true,
	        'data'    => $conversions,
	    ], 200);
	}

	
}