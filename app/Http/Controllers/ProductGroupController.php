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
        \Log::info('Removing parent from ProductGroupItem', [
            'group_id' => $groupId,
            'item_id' => $itemId
        ]);

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
     *     path="/api/brands/{brand_id}/categories",
     *     summary="Get Brand Categories",
     *     description="Retrieves brand with primary and secondary categories and product family information.",
     *     tags={"Product Groups"},
     *     @OA\Parameter(
     *         name="brand_id",
     *         in="path",
     *         required=true,
     *         description="ID of the brand",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand with category details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="brand_name", type="string", example="Brand 4"),
     *             @OA\Property(property="primary_category", type="string", example="Category 4"),
     *             @OA\Property(property="secondary_category", type="string", example="Second Category 4"),
     *             @OA\Property(property="product_family", type="string", example="product_family 4"),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-21"),
     *             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-20")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Brand not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getBrandCategories($brandId)
    {
        // Find the brand or return a 404 response
        $brand = Brand::findOrFail($brandId);
        
        // Get all product IDs for this brand
        $productIds = $brand->products()->pluck('id')->toArray();
        
        if (empty($productIds)) {
            return response()->json([
                'message' => 'No products found for this brand.',
            ], 404);
        }
        
        // Get all category IDs associated with these products through the pivot table
        $categoryIds = \DB::table('ec_product_category_product')
            ->whereIn('product_id', $productIds)
            ->pluck('category_id')
            ->unique()
            ->toArray();
        
        if (empty($categoryIds)) {
            return response()->json([
                'message' => 'No categories linked to products of this brand.',
            ], 404);
        }
        
        // Get all categories and build a hierarchy for analysis
        $allCategories = Category::whereIn('id', $categoryIds)
            ->orWhereHas('childrenRecursive', function($query) use ($categoryIds) {
                $query->whereIn('id', $categoryIds);
            })
            ->with('childrenRecursive')
            ->get();
        
        // Structure to track primary, secondary and product family categories
        $categoryAnalysis = [
            'primary' => null,
            'secondary' => null,
            'product_family' => null,
        ];
        
        // Identify root categories (those with no parent or parent_id = 0)
        $rootCategories = $allCategories->filter(function($category) {
            return $category->parent_id === null || $category->parent_id === 0;
        });
        
        // If we have root categories, set the most common one as primary
        if ($rootCategories->isNotEmpty()) {
            // Count how many products are related to each root category
            $rootCategoryCounts = collect();
            
            foreach ($rootCategories as $rootCategory) {
                // Get all descendant category IDs (including the root itself)
                $descendantIds = $this->getAllDescendantIds($rootCategory, $allCategories);
                
                // Count products linked to these categories
                $count = \DB::table('ec_product_category_product')
                    ->whereIn('category_id', $descendantIds)
                    ->whereIn('product_id', $productIds)
                    ->distinct('product_id')
                    ->count('product_id');
                
                $rootCategoryCounts->push([
                    'category' => $rootCategory,
                    'count' => $count
                ]);
            }
            
            // Sort by count descending and get the most common one
            $sortedRoots = $rootCategoryCounts->sortByDesc('count');
            
            if ($sortedRoots->isNotEmpty()) {
                $primaryCategory = $sortedRoots->first()['category'];
                $categoryAnalysis['primary'] = $primaryCategory->name;
                
                // Now find the most common secondary category (child of primary)
                $secondaryCategories = $allCategories->filter(function($category) use ($primaryCategory) {
                    return $category->parent_id == $primaryCategory->id;
                });
                
                if ($secondaryCategories->isNotEmpty()) {
                    // Similar process to find most common secondary category
                    $secondaryCategoryCounts = collect();
                    
                    foreach ($secondaryCategories as $secondaryCategory) {
                        $descendantIds = $this->getAllDescendantIds($secondaryCategory, $allCategories);
                        
                        $count = \DB::table('ec_product_category_product')
                            ->whereIn('category_id', $descendantIds)
                            ->whereIn('product_id', $productIds)
                            ->distinct('product_id')
                            ->count('product_id');
                        
                        $secondaryCategoryCounts->push([
                            'category' => $secondaryCategory,
                            'count' => $count
                        ]);
                    }
                    
                    $sortedSecondary = $secondaryCategoryCounts->sortByDesc('count');
                    
                    if ($sortedSecondary->isNotEmpty()) {
                        $secondaryCategory = $sortedSecondary->first()['category'];
                        $categoryAnalysis['secondary'] = $secondaryCategory->name;
                        
                        // Finally, find product family (child of secondary)
                        $productFamilyCategories = $allCategories->filter(function($category) use ($secondaryCategory) {
                            return $category->parent_id == $secondaryCategory->id;
                        });
                        
                        if ($productFamilyCategories->isNotEmpty()) {
                            // Similar process for product family
                            $familyCategoryCounts = collect();
                            
                            foreach ($productFamilyCategories as $familyCategory) {
                                $descendantIds = $this->getAllDescendantIds($familyCategory, $allCategories);
                                
                                $count = \DB::table('ec_product_category_product')
                                    ->whereIn('category_id', $descendantIds)
                                    ->whereIn('product_id', $productIds)
                                    ->distinct('product_id')
                                    ->count('product_id');
                                
                                $familyCategoryCounts->push([
                                    'category' => $familyCategory,
                                    'count' => $count
                                ]);
                            }
                            
                            $sortedFamily = $familyCategoryCounts->sortByDesc('count');
                            
                            if ($sortedFamily->isNotEmpty()) {
                                $categoryAnalysis['product_family'] = $sortedFamily->first()['category']->name;
                            }
                        }
                    }
                }
            }
        }
        
        // If we still don't have a product family, try to find the most common leaf category
        if (!$categoryAnalysis['product_family']) {
            // Get all leaf categories (those that don't have children within our set)
            $leafCategories = $allCategories->filter(function($category) use ($allCategories) {
                return !$allCategories->contains('parent_id', $category->id);
            });
            
            if ($leafCategories->isNotEmpty()) {
                // Count products per leaf category
                $leafCategoryCounts = collect();
                
                foreach ($leafCategories as $leafCategory) {
                    $count = \DB::table('ec_product_category_product')
                        ->where('category_id', $leafCategory->id)
                        ->whereIn('product_id', $productIds)
                        ->count();
                    
                    $leafCategoryCounts->push([
                        'category' => $leafCategory,
                        'count' => $count
                    ]);
                }
                
                $sortedLeaves = $leafCategoryCounts->sortByDesc('count');
                
                if ($sortedLeaves->isNotEmpty()) {
                    $categoryAnalysis['product_family'] = $sortedLeaves->first()['category']->name;
                }
            }
        }
        
        // Format the response according to the requested structure
        $response = [
            'id' => (int) $brandId,
            'brand_name' => $brand->name,
            'primary_category' => $categoryAnalysis['primary'],
            'secondary_category' => $categoryAnalysis['secondary'],
            'product_family' => $categoryAnalysis['product_family'],
            'created_at' => $brand->created_at->format('Y-m-d'),
            'updated_at' => $brand->updated_at->format('Y-m-d')
        ];
        
        return response()->json($response);
    }

    /**
     * Helper method to get all descendant IDs including the given category itself
     * 
     * @param Category $category
     * @param Collection $allCategories
     * @return array
     */
    private function getAllDescendantIds($category, $allCategories)
    {
        $ids = [$category->id];
        
        $children = $allCategories->filter(function($cat) use ($category) {
            return $cat->parent_id == $category->id;
        });
        
        foreach ($children as $child) {
            $ids = array_merge($ids, $this->getAllDescendantIds($child, $allCategories));
        }
        
        return $ids;
    }
            
     

 /**
 * @OA\Get(
 *     path="/api/brands/categories",
 *     summary="Get Categories for All Brands",
 *     description="Returns a list of all brands with their hierarchical categories (primary, secondary, product family, and additional child categories).",
 *     tags={"Product Groups"},
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Page number",
 *         required=false,
 *         @OA\Schema(type="integer", default=1)
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Number of items per page",
 *         required=false,
 *         @OA\Schema(type="integer", default=10)
 *     ),
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Search term for brand name or category name",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="sort_by",
 *         in="query",
 *         description="Field to sort by (brand_name, created_at)",
 *         required=false,
 *         @OA\Schema(type="string", default="brand_name")
 *     ),
 *     @OA\Parameter(
 *         name="sort_order",
 *         in="query",
 *         description="Sort order (asc, desc)",
 *         required=false,
 *         @OA\Schema(type="string", default="asc")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="List of all brands with their categorized hierarchy",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="data", type="array", 
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", example=14),
 *                     @OA\Property(property="brand_name", type="string", example="Atosa"),
 *                     @OA\Property(property="primary_category", type="string", example="Refrigeration"),
 *                     @OA\Property(property="secondary_category", type="string", example="Commercial Refrigerator"),
 *                     @OA\Property(property="product_family", type="string", example="Back Bar Refrigerator"),
 *                     @OA\Property(property="additional_categories", type="array", 
 *                        @OA\Items(
 *                            type="object",
 *                            @OA\Property(property="level", type="integer", example=3),
 *                            @OA\Property(property="name", type="string", example="Double Door")
 *                        ),
 *                        description="Additional category levels beyond the standard three"
 *                     ),
 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-22"),
 *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-02-11")
 *                 )
 *             ),
 *             @OA\Property(property="first_page_url", type="string"),
 *             @OA\Property(property="from", type="integer"),
 *             @OA\Property(property="last_page", type="integer"),
 *             @OA\Property(property="last_page_url", type="string"),
 *             @OA\Property(property="links", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="next_page_url", type="string", nullable=true),
 *             @OA\Property(property="path", type="string"),
 *             @OA\Property(property="per_page", type="integer"),
 *             @OA\Property(property="prev_page_url", type="string", nullable=true),
 *             @OA\Property(property="to", type="integer"),
 *             @OA\Property(property="total", type="integer")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized"
 *     ),
 *     security={{"bearerAuth":{}}}
 * )
 */
