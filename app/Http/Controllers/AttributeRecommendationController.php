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

    // public function generate(Request $request)
    // {
    //     // Validate the incoming request to ensure the payload format is correct
    //     $request->validate([
    //         'groups' => 'required|array',
    //         'groups.*.parent_id' => 'required|integer',
    //         'groups.*.child_ids' => 'required|array',
    //         'groups.*.child_ids.*' => 'integer',
    //     ]);

    //     // Get the 'groups' data from the request
    //     $groups = $request->input('groups'); // Array of parent-child relationships

    //     // Prepare the input for the Python script
    //     $input = [];
    //     foreach ($groups as $group) {
    //         $input[] = [
    //             'parent_id' => $group['parent_id'],
    //             'child_ids' => $group['child_ids'],
    //         ];
    //     }

    //     // Set up the environment variables for the Python script
    //     $env = [
    //         'CLAUDE_API_KEY' => env('CLAUDE_API_KEY'),
    //         'CLAUDE_VERSION' => env('CLAUDE_VERSION'),
    //         'CLAUDE_API_URL' => env('CLAUDE_API_URL'),
    //         'CLAUDE_MODEL' => env('CLAUDE_MODEL'),
    //     ];

    //     // Specify the path to the Python script and the Python executable
    //     $scriptPath = base_path('app/Script/rec.py');
    //     $pythonCmd = base_path('venv/bin/python');
    //     $workingDirectory = base_path('app/Script');

    //     // Run the Python script
    //     $process = new Process([$pythonCmd, $scriptPath, json_encode($env), json_encode($input)], $workingDirectory);
    //     $process->run();

    //     // Handle errors if the process is not successful
    //     if (!$process->isSuccessful()) {
    //         Log::error("Python script execution failed: " . $process->getErrorOutput());
    //         return response()->json([
    //             'error' => 'Python script execution failed',
    //             'details' => $process->getErrorOutput(),
    //         ], 500);
    //     }

    //     // Decode the result from the Python script
    //     $result = json_decode($process->getOutput(), true);

    //     // Handle case if the AI processing did not return a valid result
    //     if (!$result || !$result['success']) {
    //         return response()->json([
    //             'error' => $result['error'] ?? 'AI processing failed',
    //             'details' => $process->getOutput(),
    //         ], 500);
    //     }

    //     // Update or create AttributeRecommendations based on the result
    //     foreach ($result['families'] as $family) {
    //         AttributeRecommendation::updateOrCreate(
    //             ['parent_id' => $family['parent_id']],
    //             [
    //                 'family_name' => $family['family_name'],
    //                 'common_attributes' => $family['common_attributes'],
    //                 'variants' => $family['variants'],
    //             ]
    //         );
    //     }

    //     // Return a success response
    //     return response()->json([
    //         'success' => true,
    //         'stored' => count($result['families']),
    //     ]);
    // }

        public function generate(Request $request)
        {
            // Validate the incoming request
            $request->validate([
                'groups' => 'required|array',
                'groups.*.parent_id' => 'required|integer',
                'groups.*.child_ids' => 'required|array',
                'groups.*.child_ids.*' => 'integer',
            ]);

            // Get the 'groups' data from the request
            $groups = $request->input('groups');

            // Prepare the input for the Python script - keep the same structure
            $input = $groups; // No need to restructure, pass as is

            // Set up the environment variables for the Python script
            $env = [
                'CLAUDE_API_KEY' => env('CLAUDE_API_KEY'),
                'CLAUDE_VERSION' => env('CLAUDE_VERSION'),
                'CLAUDE_API_URL' => env('CLAUDE_API_URL'),
                'CLAUDE_MODEL' => env('CLAUDE_MODEL', 'claude-3-sonnet-20240229'),

                // Pass the database credentials as well
                'DB_HOST' => env('DB_HOST'),
                'DB_DATABASE' => env('DB_DATABASE'),
                'DB_USERNAME' => env('DB_USERNAME'),
                'DB_PASSWORD' => env('DB_PASSWORD'),
            ];

            // Log the data being sent
            Log::debug("Sending to Python script:", [
                'env_vars' => array_map(function($val) { 
                    return substr($val, 0, 3) . '...'; // Truncate sensitive values
                }, $env),
                'input_data' => $input
            ]);

            // Specify the path to the Python script
            $scriptPath = base_path('app/Script/rec.py');
            $pythonCmd = env('PYTHON_PATH', base_path('venv/bin/python'));
            $workingDirectory = base_path('app/Script');

            // Run the Python script with proper arguments
            $process = new Process(
                [$pythonCmd, $scriptPath, json_encode($env), json_encode($input)],
                $workingDirectory,
                null,
                null,
                60 // Increase timeout to 60 seconds
            );
            
            $process->run();

            // Handle errors if the process is not successful
            if (!$process->isSuccessful()) {
                Log::error("Python script execution failed: " . $process->getErrorOutput());
                return response()->json([
                    'error' => 'Python script execution failed',
                    'details' => $process->getErrorOutput(),
                    'command' => $process->getCommandLine(),
                ], 500);
            }

            // Get the output
            $output = $process->getOutput();
            Log::debug("Python script output: " . substr($output, 0, 500) . "...");

            // Try to decode the result
            $result = json_decode($output, true);
            
            // Check if the result is valid
            if (!$result || json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Failed to decode JSON output: " . json_last_error_msg());
                return response()->json([
                    'error' => 'Invalid JSON response from Python script',
                    'details' => $output,
                ], 500);
            }

            // Check if the result indicates success
            if (!isset($result['success']) || $result['success'] !== true) {
                return response()->json([
                    'error' => $result['error'] ?? 'AI processing failed',
                    'details' => $output,
                ], 500);
            }

            // Process successful results
            if (!isset($result['families']) || !is_array($result['families'])) {
                return response()->json([
                    'error' => 'Invalid result structure: missing families array',
                    'details' => $output,
                ], 500);
            }

            // Update or create AttributeRecommendations based on the result
            $stored = 0;
            foreach ($result['families'] as $family) {
                AttributeRecommendation::updateOrCreate(
                    ['parent_id' => $family['parent_id']],
                    [
                        'family_name' => $family['family_name'],
                        'common_attributes' => json_encode($family['common_attributes']),
                        'variants' => json_encode($family['variants']),
                    ]
                );
                $stored++;
            }

            // Return a success response
            return response()->json([
                'success' => true,
                'stored' => $stored,
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
