<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Support\Facades\Storage;

class BrandController extends BaseController
{
	/**
	 * Display a listing of the resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/brands",
	 *     summary="Get brands List",
	 *     description="Fetches a list of all brands.",
	 *     tags={"Brands"},
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination. Starts from 1.",
	 *         required=true,
	 *         example=1,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="length",
	 *         in="query",
	 *         description="Number of records per page.",
	 *         required=true,
	 *         example=20,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$brands = Brand::query();

		if($request->filled('page') && $request->filled('length')){
			$page = $request->input('page');
			$length = $request->input('length');
			$brands = $brands->offset(($page - 1)*$length)->limit($length);
		}

		$brands = $brands->pluck('name', 'id');

		return response()->json([
			'success' => true,
			'message' => 'Brand List',
			'brands' => $brands
		]);
	}
   
/**
 * @OA\Post(
 *     path="/api/brands",
 *     summary="Create a new brand",
 *     tags={"Brands"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"name", "status", "order", "is_featured"},
 *                 @OA\Property(property="name", type="string", example="Nike"),
 *                 @OA\Property(property="description", type="string", example="A global sports brand"),
 *                 @OA\Property(property="website", type="string", example="https://nike.com"),
 *                 @OA\Property(
 *                     property="logo", 
 *                     type="string", 
 *                     format="binary",
 *                     description="Logo file upload"
 *                 ),
 *                 @OA\Property(property="status", type="string", enum={"published", "draft"}, example="published"),
 *                 @OA\Property(property="order", type="integer", example=1),
 *                 @OA\Property(property="is_featured", type="integer", enum={0, 1}, example=1, description="Use 1 for true, 0 for false")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Brand created successfully"),
 *     @OA\Response(response=422, description="Validation error"),
 *     security={{"bearerAuth":{}}}
 * )
 */

 public function store(Request $request) 
 {
     try {
         // Update validation rules for is_featured to accept 0 or 1
         $validated = $request->validate([
             'name' => 'required|string|max:191',
             'description' => 'nullable|string',
             'website' => 'nullable|url|max:191',
             'status' => 'required|string|in:published,draft',
             'order' => 'required|integer|min:0',
             'is_featured' => 'required|boolean',  // Boolean validation will convert "0", "1", 0, 1, true, false
             'logo' => 'nullable|file|image|mimes:webp,jpeg,png,jpg,gif|max:2048',
         ]);
 
         // Initialize brand data from validated data
         $brandData = [
             'name' => $validated['name'],
             'description' => $validated['description'] ?? null,
             'website' => $validated['website'] ?? null,
             'status' => $validated['status'],
             'order' => $validated['order'],
             'is_featured' => (bool)$validated['is_featured'],  // Convert to proper boolean
         ];
         
         // Handle logo as file upload only
         if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
             // Import Storage facade
             $storage = app('Illuminate\Support\Facades\Storage');
             
             // Process file upload
             $folderPath = env('STORAGE_ENV', 'default') . "/brands"; // Example: 'production/brands'
             $logoPath = $request->file('logo')->store($folderPath, 's3'); // 's3' disk defined in config/filesystems.php
             $brandData['logo'] = $storage::disk('s3')->url($logoPath);
         }
 
         // Create the brand record in the database
         $brand = Brand::create($brandData);
 
         // Return a response including the brand data with the logo URL
         return response()->json([
             'success' => true,
             'message' => 'Brand created successfully',
             'brand' => $brand->fresh() // Get the fresh brand data, including logo
         ], 201); // Using 201 Created status code as this is a resource creation
     } catch (\Exception $e) {
         // Log the error
         \Log::error('Brand creation error: ' . $e->getMessage());
         
         // Return a proper JSON error response
         return response()->json([
             'success' => false,
             'message' => 'Failed to create brand',
             'error' => $e->getMessage()
         ], 422);
     }
 }
 // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'name' => 'required|string|max:191',
    //         'description' => 'nullable|string',
    //         'website' => 'nullable|url|max:191',
    //         'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    //         'status' => 'required|string|in:published,draft',
    //         'order' => 'required|integer|min:0',
    //         'is_featured' => 'required|boolean',
    //     ]);

    //     if ($request->hasFile('logo')) {
    //         $validated['logo'] = $request->file('logo')->store('brands', 'public');
    //     }

    //     $brand = Brand::create($validated);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Brand created successfully',
    //         'brand' => $brand
    //     ], 201);
    // }
    


    /**
     * @OA\Get(
     *     path="/api/brands/{id}",
     *     summary="Get a brand by ID",
     *     tags={"Brands"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Brand not found"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'brand' => $brand
        ]);
    }

  /**
 * @OA\Post(
 *     path="/api/brands/{id}",
 *     summary="Update an existing brand",
 *     tags={"Brands"},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Brand ID",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Parameter(
 *         name="_method",
 *         in="query",
 *         required=true,
 *         description="HTTP method override",
 *         @OA\Schema(type="string", enum={"PUT"}, example="PUT")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(property="_method", type="string", enum={"PUT"}, example="PUT"),
 *                 @OA\Property(property="name", type="string", example="Nike Updated"),
 *                 @OA\Property(property="description", type="string", example="Updated description"),
 *                 @OA\Property(property="website", type="string", example="https://nike.com"),
 *                 @OA\Property(
 *                     property="logo", 
 *                     type="string", 
 *                     format="binary",
 *                     description="Logo file upload"
 *                 ),
 *                 @OA\Property(property="status", type="string", enum={"published", "draft"}, example="published"),
 *                 @OA\Property(property="order", type="integer", example=2),
 *                 @OA\Property(property="is_featured", type="integer", enum={0, 1}, example=0, description="Use 1 for true, 0 for false")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Brand updated successfully"),
 *     @OA\Response(response=404, description="Brand not found"),
 *     @OA\Response(response=422, description="Validation error"),
 *     security={{"bearerAuth":{}}}
 * )
 */