public function getAllBrandsWithCategories(Request $request)
{
    // Get and validate request parameters
    $perPage = (int)$request->input('per_page', 10);
    $search = $request->input('search');
    $sortBy = $request->input('sort_by', 'brand_name');
    $sortOrder = strtolower($request->input('sort_order', 'asc'));
    
    // Map API parameter names to database column names
    $sortFieldMap = [
        'brand_name' => 'name',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at'
    ];
    
    // Validate and set default sort parameters
    if (!array_key_exists($sortBy, $sortFieldMap)) {
        $sortBy = 'brand_name';
    }
    
    if (!in_array($sortOrder, ['asc', 'desc'])) {
        $sortOrder = 'asc';
    }
    
    // Use mapped database field for sorting
    $dbSortField = $sortFieldMap[$sortBy];
    
    // Optimize query: eager load products with their categories
    // Use query builder for better control over the query
    $query = Brand::query()
        ->with(['products' => function($query) {
            $query->with('categories');
        }]);
    
    // Apply search if provided
    if ($search) {
        $query->where(function ($q) use ($search) {
            // Search brand name
            $q->where('name', 'like', "%{$search}%")
              // Search category names connected to brand products
              ->orWhereHas('products.categories', function ($subq) use ($search) {
                  $subq->where('name', 'like', "%{$search}%");
              });
        });
    }
    
    // Apply sorting
    $query->orderBy($dbSortField, $sortOrder);
    
    // Execute paginated query
    $brands = $query->paginate($perPage);
    
    // Load all categories at once to reduce database access
    $allCategories = Category::all()->keyBy('id');
    
    // Transform data to match API specification
    $transformedData = $brands->map(function ($brand) use ($allCategories) {
        // Process all product categories for this brand
        $categoryData = $this->processBrandCategories($brand, $allCategories);
        
        return [
            'id' => $brand->id,
            'brand_name' => $brand->name,
            'primary_category' => $categoryData['primary'] ?? null,
            'secondary_category' => $categoryData['secondary'] ?? null,
            'product_family' => $categoryData['family'] ?? null,
            'additional_categories' => $categoryData['additional'] ?? [],
            'created_at' => $brand->created_at->format('Y-m-d'),
            'updated_at' => $brand->updated_at->format('Y-m-d')
        ];
    });
    
    // Return paginated response with the correct format
    return response()->json($brands->setCollection($transformedData));
}

