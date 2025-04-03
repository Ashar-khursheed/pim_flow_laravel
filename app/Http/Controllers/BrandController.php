<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;

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
     *         @OA\JsonContent(
     *             required={"name", "status", "order", "is_featured"},
     *             @OA\Property(property="name", type="string", example="Nike"),
     *             @OA\Property(property="description", type="string", example="A global sports brand"),
     *             @OA\Property(property="website", type="string", example="https://nike.com"),
     *             @OA\Property(property="logo", type="string", format="binary"),
     *             @OA\Property(property="status", type="string", example="published"),
     *             @OA\Property(property="order", type="integer", example=1),
     *             @OA\Property(property="is_featured", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Brand created successfully"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'website' => 'nullable|url|max:191',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|string|in:published,draft',
            'order' => 'required|integer|min:0',
            'is_featured' => 'required|boolean',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $brand = Brand::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully',
            'brand' => $brand
        ], 201);
    }

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
     * @OA\Put(
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
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Nike Updated"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="website", type="string", example="https://nike.com"),
     *             @OA\Property(property="logo", type="string", format="binary"),
     *             @OA\Property(property="status", type="string", example="published"),
     *             @OA\Property(property="order", type="integer", example=2),
     *             @OA\Property(property="is_featured", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Brand updated successfully"),
     *     @OA\Response(response=404, description="Brand not found"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request, $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'description' => 'nullable|string',
            'website' => 'nullable|url|max:191',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'sometimes|required|string|in:published,draft',
            'order' => 'sometimes|required|integer|min:0',
            'is_featured' => 'sometimes|required|boolean',
        ]);

        if ($request->hasFile('logo')) {
            if ($brand->logo) {
                Storage::disk('public')->delete($brand->logo);
            }
            $validated['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $brand->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully',
            'brand' => $brand
        ]);
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
                    $query->select('id', 'brand_id', 'store_id')->with('categories:id'); 
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
            // Collect category IDs and store IDs by iterating over products
            $categoryIds = $brand->products->flatMap(function ($product) {
                return $product->categories->pluck('id');
            })->unique()->values();
    
            $storeIds = $brand->products->pluck('store_id')->unique()->values();
    
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'logo' => $brand->logo,
                'slug' => $brand->website,
                'is_featured' => $brand->is_featured,
                'description' => $brand->description,
                'status' => $brand->status,
                'products_count' => $brand->products_count,
                'category_ids' => $categoryIds,
                'store_ids' => $storeIds,
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