public function update(Request $request, $id)
{
    try {
        // Find the brand
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        // Validate incoming data
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'description' => 'nullable|string',
            'website' => 'nullable|url|max:191',
            'status' => 'sometimes|required|string|in:published,draft',
            'order' => 'sometimes|required|integer|min:0',
            'is_featured' => 'sometimes|required|boolean',
            'logo' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Prepare data for update
        $updateData = [];
        
        // Add text fields to update data
        foreach(['name', 'description', 'website', 'status', 'order'] as $field) {
            if (isset($validated[$field])) {
                $updateData[$field] = $validated[$field];
            }
        }
        
        // Handle is_featured specifically to ensure correct boolean conversion
        if (isset($validated['is_featured'])) {
            $updateData['is_featured'] = (bool)$validated['is_featured'];
        }
        
        // Handle logo as file upload
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            // Import Storage facade
            $storage = app('Illuminate\Support\Facades\Storage');
            
            // If there's an existing logo stored in S3, attempt to delete it
            if ($brand->logo && strpos($brand->logo, env('AWS_URL')) !== false) {
                // Extract the path from the full URL
                $existingPath = str_replace(env('AWS_URL').'/', '', $brand->logo);
                try {
                    $storage::disk('s3')->delete($existingPath);
                } catch (\Exception $e) {
                    // Log error but continue with the update
                    \Log::warning("Failed to delete old logo: {$e->getMessage()}");
                }
            }
            
            // Process file upload
            $folderPath = env('STORAGE_ENV', 'default') . "/brands"; // Example: 'production/brands'
            $logoPath = $request->file('logo')->store($folderPath, 's3'); // 's3' disk defined in config/filesystems.php
            $updateData['logo'] = $storage::disk('s3')->url($logoPath);
        }

        // Update the brand
        $brand->update($updateData);

        // Return a response with the updated brand
        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully',
            'brand' => $brand->fresh() // Get the fresh brand data
        ], 200);
    } catch (\Exception $e) {
        // Log the error
        \Log::error('Brand update error: ' . $e->getMessage());
        
        // Return a proper JSON error response
        return response()->json([
            'success' => false,
            'message' => 'Failed to update brand',
            'error' => $e->getMessage()
        ], 422);
    }
}

    /**
     * @OA\Delete(
     *     path="/api/brands/{id}",
     *     summary="Delete a brand",
     *     tags={"Brands"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Brand deleted successfully"),
     *     @OA\Response(response=404, description="Brand not found"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        if ($brand->logo) {
            Storage::disk('public')->delete($brand->logo);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully'
        ]);
    }


        /**
     * @OA\Get(
     *    path="/api/getbrandsList",
     *     summary="Fetch all brands with pagination, search, filter, and sorting",
     *     tags={"Brands"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by brand name",
     *         @OA\Schema(type="string", example="Nike")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by brand status",
     *         @OA\Schema(type="string", example="active")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by id, name, or created_at",
     *         @OA\Schema(type="string", enum={"id", "name", "created_at"}, example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order: asc or desc",
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of brands per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Success", 
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="brands", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid parameters"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getBrandsList(Request $request)
    {
        $query = Brand::select('id', 'name', 'logo', 'website', 'is_featured', 'description', 'status', 'created_at', 'updated_at')
            ->withCount('products') // Only count products, don't load full relation
            ->with([
                'products' => function ($query) {
                    $query->select('id', 'brand_id', 'store_id')->with('categories:id,name'); // Make sure category name is included
                }
            ]);
    
        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }
    
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    
        // Sorting (default: latest created_at)
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
    
        if (in_array($sortBy, ['id', 'name', 'created_at']) && in_array($sortOrder, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortOrder);
        }
    
        // Paginate results (default 10 per page)
        $brands = $query->paginate($request->input('per_page', 10));
    
        // Transform data (after loading all necessary relations)
        $brands->getCollection()->transform(function ($brand) {
            // Collect category IDs by iterating over products
            $categoryIds = $brand->products->flatMap(function ($product) {
                return $product->categories->pluck('id');
            })->unique()->values();
    
            // Fetch category names using find method for each category ID
            $categoryNames = $categoryIds->map(function ($categoryId) {
                $category = Category::find($categoryId); // Using find instead of whereIn
                return $category ? $category->name : null; // Return category name or null if not found
            })->filter()->values();
    
            // Collect store IDs and map them to store names
            $storeIds = $brand->products->pluck('store_id')->unique();
            $storeNames = $storeIds->map(function ($storeId) {
                $store = Store::find($storeId);
                return $store ? $store->name : null;
            })->filter()->values();
    
            // Use asset() or url() to get the full URL for the logo
            $logoUrl = $brand->logo ? asset('storage/' . $brand->logo) : null;
    
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'logo' => $logoUrl, // Full URL for the logo
                'slug' => $brand->website,
                'is_featured' => $brand->is_featured,
                'description' => $brand->description,
                'status' => $brand->status,
                'products_count' => $brand->products_count,
                'category_name' => $categoryNames, // Use the fetched category names
                'store_name' => $storeNames,
                'created_at' => $brand->created_at,
                'updated_at' => $brand->updated_at,
            ];
        });
    
        return response()->json([
            'success' => true,
            'brands' => $brands
        ]);
    }
    
    
    
    
    
}
