<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GradingRule; // Ensure the GradingRule model is included

class GradingController extends Controller
{
     /**
     * @OA\Post(
     *     path="/api/calculate-grade",
     *     summary="Calculate Grade based on attributes and save to database",
     *     description="Accepts a list of attributes with obtained and total values, calculates the total score, percentage, and assigns a grade, and saves the data to the database.",
     *     tags={"Grading"},
     *     security={{"bearerAuth":{}}}, 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"attributes", "product_id"},
     *                 @OA\Property(
     *                     property="attributes",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="obtained", type="number", example=80),
     *                         @OA\Property(property="total", type="number", example=100)
     *                     )
     *                 ),
     *                 @OA\Property(property="product_id", type="integer", example=1683)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful grade calculation and save",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_obtained", type="number", example=400),
     *             @OA\Property(property="total_possible", type="number", example=500),
     *             @OA\Property(property="percentage", type="number", example=80),
     *             @OA\Property(property="grade", type="string", example="B"),
     *             @OA\Property(property="product_id", type="integer", example=1683)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The attributes field is required.")
     *         )
     *     )
     * )
     */
    public function calculate(Request $request)
    {
        // Validate incoming request
        $request->validate([
            'attributes' => 'required|array|min:1',
            'attributes.*.obtained' => 'required|numeric|min:0',
            'attributes.*.total' => 'required|numeric|min:1',
            'product_id' => 'required|exists:ec_products,id',  // Validate product_id
        ]);

        $attributes = $request->input('attributes');
        $totalObtained = 0;
        $totalPossible = 0;

        // Sum the obtained and total values to calculate percentage
        foreach ($attributes as $attr) {
            $totalObtained += $attr['obtained'];
            $totalPossible += $attr['total'];
        }

        $percentage = $totalPossible > 0 ? ($totalObtained / $totalPossible) * 100 : 0;

        // Dynamically calculate grade based on percentage
        $grade = $this->getGradeFromPercentage($percentage);

        // Save to database (grading_rules table)
        GradingRule::create([
            'product_id' => $request->input('product_id'),
            'grade' => $grade,
            'min_percentage' => $percentage - 10,  // Example: Adjust as needed
            'max_percentage' => $percentage + 10,  // Example: Adjust as needed
        ]);

        // Return the response with product_id and grade calculation details
        return response()->json([
            'product_id' => $request->input('product_id'),
            'total_obtained' => $totalObtained,
            'total_possible' => $totalPossible,
            'percentage' => round($percentage, 2),
            'grade' => $grade
        ]);
    }

    // Function to calculate grade based on percentage
    private function getGradeFromPercentage($percentage)
    {
        if ($percentage >= 90) {
            return 'A';
        } elseif ($percentage >= 80) {
            return 'B';
        } elseif ($percentage >= 70) {
            return 'C';
        } elseif ($percentage >= 60) {
            return 'D';
        } else {
            return 'F';
        }
    }

   

 /**
     * @OA\Get(
     *     path="/api/grading/view/{product_id}",
     *     summary="View grading rules for a specific product",
     *     description="Fetches all grading rules for a specific product using the product_id.",
     *     tags={"Grading"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="product_id",
     *         in="path",
     *         description="The ID of the product to fetch grading rules for",
     *         required=true,
     *         @OA\Schema(type="integer", example=1683)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Returns the grading rules for the specified product",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="product_id", type="integer", example=1683),
     *             @OA\Property(
     *                 property="grading_rules",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="grade", type="string", example="A"),
     *                     @OA\Property(property="min_percentage", type="number", example=90),
     *                     @OA\Property(property="max_percentage", type="number", example=100)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No grading rules found for the product",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No grading rules found for this product.")
     *         )
     *     )
     * )
     */

    public function viewByProduct($product_id)
    {
        // Fetch grading rules for the given product_id
        $gradingRules = GradingRule::where('product_id', $product_id)->get();

        // Check if grading rules exist
        if ($gradingRules->isEmpty()) {
            return response()->json([
                'message' => 'No grading rules found for this product.'
            ], 404);
        }

        return response()->json([
            'product_id' => $product_id,
            'grading_rules' => $gradingRules
        ]);
    }



    /**
     * @OA\Put(
     *     path="/api/grading/update/{product_id}",
     *     summary="Update Grade based on attributes and update in database",
     *     description="Accepts a list of attributes with obtained and total values, recalculates the grade and percentage, and updates the existing grading rule in the database.",
     *     tags={"Grading"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"attributes", "grading_rule_id"},
     *                 @OA\Property(
     *                     property="attributes",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="obtained", type="number", example=90),
     *                         @OA\Property(property="total", type="number", example=100)
     *                     )
     *                 ),
     *                 @OA\Property(property="grading_rule_id", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful grade update",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="grading_rule_id", type="integer", example=5),
     *             @OA\Property(property="total_obtained", type="number", example=450),
     *             @OA\Property(property="total_possible", type="number", example=500),
     *             @OA\Property(property="percentage", type="number", example=90),
     *             @OA\Property(property="grade", type="string", example="A")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Grading rule not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Grading rule not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The attributes field is required.")
     *         )
     *     )
     * )
     */
    public function updateGradingRule(Request $request)
    {
        // Validate input
        $request->validate([
            'attributes' => 'required|array|min:1',
            'attributes.*.obtained' => 'required|numeric|min:0',
            'attributes.*.total' => 'required|numeric|min:1',
            'grading_rule_id' => 'required|exists:grading_rules,id',
        ]);

        $attributes = $request->input('attributes');
        $totalObtained = 0;
        $totalPossible = 0;

        foreach ($attributes as $attr) {
            $totalObtained += $attr['obtained'];
            $totalPossible += $attr['total'];
        }

        $percentage = $totalPossible > 0 ? ($totalObtained / $totalPossible) * 100 : 0;
        $grade = $this->getGradeFromPercentage($percentage);

        // Find existing record
        $gradingRule = GradingRule::find($request->input('grading_rule_id'));

        if (!$gradingRule) {
            return response()->json(['message' => 'Grading rule not found.'], 404);
        }

        // Update record
        $gradingRule->update([
            'grade' => $grade,
            'min_percentage' => $percentage - 10,
            'max_percentage' => $percentage + 10,
        ]);

        return response()->json([
            'grading_rule_id' => $gradingRule->id,
            'total_obtained' => $totalObtained,
            'total_possible' => $totalPossible,
            'percentage' => round($percentage, 2),
            'grade' => $grade
        ]);
    }


    // Function to calculate the grade based on percentage of filled attributes
    private function calculateGrade($percentage)
    {
        if ($percentage >= 90) {
            return 'A';
        } elseif ($percentage >= 80) {
            return 'B';
        } elseif ($percentage >= 70) {
            return 'C';
        } elseif ($percentage >= 60) {
            return 'D';
        } else {
            return 'F';
        }
    }
}
