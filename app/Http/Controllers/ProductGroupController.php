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


        /**
     * @OA\Get(
     *     path="/api/product-groups",
     *     summary="Get Grouped Product List",
     *     description="Fetches a list of product groups with their related products, including brand, image, categories, and taxonomy.",
     *     tags={"Product Groups"},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             additionalProperties={
     *                 @OA\Property(
     *                     property="BakeMax BM",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="sku", type="string", example="BMPM007"),
     *                         @OA\Property(property="image", type="string"),
     *                         @OA\Property(property="brand", type="string", example="BakeMax"),
     *                         @OA\Property(property="store", type="string", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="published"),
     *                         @OA\Property(property="product_family", type="array", @OA\Items(type="string", example="Food Preparation Equipment")),
     *                         @OA\Property(property="taxonomy_path", type="string", example="bakemax-bmpm007-181-countertop-planetary-mixer-7-qt")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */


     public function getGroupedProductDetails()
     {
         $groups = ProductGroup::with(['items.product' => function ($query) {
             $query->with(['brand', 'categories', 'slug']);
         }])->get();
     
         $result = [];
     
         foreach ($groups as $group) {
             $products = [];
     
             foreach ($group->items as $item) {
                 $product = $item->product;
     
                 if (!$product) continue;
     
                 $products[] = [
                     'id' => $product->id,
                     'name' => $product->name,
                     'sku' => $product->sku,
                     'image' => $product->image ? asset('storage/products/' . basename($product->image)) : null,
                     'brand' => optional($product->brand)->name,
                     'store' => null,
                     'status' => $product->status,
                     'product_family' => $product->categories->pluck('name')->unique()->values()->all(),
                     'taxonomy_path' => optional($product->slug)->key,
                 ];
             }
     
             $result[$group->name] = $products;
         }
     
         return $result;
     }

  /**
     * @OA\Put(
     *     path="/api/product-groups/{group_id}/items/{item_id}/parent",
     *     summary="Update Parent of Product Group Item",
     *     description="Updates the parent of a product group item, allowing it to be reassigned to a different group.",
     *     tags={"Product Groups"},
     *     @OA\Parameter(
     *         name="group_id",
     *         in="path",
     *         required=true,
     *         description="The ID of the product group to which the item currently belongs.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="item_id",
     *         in="path",
     *         required=true,
     *         description="The ID of the product group item whose parent group is being updated.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="new_group_id", type="integer", description="The ID of the new parent product group")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Parent updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Parent updated successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product Group or Product Group Item not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */

    public function updateProductGroupItemParent($groupId, $itemId, Request $request)
    {
        // Log the incoming request and parameters
        \Log::info('Updating ProductGroupItem Parent', [
            'group_id' => $groupId,
            'item_id' => $itemId,
            'new_group_id' => $request->new_group_id
        ]);
    
        // Validate incoming request data
        $request->validate([
            'new_group_id' => 'required|integer|exists:product_groups,id',
        ]);
    
        // Find the product group item
        $item = ProductGroupItem::where('group_id', $groupId) // Use group_id
                                ->where('product_id', $itemId)
                                ->first();
    
        // Log the result to see if it's found
        \Log::info('Found ProductGroupItem:', ['item' => $item]);
    
        if (!$item) {
            return response()->json(['message' => 'Product Group Item not found'], 404);
        }
    
        // Find the new group
        $newGroup = ProductGroup::find($request->new_group_id);
    
        if (!$newGroup) {
            return response()->json(['message' => 'New Product Group not found'], 404);
        }
    
        // Update the parent group of the item
        $item->group_id = $newGroup->id;
        $item->save();
    
        return response()->json([
            'message' => 'Parent updated successfully.',
            'new_parent_name' => $newGroup->name,
        ]);
    }
    

}
