<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PreOnboardingVendor;

/**
 * @OA\Post(
 *     path="/api/supplier-score",
 *     summary="Calculate and store supplier score and grade based on existing data",
 *     tags={"Supplier Scores"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"id"},
 *             @OA\Property(property="id", type="integer", example=1, description="ID from pre_onboarding_vendors table")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Score and grade successfully calculated",
 *         @OA\JsonContent(
 *             @OA\Property(property="score", type="integer", example=48),
 *             @OA\Property(property="grade", type="string", example="B")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422, 
 *         description="Validation Error",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="The id field is required.")
 *         )
 *     ),
 *     @OA\Response(response=404, description="Supplier not found"),
 *     security={{"bearerAuth":{}}}
 * )
 */
class SupplierScoreController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:pre_onboarding_vendors,id',
        ]);

        // Find the supplier by ID
        $supplier = PreOnboardingVendor::find($validated['id']);
        
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }

        // --- Location Score ---
        $locationMap = [
            'Houston' => 10,
            'Texas' => 8,
            'Neighboring States' => 6,
        ];
        $locationScore = $locationMap[$supplier->location] ?? 4;

        // --- Drop Shipping ---
        $dropShippingScore = $supplier->drop_shipping === 'Yes' ? 10 : 6;

        // --- Shipping Days ---
        $shippingDays = $supplier->shipping_days;
        // Parse shipping days from string format (e.g., "5-7 days")
        preg_match('/(\d+)(?:-(\d+))?\s*days?/i', $shippingDays, $matches);
        $days = isset($matches[2]) ? (int)$matches[2] : (isset($matches[1]) ? (int)$matches[1] : 10);
        
        if ($days <= 2) $shippingScore = 10;
        elseif ($days <= 4) $shippingScore = 8;
        elseif ($days <= 6) $shippingScore = 6;
        else $shippingScore = 4;

        // --- Margin % ---
        $margin = $supplier->margin_percent;
        if ($margin >= 30) $marginScore = 10;
        elseif ($margin >= 20) $marginScore = 8;
        elseif ($margin >= 10) $marginScore = 6;
        else $marginScore = 4;

        // --- Credit Terms ---
        $creditScore = match($supplier->credit_terms) {
            'Net 30' => 2,
            'Net 15' => 1,
            'Advance' => 0,
            default => 0,
        };

        // --- Product Demand ---
        $demand = 0;
        try {
            $productData = json_decode($supplier->product_demand_level, true);
            if (is_array($productData) && count($productData) > 0) {
                foreach ($productData as $product) {
                    if (isset($product['search_volume'])) {
                        $demand += $product['search_volume'];
                    }
                }
            }
        } catch (\Exception $e) {
            // If JSON parsing fails, set demand to 0
            $demand = 0;
        }
        
        if ($demand >= 10000) $demandScore = 10;
        elseif ($demand >= 5000) $demandScore = 8;
        elseif ($demand >= 2000) $demandScore = 5;
        else $demandScore = 3;

        // --- Total Score ---
        $totalScore = $locationScore + $dropShippingScore + $shippingScore + $marginScore + $creditScore + $demandScore;

        $grade = null;
        if ($totalScore >= 90) {
            $grade = 'A';
        } elseif ($totalScore >= 75) {
            $grade = 'B';
        } elseif ($totalScore >= 65) {
            $grade = 'C';
        } elseif ($totalScore >= 55) {
            $grade = 'D';
        } else {
            $grade = 'E';
        }

        // Update the score and grade fields
        $supplier->score = $totalScore;
        $supplier->grade = $grade;
        $supplier->updated_by = auth()->id() ?? 1;
        $supplier->save();

        return response()->json([
            'score' => $totalScore,
            'grade' => $grade
        ]);
    }
}