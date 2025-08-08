<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\ProductGroup;
use App\Models\ProductGroupItem;
use Illuminate\Support\Facades\Log;
use App\Models\Category;
use App\Models\Product;
use App\Models\Brand;
use App\Http\Controllers\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductGroupController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/product-groups-listing",
     *     summary="Get Product Groups",
     *     description="Fetches a list of product groups with ID and name.",
     *     tags={"Product Groups"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with list of product groups",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Mixers")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */


     public function index()
    {
        $groups = ProductGroup::select('id', 'name')->get();

        return response()->json([
            'status' => true,
            'data' => $groups,
        ]);
    }

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
    *         description="Successfully fetched product groups",
    *         @OA\JsonContent(
    *             type="array",
    *             @OA\Items(
    *                 type="object",
    *                 @OA\Property(property="id", type="integer", example=1, description="The ID of the product group"),
    *                 @OA\Property(property="name", type="string", example="Group 1", description="The name of the product group")
    *             ),
    *             example={"id": 1, "name": "Group 1"}
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
        $productGroupMap = [];

        try {
            foreach ($data as $groupName => $products) {
                $group = ProductGroup::create(['name' => $groupName]);

                foreach ($products as $product) {
                    ProductGroupItem::create([
                        'group_id' => $group->id,
                        'product_id' => $product['id']
                    ]);

                    // Map product ID to group ID
                    $productGroupMap[$product['id']] = $group->id;
                }
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error saving groups: ' . $e->getMessage()], 500);
        }

        // Return original structure plus group mapping
        return response()->json([
            'message' => 'Groups saved successfully',
            'data' => $data,
            'product_group_map' => $productGroupMap
        ]);

    }



       /**
     * @OA\Get(
     *     path="/api/product-groups",
     *     summary="Get Grouped Product List",
     *     description="Fetches a list of product groups with their related products, including brand, image, categories, and taxonomy. Optionally filter by category.",
     *     tags={"Product Groups"},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter products by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
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

    public function getGroupedProductDetails(Request $request)
{
    // Get the category_id from the query string
    $categoryId = $request->query('category_id');

    // If no category ID provided, return error response
    if (!$categoryId) {
        return response()->json([
            'error' => 'Category ID is required for filtering',
            'status' => false
        ], 400);
    }

    // First, find product IDs that belong to the requested category
    $productIdsInCategory = DB::table('product_categories')
        ->where('category_id', $categoryId)
        ->pluck('product_id')
        ->toArray();

    if (empty($productIdsInCategory)) {
        return response()->json([
            'message' => 'No products found in this category',
            'status' => true,
            'data' => []
        ]);
    }

    // Now get the groups that contain these products
    $groups = ProductGroup::with(['items' => function ($query) use ($productIdsInCategory) {
        $query->whereIn('product_id', $productIdsInCategory);
    }, 'items.product' => function ($query) {
        $query->with(['brand', 'categories', 'slug']);
    }])
    ->whereHas('items', function ($query) use ($productIdsInCategory) {
        $query->whereIn('product_id', $productIdsInCategory);
    })
    ->get();

    $result = [];

    foreach ($groups as $group) {
        $products = [];

        foreach ($group->items as $item) {
            $product = $item->product;

            if (!$product) continue;

            // Verify this product is in the requested category (double-check)
            $inCategory = $product->categories->contains(function ($category) use ($categoryId) {
                return $category->id == $categoryId;
            });

            if (!$inCategory) continue;

            $products[] = [
                'id' => $product->id,
                'group_id' => $group->id, // <-- Added this line
                'name' => $product->name,
                'sku' => $product->sku,
                'image' => $product->image ?: null,
                'brand' => optional($product->brand)->name,
                'store' => null,
                'status' => $product->status,
                'product_family' => $product->categories->pluck('name')->unique()->values()->all(),
                'taxonomy_path' => optional($product->slug)->key,
            ];
        }

        // Only include groups that have products in this category
        if (count($products) > 0) {
            $result[$group->name] = $products;
        }
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
   
        // Validate incoming request data
        $request->validate([
            'new_group_id' => 'required|integer|exists:product_groups,id',
        ]);

        // Find the product group item
        $item = ProductGroupItem::where('group_id', $groupId) // Use group_id
                                ->where('product_id', $itemId)
                                ->first();

      

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

    /**
     * @OA\Delete(
     *     path="/api/product-groups/{group_id}/items/{item_id}/parent",
     *     summary="Remove Product Group Item from its Parent",
     *     description="Removes the association between a product group item and its parent group, effectively unparenting the item.",
     *     tags={"Product Groups"},
     *     @OA\Parameter(
     *         name="group_id",
     *         in="path",
     *         required=true,
     *         description="The ID of the parent product group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="item_id",
     *         in="path",
     *         required=true,
     *         description="The ID of the product group item to unparent.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Child successfully removed from parent",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Child removed from parent successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product Group Item not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */

     public function removeProductGroupItemParent($groupId, $itemId)
    {
 

        // Find the product group item
        $item = ProductGroupItem::where('group_id', $groupId)
                                ->where('product_id', $itemId)
                                ->first();

        if (!$item) {
            return response()->json(['message' => 'Product Group Item not found'], 404);
        }

        // Remove the parent relationship (set group_id to null or other logic)
        $item->group_id = null;
        $item->save();

        return response()->json([
            'message' => 'Child removed from parent successfully.'
        ]);
    }

           /**
     * @OA\Get(
     *     path="/api/product-groups/brands-with-categories",
     *     summary="Get Brands with Leaf Categories",
     *     description="Fetches a paginated, searchable, and sortable flat list of brands and their associated leaf categories. Includes created_at and updated_at timestamps for brands.",
     *     tags={"Product Groups"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination. Starts from 1.",
     *         required=true,
     *         example=1,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of records per page.",
     *         required=true,
     *         example=10,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search brands or categories by name.",
     *         required=false,
     *         example="Nike",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by: brand_name, category_name, brand_created_at, brand_updated_at.",
     *         required=false,
     *         example="brand_name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order: asc or desc.",
     *         required=false,
     *         example="asc",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand List with Categories"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="brand_id", type="integer", example=13),
     *                     @OA\Property(property="brand_name", type="string", example="Nike Updated"),
     *                     @OA\Property(property="category_id", type="integer", example=47),
     *                     @OA\Property(property="category_name", type="string", example="Chef Base Refrigerators"),
     *                     @OA\Property(property="brand_created_at", type="string", format="date-time", example="2024-10-16 08:31:24"),
     *                     @OA\Property(property="brand_updated_at", type="string", format="date-time", example="2025-04-05 07:04:29")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="last_page", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getBrandsWithCategories(Request $request)
    {
        $page = max((int) $request->input('page', 1), 1);
        $perPage = max((int) $request->input('per_page', 10), 1);
        $search = $request->input('search', '');

        $sortBy = $request->input('sort_by', 'brand_name');
        $sortOrder = strtolower($request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Allowed fields for sorting mapped to actual columns
        $sortFieldsMap = [
            'brand_name' => 'b.name',
            'category_name' => 'c.name',
            'brand_created_at' => 'b.created_at',
            'brand_updated_at' => 'b.updated_at',
        ];

        $sortColumn = $sortFieldsMap[$sortBy] ?? 'b.name';

        $query = DB::table('ec_products as p')
            ->join('ec_brands as b', 'p.brand_id', '=', 'b.id')
            ->join('product_categories as pcp', 'p.id', '=', 'pcp.product_id')
            ->join('categories as c', 'pcp.category_id', '=', 'c.id')
            ->leftJoin('categories as sub', 'c.id', '=', 'sub.parent_id')
            ->whereNull('sub.id') // Only leaf categories
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('b.name', 'LIKE', '%' . $search . '%')
                        ->orWhere('c.name', 'LIKE', '%' . $search . '%');
                });
            })
            ->select([
                'b.id as brand_id',
                'b.name as brand_name',
                'b.created_at as brand_created_at',
                'b.updated_at as brand_updated_at',
                'c.id as category_id',
                'c.name as category_name'
            ])
            ->distinct();

        $total = $query->count();

        $results = $query
            ->orderBy($sortColumn, $sortOrder)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Brand List with Categories',
            'data' => $results,
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }







    /**
     * @OA\Schema(
     *     schema="BrandCategoryResponse",
     *     type="object",
     *     @OA\Property(property="id", type="integer", example=14),
     *     @OA\Property(property="brand_name", type="string", example="Atosa"),
     *     @OA\Property(property="primary_category", type="string", example="Refrigeration"),
     *     @OA\Property(property="secondary_category", type="string", example="Commercial Refrigerator"),
     *     @OA\Property(property="product_family", type="string", example="Back Bar Refrigerator"),
     *     @OA\Property(
     *         property="additional_categories",
     *         type="array",
     *         @OA\Items(
     *             type="object",
     *             @OA\Property(property="level", type="integer", example=3),
     *             @OA\Property(property="name", type="string", example="Double Door")
     *         )
     *     ),
     *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-22"),
     *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-02-11")
     * )
     */



    }
