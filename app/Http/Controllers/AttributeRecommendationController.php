<?php
namespace App\Http\Controllers;

use App\Models\AttributeRecommendation;
use App\Models\ProductGroupItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class AttributeRecommendationController extends Controller
{
    /**
     * Generate attribute recommendations for product groups using AI-powered Python script
     *
     * @OA\Post(
     *     path="/api/generate-recommendations",
     *     summary="Generate attribute recommendations for product groups",
     *     description="Generates common attributes and variant combinations for grouped products using an AI script, and stores them in the attribute_recommendations table.",
     *     tags={"Recommendations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="groups",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="parent_id", type="integer", description="Parent group ID"),
     *                     @OA\Property(
     *                         property="child_ids",
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         description="Array of product IDs"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Recommendations generated and stored.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="stored", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="AI processing failed or Python script error.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="details", type="string")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function generate(Request $request)
    {
        try {
            // Validate the incoming request
            $request->validate([
                'groups' => 'required|array',
                'groups.*.parent_id' => 'required|integer',
                'groups.*.child_ids' => 'required|array',
                'groups.*.child_ids.*' => 'integer',
            ]);

            // Get the 'groups' data from the request
            $groups = $request->input('groups');

            // Prepare the environment configuration for the Python script
            $env = $this->prepareEnvironmentConfig();

            // Log the input data with sensitive information masked
            $this->logInputData($env, $groups);

            // Specify the path to the Python script
            $scriptPath = base_path('app/Script/rec.py');
            $pythonCmd = env('PYTHON_PATH', base_path('venv/bin/python'));
            $workingDirectory = base_path('app/Script');

            // Prepare the input data as a JSON string
            $inputJson = json_encode($groups);

            // Run the Python script
            $process = new Process(
                [$pythonCmd, $scriptPath],
                $workingDirectory,
                null,
                $inputJson,
                120 // Increased timeout to 2 minutes
            );
            
            $process->run();

            // Handle process execution errors
            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                Log::error("Python script execution failed", [
                    'error' => $errorOutput,
                    'command' => $process->getCommandLine()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Python script execution failed',
                    'details' => $errorOutput
                ], 500);
            }

            // Get and parse the output
            $output = $process->getOutput();
            $result = $this->parseScriptOutput($output);

            // Process and store recommendations
            return $this->processRecommendations($result);

        } catch (ValidationException $e) {
            // Handle validation errors
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            // Handle unexpected errors
            Log::error("Recommendation generation failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepare environment configuration for the Python script
     *
     * @return array
     */
    private function prepareEnvironmentConfig(): array
    {
        return [
            'CLAUDE_API_KEY' => env('CLAUDE_API_KEY'),
            'CLAUDE_VERSION' => env('CLAUDE_VERSION'),
            'CLAUDE_API_URL' => env('CLAUDE_API_URL'),
            'CLAUDE_MODEL' => env('CLAUDE_MODEL', 'claude-3-sonnet-20240229'),

            // Database credentials
            'DB_HOST' => env('DB_HOST'),
            'DB_DATABASE' => env('DB_DATABASE'),
            'DB_USERNAME' => env('DB_USERNAME'),
            'DB_PASSWORD' => env('DB_PASSWORD'),
        ];
    }

    /**
     * Log input data with sensitive information masked
     *
     * @param array $env
     * @param array $groups
     */
    private function logInputData(array $env, array $groups): void
    {
        Log::info("Generating recommendations", [
            'env_vars' => array_map(function($val) { 
                return is_string($val) ? substr($val, 0, 3) . '...' : $val; 
            }, $env),
            'groups_count' => count($groups),
            'group_ids' => array_column($groups, 'parent_id')
        ]);
    }

    /**
     * Parse the output from the Python script
     *
     * @param string $output
     * @return array
     * @throws \Exception
     */
    private function parseScriptOutput(string $output): array
    {
        // Try to decode the JSON output
        $result = json_decode($output, true);
        
        // Check if JSON decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Failed to parse JSON output", [
                'raw_output' => $output,
                'json_error' => json_last_error_msg()
            ]);

            throw new \Exception('Invalid JSON response from Python script');
        }

        // Validate the result structure
        if (!isset($result['success']) || $result['success'] !== true) {
            Log::error("Python script returned an error", [
                'error' => $result['error'] ?? 'Unknown error',
                'output' => $output
            ]);

            throw new \Exception($result['error'] ?? 'AI processing failed');
        }

        // Validate families array
        if (!isset($result['families']) || !is_array($result['families'])) {
            throw new \Exception('Invalid result structure: missing families array');
        }

        return $result;
    }

    /**
     * Process and store recommendations
     *
     * @param array $result
     * @return \Illuminate\Http\JsonResponse
     */
    private function processRecommendations(array $result)
    {
        $stored = 0;
        $processedFamilyIds = [];

        // Store or update attribute recommendations
        foreach ($result['families'] as $family) {
            $recommendation = AttributeRecommendation::updateOrCreate(
                ['parent_id' => $family['parent_id']],
                [
                    'family_name' => $family['family_name'],
                    'common_attributes' => json_encode($family['common_attributes']),
                    'variants' => json_encode($family['variants']),
                ]
            );

            $stored++;
            $processedFamilyIds[] = $family['parent_id'];
        }

        // Log the successful processing
        Log::info("Recommendations processed", [
            'stored' => $stored,
            'family_ids' => $processedFamilyIds
        ]);

        // Return success response
        return response()->json([
            'success' => true,
            'stored' => $stored,
            'processed_families' => $processedFamilyIds
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/recommendations",
     *     operationId="getAttributeRecommendations",
     *     tags={"Recommendations"},
     *     summary="Get all attribute recommendations",
     *     description="Returns a list of all attribute recommendations",
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="parent_id", type="integer", example=101),
     *                 @OA\Property(property="family_name", type="string", example="Shirts Family"),
     *                 @OA\Property(property="common_attributes", type="array", @OA\Items(type="string"), example={"color", "size", "material"}),
     *                 @OA\Property(property="variants", type="array", @OA\Items(type="object", @OA\Property(property="color", type="string"), @OA\Property(property="size", type="string"))),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-01T12:00:00Z")
     *             )
     *         )
     *     ),
     *    security={{"bearerAuth":{}}}
     * )
     */


    public function index()
    {
        return response()->json(AttributeRecommendation::all());
    }


   /**
     * @OA\Get(
     *     path="/api/recommendations/{id}",
     *     operationId="showAttributeRecommendation",
     *     tags={"Recommendations"},
     *     summary="Get a specific attribute recommendation",
     *     description="Returns a specific attribute recommendation by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the attribute recommendation",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="parent_id", type="integer", example=101),
     *             @OA\Property(property="family_name", type="string", example="Shirts Family"),
     *             @OA\Property(property="common_attributes", type="array", @OA\Items(type="string"), example={"color", "size", "material"}),
     *             @OA\Property(property="variants", type="array", @OA\Items(type="object", @OA\Property(property="color", type="string"), @OA\Property(property="size", type="string"))),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-01T12:00:00Z"),
     *             @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-01T12:00:00Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Attribute recommendation not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Not found")
     *         )
     *     ),
     *    security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        $recommendation = AttributeRecommendation::find($id);

        if (!$recommendation) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($recommendation, 200);
    }


    /**
     * @OA\Put(
     *     path="/api/recommendations/{id}",
     *     operationId="updateAttributeRecommendation",
     *     tags={"Recommendations"},
     *     summary="Update an attribute recommendation",
     *     description="Updates a specific attribute recommendation by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the attribute recommendation",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="parent_id", type="integer", example=101),
     *             @OA\Property(property="family_name", type="string", example="Shirts Family"),
     *             @OA\Property(property="common_attributes", type="array", @OA\Items(type="string"), example={"color", "size", "material"}),
     *             @OA\Property(property="variants", type="array", @OA\Items(type="object", @OA\Property(property="color", type="string"), @OA\Property(property="size", type="string")))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="parent_id", type="integer", example=101),
     *             @OA\Property(property="family_name", type="string", example="Shirts Family"),
     *             @OA\Property(property="common_attributes", type="array", @OA\Items(type="string"), example={"color", "size", "material"}),
     *             @OA\Property(property="variants", type="array", @OA\Items(type="object", @OA\Property(property="color", type="string"), @OA\Property(property="size", type="string"))),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-01T12:00:00Z"),
     *             @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-01T12:00:00Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Attribute recommendation not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Not found")
     *         )
     *     ),
     *    security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request, $id)
    {
        $recommendation = AttributeRecommendation::find($id);

        if (!$recommendation) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Validate and update the recommendation
        $request->validate([
            'parent_id' => 'required|integer',
            'family_name' => 'required|string',
            'common_attributes' => 'required|array',
            'variants' => 'required|array',
        ]);

        $recommendation->update($request->all());

        return response()->json($recommendation, 200);
    }



   /**
     * @OA\Delete(
     *     path="/api/recommendations/{id}",
     *     operationId="deleteAttributeRecommendation",
     *     tags={"Recommendations"},
     *     summary="Delete an attribute recommendation",
     *     description="Deletes a specific attribute recommendation by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the attribute recommendation",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully deleted",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Successfully deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Attribute recommendation not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Not found")
     *         )
     *     ),
     *    security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id)
    {
        $recommendation = AttributeRecommendation::find($id);

        if (!$recommendation) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $recommendation->delete();

        return response()->json(['message' => 'Successfully deleted'], 200);
    }

}
