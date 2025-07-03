<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Services\TaxJarService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TaxController extends Controller
{
    protected $taxService;

    public function __construct(TaxJarService $taxService)
    {
        $this->taxService = $taxService;
    }

    public function getRate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'zip' => 'required|string|max:10',
                'country' => 'string|size:2',
                'state' => 'string|max:3',
                'city' => 'string|max:255',
                'street' => 'string|max:255',
            ]);

            $rate = $this->taxService->getTaxRate($validated['zip'], $validated);
            
            return response()->json([
                'success' => true,
                'data' => $rate
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tax rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function calculateTax(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'to_country' => 'required|string|size:2',
                'to_zip' => 'required|string|max:10',
                'to_state' => 'string|max:3',
                'to_city' => 'string|max:255',
                'to_street' => 'string|max:255',
                'amount' => 'required|numeric|min:0',
                'shipping' => 'required|numeric|min:0',
                'from_country' => 'string|size:2',
                'from_zip' => 'string|max:10',
                'from_state' => 'string|max:3',
                'from_city' => 'string|max:255',
                'from_street' => 'string|max:255',
                'line_items' => 'array',
                'line_items.*.id' => 'string',
                'line_items.*.quantity' => 'integer|min:1',
                'line_items.*.product_tax_code' => 'string',
                'line_items.*.unit_price' => 'numeric|min:0',
                'line_items.*.discount' => 'numeric|min:0',
            ]);

            $tax = $this->taxService->calculateTax($validated);
            
            return response()->json([
                'success' => true,
                'data' => $tax
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate tax',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}