<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Services\TaxJarService;

class TaxController extends Controller
{
    protected $taxService;

    public function __construct(TaxJarService $taxService)
    {
        $this->taxService = $taxService;
    }

    public function getRate(Request $request)
    {
        $rate = $this->taxService->getTaxRate($request->zip, $request->all());
        return response()->json($rate);
    }

    public function calculateTax(Request $request)
    {
        $validated = $request->validate([
            'to_country' => 'required|string',
            'to_zip' => 'required|string',
            'amount' => 'required|numeric',
            'shipping' => 'required|numeric',
        ]);

        $tax = $this->taxService->calculateTax($validated);
        return response()->json($tax);
    }
}
