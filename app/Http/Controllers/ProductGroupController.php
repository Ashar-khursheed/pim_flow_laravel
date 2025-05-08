<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\ProductGroup;
use App\Models\ProductGroupItem;
use Illuminate\Support\Facades\Log;

class ProductGroupController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/generate-groups",
     *     summary="Generate Product Groups by Category",
     *     description="Runs a Python script to group products by category and saves them into the database as product groups.",
     *     operationId="generateProductGroups",
     *     tags={"Product Groups"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"category_id"},
     *             @OA\Property(property="category_id", type="integer", example=5, description="The ID of the category to process.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Groups saved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Groups saved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Category ID is required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error running script")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */

     public function generateGroups(Request $request)
     {
         $categoryId = $request->input('category_id');
     
         if (!$categoryId) {
             return response()->json(['error' => 'Category ID is required'], 400);
         }
     
         // Path to the Python script
         $scriptPath = base_path('app/Script/main.py');
     
         // Dynamically determine the Python command based on the environment
         $pythonCmd = base_path('venv/bin/python');
         
         // Set the working directory where the script is located
         $workingDirectory = base_path('app/Script');
         
         // Run the Python script with the category ID as an argument
         $process = new Process([$pythonCmd, $scriptPath, $categoryId], $workingDirectory);
         $process->run();
     
         // Check if the process ran successfully
         if (!$process->isSuccessful()) {
             Log::error("Python script execution failed: " . $process->getErrorOutput());
             return response()->json(['error' => 'Python script execution failed', 'details' => $process->getErrorOutput()], 500);
         }
     
         // Decode the output from the script
         $result = json_decode($process->getOutput(), true);
         if ($result === null) {
             return response()->json(['error' => 'Invalid JSON returned from Python script'], 500);
         }
     
         // Check if the script returned an error
         if (!$result['success']) {
             return response()->json(['error' => $result['message']], 500);
         }
     
         // Extract the grouped products data
         $data = $result['data'];
     
         // Process and save the grouped products
         try {
             foreach ($data as $groupName => $products) {
                 $group = ProductGroup::create(['name' => $groupName]);
     
                 foreach ($products as $product) {
                     ProductGroupItem::create([
                         'group_id' => $group->id,
                         'product_id' => $product['id']
                     ]);
                 }
             }
         } catch (\Exception $e) {
             return response()->json(['error' => 'Error saving groups: ' . $e->getMessage()], 500);
         }
     
         return response()->json(['message' => 'Groups saved successfully', 'data' => $data]);
     }
     
}
