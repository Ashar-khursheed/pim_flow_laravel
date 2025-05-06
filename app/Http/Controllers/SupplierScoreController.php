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
 *             @OA\Property(property="score", type="integer", example=75),
 *             @OA\Property(property="grade", type="string", example="B"),
 *             @OA\Property(property="breakdown", type="object")
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
    // Define weights for each scoring category (total = 100)
    const WEIGHTS = [
        'location' => 20,    // 20% of total score
        'drop_shipping' => 15, // 15% of total score
        'shipping' => 20,    // 20% of total score
        'margin' => 25,      // 25% of total score
        'credit' => 5,       // 5% of total score
        'demand' => 15       // 15% of total score
    ];

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

        // Parse product data from JSON
        $productData = $this->parseProductData($supplier->product_demand_level);
        
        // --- Location Score ---
        $locationRawScore = $this->calculateLocationScore($supplier->location);
        $locationScore = $locationRawScore * (self::WEIGHTS['location'] / 10); // Convert to weighted score

        // --- Drop Shipping Score ---
        $dropShippingRawScore = $supplier->drop_shipping === 'Yes' ? 10 : 6;
        $dropShippingScore = $dropShippingRawScore * (self::WEIGHTS['drop_shipping'] / 10);

        // --- Shipping Days Score ---
        $shippingRawScore = $this->calculateShippingScore($supplier->shipping_days);
        $shippingScore = $shippingRawScore * (self::WEIGHTS['shipping'] / 10);

        // --- Product Demand Score ---
        $demandRawScore = $this->calculateDemandScore($productData['total_search_volume']);
        $demandScore = $demandRawScore * (self::WEIGHTS['demand'] / 10);

        // --- Margin % Score ---
        // Use either the calculated margin from product data or the provided margin_percent field
        $marginPercent = !empty($productData['average_margin']) 
            ? $productData['average_margin'] 
            : $supplier->margin_percent;
            
        $marginRawScore = $this->calculateMarginScore($marginPercent);
        $marginScore = $marginRawScore * (self::WEIGHTS['margin'] / 10);

        // --- Credit Terms Score ---
        // Convert the 0-2 scale to a 0-10 scale for weighting
        $creditTermsRaw = match($supplier->credit_terms) {
            'Net 30' => 2,
            'Net 15' => 1,
            'Advance' => 0,
            default => 0,
        };
        $creditScore = ($creditTermsRaw / 2) * 10 * (self::WEIGHTS['credit'] / 10);

        // --- Total Score ---
        $totalScore = round(
            $locationScore + 
            $dropShippingScore + 
            $shippingScore + 
            $marginScore + 
            $creditScore + 
            $demandScore
        );

        // Determine grade based on total score
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

        // For debugging - include individual scores
        $scoreBreakdown = [
            'location' => [
                'raw_score' => $locationRawScore,
                'weighted_score' => $locationScore,
                'weight' => self::WEIGHTS['location']
            ],
            'drop_shipping' => [
                'raw_score' => $dropShippingRawScore,
                'weighted_score' => $dropShippingScore,
                'weight' => self::WEIGHTS['drop_shipping']
            ],
            'shipping' => [
                'raw_score' => $shippingRawScore,
                'weighted_score' => $shippingScore,
                'weight' => self::WEIGHTS['shipping']
            ],
            'margin' => [
                'raw_score' => $marginRawScore,
                'weighted_score' => $marginScore,
                'weight' => self::WEIGHTS['margin'],
                'margin_percent_used' => $marginPercent,
                'margin_source' => !empty($productData['average_margin']) ? 'product_data' : 'margin_percent'
            ],
            'credit' => [
                'raw_score' => $creditTermsRaw,
                'weighted_score' => $creditScore,
                'weight' => self::WEIGHTS['credit']
            ],
            'demand' => [
                'raw_score' => $demandRawScore,
                'weighted_score' => $demandScore,
                'weight' => self::WEIGHTS['demand'],
                'total_search_volume' => $productData['total_search_volume']
            ],
            'product_data' => $productData['products'],
            'total_score' => $totalScore
        ];

        // Update the score and grade fields
        $supplier->score = $totalScore;
        $supplier->grade = $grade;
        $supplier->updated_by = auth()->id() ?? 1;
        $supplier->save();

        return response()->json([
            'score' => $totalScore,
            'grade' => $grade,
            'breakdown' => $scoreBreakdown
        ]);
    }

    /**
     * Parse product data JSON and calculate aggregated values
     * @return array Parsed product data with total search volume and average margin
     */
    private function parseProductData($productJsonData)
    {
        $result = [
            'total_search_volume' => 0,
            'average_margin' => 0,
            'products' => []
        ];
        
        try {
            $products = json_decode($productJsonData, true);
            
            if (!is_array($products) || empty($products)) {
                return $result;
            }
            
            $totalMargin = 0;
            $validMarginProducts = 0;
            
            foreach ($products as $product) {
                // Extract and validate search volume
                $searchVolume = isset($product['search_volume']) ? intval($product['search_volume']) : 0;
                $result['total_search_volume'] += $searchVolume;
                
                // Calculate and track margin if possible
                $supplierPrice = isset($product['supplier_price']) ? floatval($product['supplier_price']) : 0;
                $competitorPrice = isset($product['competitor_price_online']) 
                    ? floatval($product['competitor_price_online']) 
                    : (isset($product['competitor_price_offline']) ? floatval($product['competitor_price_offline']) : 0);
                
                $margin = 0;
                if ($supplierPrice > 0 && $competitorPrice > 0) {
                    // Calculate margin percentage
                    $margin = (($competitorPrice - $supplierPrice) / $competitorPrice) * 100;
                    $totalMargin += $margin;
                    $validMarginProducts++;
                } elseif (isset($product['margin_auto_calculate']) && is_numeric($product['margin_auto_calculate'])) {
                    // Use pre-calculated margin if available
                    $margin = floatval($product['margin_auto_calculate']);
                    $totalMargin += $margin;
                    $validMarginProducts++;
                }
                
                // Store processed product data
                $result['products'][] = [
                    'name' => $product['product_name'] ?? 'Unknown',
                    'search_volume' => $searchVolume,
                    'margin_percent' => round($margin, 2)
                ];
            }
            
            // Calculate average margin if we have valid data
            if ($validMarginProducts > 0) {
                $result['average_margin'] = round($totalMargin / $validMarginProducts, 2);
            }
            
        } catch (\Exception $e) {
            // In case of any error, return the default empty result
        }
        
        return $result;
    }

    /**
     * Calculate location score based on the requirements
     * @return int Score from 0-10
     */
    private function calculateLocationScore($location)
    {
        if (empty($location)) {
            return 4; // Default for unknown locations
        }

        // Check for Houston specifically
        if (stripos($location, 'Houston') !== false) {
            return 10;
        }
        
        // Check for Texas
        if (stripos($location, 'Texas') !== false || stripos($location, 'TX') !== false) {
            return 8;
        }
        
        // Check for neighboring states (LA, AR, OK, NM)
        $neighboringStates = ['Louisiana', 'LA', 'Arkansas', 'AR', 'Oklahoma', 'OK', 'New Mexico', 'NM'];
        foreach ($neighboringStates as $state) {
            if (stripos($location, $state) !== false) {
                return 6;
            }
        }
        
        // Any other location
        return 4;
    }

    /**
     * Calculate shipping score based on number of days
     * @return int Score from 0-10
     */
    private function calculateShippingScore($shippingDays)
    {
        // Parse shipping days from string format (e.g., "5-7 days")
        preg_match('/(\d+)(?:-(\d+))?\s*days?/i', $shippingDays, $matches);
        
        if (empty($matches)) {
            // Try to match weeks format
            preg_match('/(\d+)(?:-(\d+))?\s*weeks?/i', $shippingDays, $matches);
            if (!empty($matches)) {
                // Convert weeks to days (approximate)
                $days = isset($matches[2]) ? (int)$matches[2] * 7 : (int)$matches[1] * 7;
            } else {
                $days = 10; // Default if no pattern matches
            }
        } else {
            // Use the higher number if a range is provided
            $days = isset($matches[2]) ? (int)$matches[2] : (int)$matches[1];
        }
        
        // Scoring based on requirements
        if ($days <= 2) return 10;
        elseif ($days <= 4) return 8;
        elseif ($days <= 6) return 6;
        else return 4;
    }

    /**
     * Calculate product demand score based on search volume
     * @return int Score from 0-10
     */
    private function calculateDemandScore($searchVolume)
    {
        // Scoring based on requirements
        if ($searchVolume >= 10000) return 10;
        elseif ($searchVolume >= 5000) return 8;
        elseif ($searchVolume >= 2000) return 5;
        else return 3;
    }

    /**
     * Calculate margin score based on percentage
     * @return int Score from 0-10
     */
    private function calculateMarginScore($marginPercent)
    {
        if ($marginPercent >= 30) return 10;
        elseif ($marginPercent >= 20) return 8;
        elseif ($marginPercent >= 10) return 6;
        else return 4;
    }
}