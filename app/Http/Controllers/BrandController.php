<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Vendor;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use ZipArchive;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/brands",
	 *     summary="Get brands list",
	 *     description="Fetches a paginated list of all brands with optional search and sorting.",
	 *     tags={"Brands"},
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination. Starts from 1.",
	 *         required=false,
	 *         example=1,
	 *         @OA\Schema(type="integer", minimum=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="length",
	 *         in="query",
	 *         description="Number of records per page.",
	 *         required=false,
	 *         example=20,
	 *         @OA\Schema(type="integer", minimum=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search term applied to all fields.",
	 *         required=false,
	 *         example="Nike",
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         description="Field name to sort by (e.g., id, name, created_at, etc.).",
	 *         required=false,
	 *         example="name",
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_order",
	 *         in="query",
	 *         description="Sorting direction: asc or desc.",
	 *         required=false,
	 *         example="asc",
	 *         @OA\Schema(type="string", enum={"asc", "desc"})
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success - Returns the list of brands.",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Brand List"),
	 *             @OA\Property(
	 *                 property="brands",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(property="name", type="string", example="Nike"),
	 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
	 *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-05T12:00:00Z")
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
	public function index(Request $request)
	{
		$brands = Brand::query();
		// Apply search filter (searches across all columns)
		if ($request->filled('search')) {
			$search = $request->input('search');
			$brands = $brands->where(function ($query) use ($search) {
				$columns = Schema::getColumnListing((new Brand)->getTable());
				foreach ($columns as $column) {
					$query->orWhere($column, 'LIKE', "%{$search}%");
				}
			});
		}

		// Sorting
		if ($request->filled('sort_by') && $request->filled('sort_order')) {
			$sortBy = $request->input('sort_by');
			$sortOrder = $request->input('sort_order') == 'desc' ? 'desc' : 'asc';
			if (Schema::hasColumn((new Brand)->getTable(), $sortBy)) {
				$brands = $brands->orderBy($sortBy, $sortOrder);
			}
		}

		// Pagination
		if ($request->filled('page') && $request->filled('length')) {
			$page = $request->input('page');
			$length = $request->input('length');
			$brands = $brands->offset(($page - 1) * $length)->limit($length)->get();
		} else {
			$brands = $brands->orderBy('name', 'asc')->get([
				'id', 'name'
			]);
		}

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
	 *                 @OA\Property(
	 *                     property="thumbnail",
	 *                     type="string",
	 *                     format="binary",
	 *                     description="thumbnail file upload"
	 *                 ),
	 *                 @OA\Property(
	 *                     property="ar_thumbnail",
	 *                     type="string",
	 *                     format="binary",
	 *                     description="ar_thumbnail file upload"
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
		if (!auth()->user()->can('add brand')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		try {
			$validated = $request->validate([
				'name' => 'required|string|max:191',
				'description' => 'nullable|string',
				'website' => 'nullable|url|max:191',
				'status' => 'required|string|in:published,draft',
				'order' => 'required|integer|min:0',
				'is_featured' => 'required|boolean',
				'logo' => 'nullable|file|image|mimes:webp,png|max:2048',
				'thumbnail' => 'nullable|file|image|mimes:webp,png|max:2048',
				'ar_thumbnail' => 'nullable|file|image|mimes:webp,png|max:2048',
			]);

			$brandData = [
				'name' => $validated['name'],
				'description' => $validated['description'] ?? null,
				'website' => $validated['website'] ?? null,
				'status' => $validated['status'],
				'order' => $validated['order'],
				'is_featured' => (bool)$validated['is_featured'],
			];

			if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
				$storage = app('Illuminate\Support\Facades\Storage');
				$folderPath = env('STORAGE_ENV', 'default') . "/brands";
				$logoPath = $request->file('logo')->store($folderPath, 's3');
				$brandData['logo'] = $storage::disk('s3')->url($logoPath);
			}

			if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {
				$storage = app('Illuminate\Support\Facades\Storage');
				$folderPath = env('STORAGE_ENV', 'default') . "/brands";
				$logoPath = $request->file('thumbnail')->store($folderPath, 's3');
				$brandData['thumbnail'] = $storage::disk('s3')->url($logoPath);
			}

			if ($request->hasFile('ar_thumbnail') && $request->file('ar_thumbnail')->isValid()) {
				$storage = app('Illuminate\Support\Facades\Storage');

				$folderPath = env('STORAGE_ENV', 'default') . "/brands";
				$logoPath = $request->file('ar_thumbnail')->store($folderPath, 's3');
				$brandData['ar_thumbnail'] = $storage::disk('s3')->url($logoPath);
			}

			$brand = Brand::create($brandData);

			if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
				$brand->translateOrNew('en')->name_tr = $validated['name'];
				$brand->translateOrNew('en')->description_tr = $validated['description'] ?? null;
			}

			$brand->save();

			return response()->json([
				'success' => true,
				'message' => 'Brand created successfully',
				'brand' => $brand->fresh()
			], 201);
		} catch (\Exception $e) {
			\Log::error('Brand creation error: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Failed to create brand',
				'error' => $e->getMessage()
			], 422);
		}
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
		if (!auth()->user()->can('show brand')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
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
	 *                 @OA\Property(
	 *                     property="thumbnail",
	 *                     type="string",
	 *                     format="binary",
	 *                     description="thumbnail file upload"
	 *                 ),
	 *                 @OA\Property(
	 *                     property="ar_thumbnail",
	 *                     type="string",
	 *                     format="binary",
	 *                     description="ar_thumbnail file upload"
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
		if (!auth()->user()->can('update brand')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
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
				'name' => 'required|string|max:191',
				'description' => 'nullable|string',
				'website' => 'nullable|url|max:191',
				'status' => 'sometimes|required|string|in:published,draft',
				'order' => 'sometimes|required|integer|min:0',
				'is_featured' => 'sometimes|required|boolean',
				'logo' => 'nullable|file|image|mimes:webp,png|max:2048',
				'thumbnail' => 'nullable|file|image|mimes:webp,png|max:2048',
				'ar_thumbnail' => 'nullable|file|image|mimes:webp,png|max:2048',
			]);

			$updateData = [];

			foreach(['name', 'description', 'website', 'status', 'order'] as $field) {
				if (isset($validated[$field])) {
					$updateData[$field] = $validated[$field];
				}
			}

			// Handle is_featured specifically to ensure correct boolean conversion
			if (isset($validated['is_featured'])) {
				$updateData['is_featured'] = (bool)$validated['is_featured'];
			}

			/* Handle logo upload */
			if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
				deleteS3FileFromUrl($brand->logo);
				$updateData['logo'] = uploadImageToWebpS3FromFile($request, 'logo', env('STORAGE_ENV', 'default') . '/brands');
			}

			/* Handle thumbnail upload */
			if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {
				deleteS3FileFromUrl($brand->thumbnail);
				$updateData['thumbnail'] = uploadImageToWebpS3FromFile($request, 'thumbnail', env('STORAGE_ENV', 'default') . '/brands');
			}

			/* Handle arabic thumbnail upload */
			if ($request->hasFile('ar_thumbnail') && $request->file('ar_thumbnail')->isValid()) {
				deleteS3FileFromUrl($brand->ar_thumbnail);
				$updateData['ar_thumbnail'] = uploadImageToWebpS3FromFile($request, 'ar_thumbnail', env('STORAGE_ENV', 'default') . '/brands');
			}

			// Update the brand
			$brand->update($updateData);

			if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
				$brand->translateOrNew('en')->name_tr = $validated['name'];
				$brand->translateOrNew('en')->description_tr = $validated['description'] ?? null;
			}
			$brand->save();

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
		if (!auth()->user()->can('delete brand')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
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

		/* Proceed with deletion */
		if (method_exists($brand, 'translations')) {
			$brand->translations()->delete();
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
		if (!auth()->user()->can('list brand')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$query = Brand::select('id', 'name', 'logo', 'website', 'is_featured', 'description', 'status', 'created_at', 'updated_at' , 'thumbnail' , 'ar_thumbnail')
		->withCount('products')
		->with([
			'products' => function ($query) {
				$query->select('id', 'brand_id')->with('categories:id,name');
			}
		]);

		if ($request->filled('search')) {
			$query->where('name', 'LIKE', '%' . $request->search . '%');
		}

		if ($request->filled('status')) {
			$query->where('status', $request->status);
		}

		$sortBy = $request->input('sort_by', 'created_at');
		$sortOrder = $request->input('sort_order', 'desc');

		if (in_array($sortBy, ['id', 'name', 'created_at']) && in_array($sortOrder, ['asc', 'desc'])) {
			$query->orderBy($sortBy, $sortOrder);
		}

		$brands = $query->paginate($request->input('per_page', 10));

		// Cache all categories and stores in a single query to avoid N+1
		$categories = Category::pluck('name', 'id');
		$stores = Vendor::pluck('name', 'id');

		$transformed = $brands->getCollection()->transform(function ($brand) use ($categories, $stores) {
			$categoryIds = $brand->products->flatMap(function ($product) {
				return $product->categories->pluck('id');
			})->unique();

			$categoryNames = $categoryIds->map(function ($id) use ($categories) {
				return $categories[$id] ?? null;
			})->filter()->values();

			// $storeIds = $brand->products->pluck('vendor_id')->unique();

			// $storeNames = $storeIds->map(function ($id) use ($stores) {
			// 	return $stores[$id] ?? null;
			// })->filter()->values();

		// Simplify logo generation
			$logoUrl = null;
			if ($brand->logo) {
				$logoUrl = filter_var($brand->logo, FILTER_VALIDATE_URL)
				? $brand->logo
				: asset('storage/' . $brand->logo); // skip S3 exists check
			}
			$thumbnailUrl = null;
			if ($brand->thumbnail) {
				$thumbnailUrl = filter_var($brand->thumbnail, FILTER_VALIDATE_URL)
				? $brand->thumbnail
				: asset('storage/' . $brand->thumbnail); // skip S3 exists check
			}
			$ar_thumbnailUrl = null;
			if ($brand->ar_thumbnail) {
				$ar_thumbnailUrl = filter_var($brand->ar_thumbnail, FILTER_VALIDATE_URL)
				? $brand->ar_thumbnail
				: asset('storage/' . $brand->ar_thumbnail); // skip S3 exists check
			}


			return [
				'id' => $brand->id,
				'name' => $brand->name,
				'logo' => $logoUrl,
				'thumbnail' => $thumbnailUrl,
				'ar_thumbnail' => $ar_thumbnailUrl,
				'slug' => $brand->website,
				'is_featured' => $brand->is_featured,
				'description' => $brand->description,
				'status' => $brand->status,
				'products_count' => $brand->products_count,
				'category_name' => $categoryNames,
				'store_name' => "",
				'created_at' => $brand->created_at,
				'updated_at' => $brand->updated_at,
			];
		});

		$brands->setCollection($transformed);

		return response()->json([
			'success' => true,
			'pagination' => [
				'total' => $brands->total(),
				'per_page' => $brands->perPage(),
				'current_page' => $brands->currentPage(),
				'last_page' => $brands->lastPage(),
				'next_page_url' => $brands->nextPageUrl(),
				'prev_page_url' => $brands->previousPageUrl(),
			],
			'data' => $brands->items(),
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/brands/{id}/sku",
	 *     summary="Get Brandwise SKU list",
	 *     tags={"Brands"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Brand ID",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getBrandSku($id)
	{
		$brand = Brand::find($id);

		if (!$brand) {
			return response()->json([
				'success' => false,
				'message' => 'Brand not found'
			], 404);
		}
		$uniqueSkus = $brand->products()->select('id', 'sku', 'name')->get();

		return response()->json([
			'success' => true,
			'data' => $uniqueSkus
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/products/{id}/media",
	 *     summary="Get product media attributes",
	 *     tags={"Brands"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Product ID",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getProductMedia($id)
	{
		$product = Product::select('images', 'video_path', 'documents')->find($id);

		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found'
			], 404);
		}

		// Decode JSON fields if valid
		foreach (['images', 'video_path', 'documents'] as $field) {
			if (isset($product->$field) && json_validate($product->$field)) {
				$product->$field = json_decode($product->$field);
			} else {
				$product->$field = [];
			}
		}

		return response()->json([
			'success' => true,
			'data' => $product
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/products/{id}/media/{type}/download",
	 *     summary="Download product media as ZIP",
	 *     description="Downloads all media files (images, videos, documents) associated with the specified product as a ZIP archive.",
	 *     operationId="downloadProductMediaZip",
	 *     tags={"Brands"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the product",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="type",
	 *         in="path",
	 *         description="Type of media to download (image, video, document, all)",
	 *         required=true,
	 *         @OA\Schema(
	 *             type="string",
	 *             enum={"image", "video", "document", "all"},
	 *             example="all"
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="ZIP file containing product media",
	 *         @OA\MediaType(
	 *             mediaType="application/zip",
	 *             @OA\Schema(type="string", format="binary")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Product not found")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function downloadMediaZip($id, $type = 'all')
	{
		$product = Product::find($id);

		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found'
			], 404);
		}

		/* Define type mapping */
		$typeMap = [
			'image' => 'images',
			'video' => 'video_path',
			'document' => 'documents',
		];

		/* Validate type */
		if ($type !== 'all' && !array_key_exists($type, $typeMap)) {
			return response()->json([
				'success' => false,
				'message' => 'Invalid type specified.'
			], 400);
		}

		$mediaUrls = [];

		foreach (['images', 'video_path', 'documents'] as $field) {
			if (!empty($product->$field) && json_validate($product->$field)) {
				$mediaUrls[$field] = json_decode($product->$field, true);
			} else {
				$mediaUrls[$field] = [];
			}
		}

		/* Filter mediaUrls based on $type */
		if ($type !== 'all') {
			$mediaUrls = array_filter($mediaUrls, function ($key) use ($typeMap, $type) {
				return $key === $typeMap[$type];
			}, ARRAY_FILTER_USE_KEY);
		}

		/* Document validation */
		if (isset($mediaUrls['documents'])) {
			foreach ($mediaUrls['documents'] as $item) {
				if (!is_array($item) || !array_key_exists('title', $item)) {
					return response()->json([
						'success' => false,
						'message' => 'Failed to create ZIP file. Invalid documents'
					], 404);
				}
			}
		}

		$tempDir = storage_path('app/temp_media');
		if (!file_exists($tempDir)) {
			mkdir($tempDir, 0755, true);
		}

		$zipFileName = 'product_' . $id . '_media_' . Str::random(8) . '.zip';
		$zipFilePath = $tempDir . '/' . $zipFileName;

		$zip = new ZipArchive;
		if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
			foreach ($mediaUrls as $typeKey => $items) {
				if ($typeKey === 'documents') {
					foreach ($items as $doc) {
						try {
							$url = $doc['path'];
							$title = $doc['title'];

							$filePath = $this->extractS3PathFromUrl($url);

							if ($filePath && Storage::disk('s3')->exists($filePath)) {
								$filename = basename(parse_url($url, PHP_URL_PATH));
								$zipPath = $typeKey . '/' . $title . '/' . $filename;
								$stream = Storage::disk('s3')->readStream($filePath);
								$zip->addFromString($zipPath, stream_get_contents($stream));
								fclose($stream);
							}
						} catch (\Exception $e) {
							return response()->json([
								'success' => false,
								'message' => 'Failed to create ZIP file. ' . $e->getMessage()
							], 500);
						}
					}
				} else {
					foreach ($items as $index => $url) {
						try {
							$filePath = $this->extractS3PathFromUrl($url);

							if ($filePath && Storage::disk('s3')->exists($filePath)) {
								$extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
								$filename = $typeKey . '/' . $typeKey . '_' . ($index + 1) . '.' . $extension;
								$stream = Storage::disk('s3')->readStream($filePath);
								$zip->addFromString($filename, stream_get_contents($stream));
								fclose($stream);
							}
						} catch (\Exception $e) {
							return response()->json([
								'success' => false,
								'message' => 'Failed to create ZIP file. ' . $e->getMessage()
							], 500);
						}
					}
				}
			}
			$zip->close();
		} else {
			return response()->json([
				'success' => false,
				'message' => 'Failed to create ZIP file'
			], 500);
		}
		return response()->download($zipFilePath)->deleteFileAfterSend(true);
	}

	/**
	 * Extract S3 file path from full URL
	 *
	 * @param string $url
	 * @return string|null
	 */
	private function extractS3PathFromUrl($url)
	{
		if (!$url || !Str::startsWith($url, [env('AWS_URL'), env('AWS_CACHE_URL')])) {
			return null;
		}

		$filePath = Str::after($url, env('AWS_URL') . '/');

		if ($filePath === $url) {
			$filePath = Str::after($url, env('AWS_CACHE_URL') . '/');
		}

		return $filePath !== $url ? $filePath : null;
	}

	/**
	 * @OA\Get(
	 *     path="/api/brands/{id}/categories",
	 *     summary="Get categories by brand ID",
	 *     description="Fetches all unique categories that are associated with products of the given brand.",
	 *     operationId="getBrandCategories",
	 *     tags={"Brands"},
	 * 	  security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the brand",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of categories for the specified brand",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="brand_id", type="integer", example=1),
	 *             @OA\Property(
	 *                 property="categories",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=3),
	 *                     @OA\Property(property="name", type="string", example="Electronics"),
	 *                     @OA\Property(property="slug", type="string", example="electronics"),
	 *                     @OA\Property(property="description", type="string", example="Category for electronic items")
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Brand not found"
	 *     )
	 * )
	 */
	public function getCategories($id)
	{
		$brand = Brand::with(['products.categories:id,name'])->findOrFail($id);

		// Flatten and get unique categories, only with id and name
		$categories = $brand->products
		->flatMap(function ($product) {
			return $product->categories->map(function ($category) {
				return [
					'id' => $category->id,
					'name' => $category->name,
				];
			});
		})
		->unique('id')
		->values();

		return response()->json([
			'sucess' => 'true',
			'brand_id' => $id,
			'categories' => $categories
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/brands/generate-translation",
	 *     summary="Generate or update brand translation",
	 *     description="This endpoint generates or updates translations for an brand and its values.",
	 *     tags={"Brands"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"id", "locale", "name"},
	 *             @OA\Property(property="id", type="integer", example=1, description="ID of the brand to translate"),
	 *             @OA\Property(property="locale", type="string", example="ar", description="Locale code for translation (e.g. ar)"),
	 *             @OA\Property(property="name", type="string", example="الحجم", description="Translated name of the brand"),
	 *             @OA\Property(property="description", type="string", example="الحجم", description="Translated name of the description")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function generateTranslation(Request $request)
	{
		/* Validate request data */
		$validated = $request->validate([
			'id' => 'required|exists:ec_brands,id',
			'locale' => 'required|string|in:ar',
			'name' => 'required|string',
			'description' => 'required|string',
		]);

		$brand = Brand::find($validated['id']);

		DB::beginTransaction();
		try {
			$locale = $validated['locale'];

			/* Update brand translation */
			$brand->translateOrNew($locale)->name_tr = $validated['name'];
			$brand->translateOrNew($locale)->description_tr = $validated['description'];
			$brand->save();

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => __("Translations updated successfully."),
				'data' => $brand->fresh(),
			]);
		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => __("err_update"),
				'error' => $e->getMessage(),
			], 500);
		}
	}
}