/**
 * Process categories for a brand and organize them into hierarchical levels
 * 
 * @param Brand $brand
 * @param Collection $allCategories
 * @return array
 */
private function processBrandCategories($brand, $allCategories)
{
    // Initialize result structure
    $result = [
        'primary' => null,
        'secondary' => null,
        'family' => null,
        'additional' => []
    ];
    
    // Set for deduplication
    $primarySet = [];
    $secondarySet = [];
    $familySet = [];
    $additionalSet = [];
    
    // Process each product's categories
    foreach ($brand->products as $product) {
        foreach ($product->categories as $category) {
            // Build the full category path from root to current node
            $path = $this->getCategoryPath($category, $allCategories);
            
            // Assign categories to appropriate levels based on their position in hierarchy
            $pathCount = count($path);
            
            if ($pathCount > 0 && !in_array($path[0], $primarySet)) {
                $primarySet[] = $path[0];
                if (empty($result['primary'])) {
                    $result['primary'] = $path[0];
                }
            }
            
            if ($pathCount > 1 && !in_array($path[1], $secondarySet)) {
                $secondarySet[] = $path[1];
                if (empty($result['secondary'])) {
                    $result['secondary'] = $path[1];
                }
            }
            
            if ($pathCount > 2 && !in_array($path[2], $familySet)) {
                $familySet[] = $path[2];
                if (empty($result['family'])) {
                    $result['family'] = $path[2];
                }
            }
            
            // Add any deeper levels to additional categories
            for ($i = 3; $i < $pathCount; $i++) {
                $additionalKey = $i . ':' . $path[$i];
                if (!in_array($additionalKey, $additionalSet)) {
                    $additionalSet[] = $additionalKey;
                    $result['additional'][] = [
                        'level' => $i,
                        'name' => $path[$i]
                    ];
                }
            }
        }
    }
    
    return $result;
}

