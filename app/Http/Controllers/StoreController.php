<?php

namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Category;
class StoreController extends BaseController
{
	/**
	 * Display a listing of the resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/stores",
	 *     summary="Get stores List",
	 *     description="Fetches a list of all stores.",
	 *     tags={"Stores"},
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
		$stores = Store::query();

		if($request->filled('page') && $request->filled('length')){
			$page = $request->input('page');
			$length = $request->input('length');
			$stores = $stores->offset(($page - 1)*$length)->limit($length);
		}

		$stores = $stores->pluck('name', 'id');

		return response()->json([
			'message' => 'Store List',
			'stores' => $stores
		]);
	}

    /**
     * @OA\Get(
     *     path="/api/stores/list",
     *     summary="Get stores id and name list",
     *     description="Fetches a list of all stores with only id and name.",
     *     tags={"Stores"},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="stores",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Store Name")
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function storeList()
    {
        $stores = Store::select('id', 'name')->get();

        return response()->json([
            'message' => 'Store ID and Name List',
            'stores' => $stores
        ]);
    }


	
    /**
     * @OA\Post(
     *     path="/api/stores",
     *     summary="Create a new store",
     *     tags={"Stores"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="New Store"),
     *             @OA\Property(property="description", type="string", example="Store Description"),
     *             @OA\Property(property="website", type="string", example="https://store.com"),
     *             @OA\Property(property="status", type="string", example="active")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Store Created"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'status' => 'nullable|string|max:60'
        ]);

        $store = Store::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Store Created',
            'store' => $store
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/stores/{id}",
     *     summary="Get store details",
     *     tags={"Stores"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Store Details"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id): JsonResponse
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json(['success' => false, 'message' => 'Store Not Found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Store Details',
            'store' => $store
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/stores/{id}",
     *     summary="Update an existing store",
     *     tags={"Stores"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Store"),
     *             @OA\Property(property="description", type="string", example="Updated Description"),
     *             @OA\Property(property="website", type="string", example="https://updatedstore.com"),
     *             @OA\Property(property="status", type="string", example="inactive")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Store Updated"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json(['success' => false, 'message' => 'Store Not Found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'status' => 'nullable|string|max:60'
        ]);

        $store->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Store Updated',
            'store' => $store
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/stores/{id}",
     *     summary="Delete a store",
     *     tags={"Stores"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Store Deleted"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id): JsonResponse
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json(['success' => false, 'message' => 'Store Not Found'], 404);
        }

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store Deleted'
        ]);
    }


    /**
 * @OA\Get(
 *     path="/api/getStoresList",
 *     summary="Fetch all stores with pagination, search, filter, and sorting",
 *     tags={"Stores"},
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Search by store name",
 *         @OA\Schema(type="string", example="Walmart")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Filter by store status",
 *         @OA\Schema(type="string", example="open")
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
 *         description="Number of stores per page",
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
 *             @OA\Property(property="stores", type="object")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid parameters"),
 *     security={{"bearerAuth":{}}}
 * )
 */
public function getStoresList(Request $request): JsonResponse
{
    $query = Store::select('id', 'name', 'email', 'phone', 'address', 'country', 'state', 'city', 'logo', 'cover_image', 'description', 'content', 'status', 'created_at', 'updated_at', 'zip_code', 'company')
        ->withCount('products') // Count the number of products associated with the store
        ->with([
            'products' => function ($query) {
                // Load product categories with IDs
                $query->select('id', 'store_id')
                      ->with('categories:id'); // Load only category IDs
            }
        ]);

    // Search by store name
    if ($request->has('search')) {
        $query->where('name', 'LIKE', '%' . $request->search . '%');
    }

    // Filter by status
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    // Sorting
    $sortBy = $request->get('sort_by', 'created_at'); // Default: created_at
    $sortOrder = $request->get('sort_order', 'desc'); // Default: desc (latest first)
    $query->orderBy($sortBy, $sortOrder);

    // Pagination
    $perPage = $request->get('per_page', 10);
    $stores = $query->paginate($perPage);

    // Transform data (after loading all necessary relations)
    $stores->getCollection()->transform(function ($store) {
        // Collect category IDs from the products
        $categoryIds = $store->products->flatMap(function ($product) {
            return $product->categories->pluck('id'); // Collect the category IDs
        })->unique()->values(); // Ensure unique category IDs

        // Now, map category IDs to their names
        $categoryNames = Category::whereIn('id', $categoryIds)->pluck('name', 'id')->toArray();
        $mappedCategoryNames = array_values(array_intersect_key($categoryNames, array_flip($categoryIds->toArray())));

        return [
            'id' => $store->id,
            'name' => $store->name,
            'email' => $store->email,
            'phone' => $store->phone,
            'address' => $store->address,
            'country' => $store->country,
            'state' => $store->state,
            'city' => $store->city,
            'store_logo' => $store->logo, // Assuming this is the field for store logo
            'cover_image' => $store->cover_image,
            'description' => $store->description,
            'content' => $store->content,
            'status' => $store->status,
            'products_count' => $store->products_count, // Number of products for the store
            'category_names' => $mappedCategoryNames, // Category names mapped from category IDs
            'created_at' => $store->created_at,
            'updated_at' => $store->updated_at,
        ];
    });

    return response()->json([
        'success' => true,
        'stores' => $stores
    ]);
}



}
