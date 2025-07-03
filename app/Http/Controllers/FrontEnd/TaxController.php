<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxRateRequest;
use App\Http\Requests\TaxCalculationRequest;
use App\Services\TaxJarService;
use Illuminate\Http\JsonResponse;

class TaxController extends Controller
{
    protected $taxService;

    public function __construct(TaxJarService $taxService)
    {
        $this->taxService = $taxService;
    }

    public function getRate(TaxRateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $rate = $this->taxService->getTaxRate($validated['zip'], $validated);
            
            return response()->json([
                'success' => true,
                'data' => $rate,
                'environment' => $this->taxService->isProduction() ? 'production' : 'sandbox'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tax rate',
                'error' => $e->getMessage(),
                'environment' => $this->taxService->isProduction() ? 'production' : 'sandbox'
            ], 500);
        }
    }

    public function calculateTax(TaxCalculationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $tax = $this->taxService->calculateTax($validated);
            
            return response()->json([
                'success' => true,
                'data' => $tax,
                'environment' => $this->taxService->isProduction() ? 'production' : 'sandbox'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate tax',
                'error' => $e->getMessage(),
                'environment' => $this->taxService->isProduction() ? 'production' : 'sandbox'
            ], 500);
        }
    }

    // Test endpoint to verify production setup
    public function testConnection(): JsonResponse
    {
        try {
            // Test with a simple rate lookup
            $testRate = $this->taxService->getTaxRate('90210', ['country' => 'US']);
            
            return response()->json([
                'success' => true,
                'message' => 'TaxJar connection successful',
                'environment' => $this->taxService->isProduction() ? 'production' : 'sandbox',
                'test_rate' => $testRate
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'TaxJar connection failed',
                'error' => $e->getMessage(),
                'environment' => $this->taxService->isProduction() ? 'production' : 'sandbox'
            ], 500);
        }
    }
}