/**
 * Get the full path from root to the given category
 * Optimized to use pre-loaded category data
 *
 * @param Category $category
 * @param Collection $allCategories
 * @return array
 */
private function getCategoryPath($category, $allCategories)
{
    // Build path using the category map
    $path = [$category->name];
    $current = $category;
    
    // Use a safety counter to prevent infinite loops
    $safetyCounter = 0;
    $maxDepth = 10; // Reasonable limit for category depth
    
    while ($current->parent_id && isset($allCategories[$current->parent_id]) && $safetyCounter < $maxDepth) {
        $current = $allCategories[$current->parent_id];
        array_unshift($path, $current->name);
        $safetyCounter++;
    }
    
    return $path;
}

  // public function getAllBrandsWithCategories(Request $request)
    // {
    //     // Parse request parameters
    //     $perPage = $request->input('per_page', 10);
    //     $search = $request->input('search');
    //     $sortBy = $request->input('sort_by', 'brand_name');
    //     $sortOrder = $request->input('sort_order', 'asc');
        
    //     // Validate sort parameters
    //     $allowedSortFields = ['brand_name', 'created_at', 'updated_at'];
    //     if (!in_array($sortBy, $allowedSortFields)) {
    //         $sortBy = 'brand_name';
    //     }
        
    //     $allowedSortOrders = ['asc', 'desc'];
    //     if (!in_array($sortOrder, $allowedSortOrders)) {
    //         $sortOrder = 'asc';
    //     }
        
    //     // Start building the query
    //     $brandsQuery = Brand::query();
        
    //     // Apply search if provided
    //     if ($search) {
    //         $brandsQuery->where('name', 'like', "%{$search}%");
            
    //         // Also include brands that have products in categories matching the search
    //         $brandsQuery->orWhereHas('products.categories', function ($query) use ($search) {
    //             $query->where('name', 'like', "%{$search}%");
    //         });
    //     }
        
    //     // Apply sorting
    //     if ($sortBy === 'brand_name') {
    //         $brandsQuery->orderBy('name', $sortOrder);
    //     } else {
    //         $brandsQuery->orderBy($sortBy, $sortOrder);
    //     }
        
    //     // Execute the paginated query (do this **before** transforming data)
    //     $brands = $brandsQuery->paginate($perPage);
        
    //     // Transform the paginated results
    //     $transformedData = $brands->getCollection()->flatMap(function ($brand) {
    //         // Get all product IDs for this brand
    //         $productIds = $brand->products()->pluck('id')->toArray();
            
    //         // Initialize array to hold results
    //         $result = [];
            
    //         if (!empty($productIds)) {
    //             // Get all categories associated with these products through the pivot table
    //             $categories = \DB::table('ec_product_category_product')
    //                 ->whereIn('product_id', $productIds)
    //                 ->pluck('category_id')
    //                 ->unique()
    //                 ->toArray();
                
    //             if (!empty($categories)) {
    //                 // Get all categories and organize them by level
    //                 $categoryData = $this->getCategoriesByLevel($categories);
                    
    //                 // Get primary, secondary, product family, and additional categories
    //                 $primaryCategories = $this->getAllUniqueCategoriesAtLevel($categoryData, 0);
    //                 $secondaryCategories = $this->getAllUniqueCategoriesAtLevel($categoryData, 1);
    //                 $productFamilies = $this->getAllUniqueCategoriesAtLevel($categoryData, 2);
                    
    //                 // Add combinations of brand, category, and product family
    //                 foreach ($primaryCategories as $primaryCategory) {
    //                     foreach ($secondaryCategories as $secondaryCategory) {
    //                         foreach ($productFamilies as $productFamily) {
    //                             $result[] = [
    //                                 'id' => $brand->id,
    //                                 'brand_name' => $brand->name,
    //                                 'primary_category' => $primaryCategory,
    //                                 'secondary_category' => $secondaryCategory,
    //                                 'product_family' => $productFamily,
    //                             ];
    //                         }
    //                     }
    //                 }
    //             }
    //         }
            
    //         return $result;
    //     });
        
    //     // Set the transformed data collection back to the paginator while keeping pagination metadata
    //     $brands->setCollection($transformedData);
        
    //     return response()->json($brands);
    // }

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
