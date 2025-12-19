<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use App\Models\Brand;
use App\Models\Category;

use App\Traits\TransformProduct;

class FBrandController extends Controller
{
	use TransformProduct;

	/**
	 * @OA\Get(
	 *     path="/api/frontend-brands",
	 *     tags={"Frontend-Brand"},
	 *     summary="Get Brand List",
	 *     description="Fetches a list of brands.",
	 *     @OA\Parameter(name="category_id", in="query", description="Category ID", @OA\Schema(type="integer", example=1)),
	 *     @OA\Response(response=200, description="Brands retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function index(Request $request)
	{
		$categoryId = $request->get('category_id');
		$leafCategoryIds = [];

		/* If category_id is provided, get leaf categories */
		if ($categoryId) {
			$category = Category::where('status', 'published')->find($categoryId);

			/* Check if category exists */
			if (!$category) {
				return response()->json([
					'success' => false,
					'message' => 'Category not found or not published'
				], 404);
			}

			/* Get leaf category IDs */
			$leafCategoryIds = $category->getLeafCategories()
			->where('status', 'published')
			->pluck('id')
			->toArray();

			/* If no leaf categories found, return empty */
			if (empty($leafCategoryIds)) {
				return response()->json([
					'success' => true,
					'message' => 'No brands found for this category',
					'data' => []
				]);
			}
		}

		/* Build brands query */
		$brandsQuery = Brand::select(['id', 'name', 'logo'])
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url'
		])
		->where('status', 'published')
		->whereNotNull('logo')
		->where('logo', '!=', '')
		->where('logo', '!=', 'null');

		/* Filter by category if provided */
		if (!empty($leafCategoryIds)) {
			$brandsQuery->whereHas('products', function ($query) use ($leafCategoryIds) {
				$query->whereHas('categories', function ($q) use ($leafCategoryIds) {
					$q->whereIn('id', $leafCategoryIds);
				});
			});
		}

		$brands = $brandsQuery->get();

		/* Transform brands */
		$brands->transform(function ($brand) {
			$brand->name = $this->getLocalizedData($brand->translations, 'name_tr');
			$brand->slug = optional($brand->seoUrl)->url ?? null;

			unset($brand->translations, $brand->seoUrl);

			return $brand;
		});

		return response()->json([
			'success' => true,
			'message' => 'Brands retrieved successfully.',
			'data' => $brands
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend-brands/featured-products",
	 *     tags={"Frontend-Brand"},
	 *     summary="Get featured brands with their featured products",
	 *     description="Retrieves featured and published brands that contain at least the minimum required number of featured products.",
	 *     @OA\Parameter(name="products_limit", in="query", description="Maximum number of products to return per brand", @OA\Schema(type="integer", example=10)),
	 *     @OA\Parameter(name="limit", in="query", description="Maximum number of brands to return", @OA\Schema(type="integer", example=5)),
	 *     @OA\Parameter(name="min_products", in="query", description="Minimum number of featured products required per brand", @OA\Schema(type="integer", example=5)),
	 *     @OA\Parameter(name="search", in="query", description="Search products by name or SKU", @OA\Schema(type="string", example="blender")),
	 *     @OA\Parameter(name="min_rating", in="query", description="Minimum average rating (1-5)", @OA\Schema(type="number", example=4.0)),
	 *     @OA\Parameter(name="price_min", in="query", description="Minimum price (uses sale price if available)", @OA\Schema(type="number", example=100.00)),
	 *     @OA\Parameter(name="price_max", in="query", description="Maximum price (uses sale price if available)", @OA\Schema(type="number", example=5000.00)),
	 *     @OA\Response(response=200, description="Featured brands retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function getFeaturedBrandProducts(Request $request)
	{
		$productsLimit = $request->get('products_limit', 10);
		$limit = $request->get('limit', 5);
		$minProducts = $request->get('min_products', 5);
		$search = $request->get('search');
		$minRating = $request->get('min_rating');
		$priceMin = $request->get('price_min');
		$priceMax = $request->get('price_max');

		$records = Brand::select(['id', 'name'])
		->where('status', 'published')
		->where('is_featured', 1)
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url',
			'featuredProducts' => function($query) use ($productsLimit, $search, $minRating, $priceMin, $priceMax) {
				$query->select(['id', 'name', 'sku', 'currency_id', 'units_sold', 'alt_tags', 'quote_available'])
				->search($search)
				->minRating($minRating)
				->priceRange($priceMin, $priceMax)
				->with([
					'translations',
					'seoUrl:id,relational_id,relational_type,url',
					'productSuppliers:id,product_id,vendor_id,vendor_sku,cost_per_item,sale_price,price,inventory,in_stock,min_quantity,is_fixed,delivery_days,return_policy,free_shipping,shipping_charge,warranty_information',
					'reviews:id,product_id,star',
					'currency:id,title,symbol',
					'sellingUnitAttribute'
				])
				->withCount('reviews')
				->withAvg('reviews', 'star')
				->orderByDesc('units_sold')
				->limit($productsLimit);
			}
		])
		->has('featuredProducts', '>=', $minProducts)
		->take($limit)
		->get();

		/* Transform brands */
		$records->transform(function ($brand) {
			/* Transform brand name to locale object */
			$brand->name = $this->getLocalizedData($brand->translations, 'name_tr');

			/* Transform featured products */
			$brand->featuredProducts->each(function ($product) {
				$this->transformFeaturedProduct($product);
			});

			/* Remove unwanted attributes from brand */
			unset($brand->translations);
			unset($brand->seoUrl);

			return $brand;
		});

		return response()->json([
			'success' => true,
			'message' => 'Featured brands retrieved successfully',
			'data' => $records
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend-brands/customer-featured-products",
	 *     tags={"Frontend-Brand"},
	 *     summary="Get featured brands with their featured products",
	 *     description="Retrieves featured and published brands that contain at least the minimum required number of featured products.",
	 *     @OA\Parameter(name="products_limit", in="query", description="Maximum number of products to return per brand", @OA\Schema(type="integer", example=10)),
	 *     @OA\Parameter(name="limit", in="query", description="Maximum number of brands to return", @OA\Schema(type="integer", example=5)),
	 *     @OA\Parameter(name="min_products", in="query", description="Minimum number of featured products required per brand", @OA\Schema(type="integer", example=5)),
	 *     @OA\Parameter(name="search", in="query", description="Search products by name or SKU", @OA\Schema(type="string", example="blender")),
	 *     @OA\Parameter(name="min_rating", in="query", description="Minimum average rating (1-5)", @OA\Schema(type="number", example=4.0)),
	 *     @OA\Parameter(name="price_min", in="query", description="Minimum price (uses sale price if available)", @OA\Schema(type="number", example=100.00)),
	 *     @OA\Parameter(name="price_max", in="query", description="Maximum price (uses sale price if available)", @OA\Schema(type="number", example=5000.00)),
	 *     @OA\Response(response=200, description="Featured brands retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function getCustomerFeaturedBrandProducts(Request $request)
	{
		$productsLimit = $request->get('products_limit', 10);
		$limit = $request->get('limit', 5);
		$minProducts = $request->get('min_products', 5);
		$search = $request->get('search');
		$minRating = $request->get('min_rating');
		$priceMin = $request->get('price_min');
		$priceMax = $request->get('price_max');
		$wishlistProductIds = auth()->user()->wishlist()->pluck('product_id')->all();

		$records = Brand::select(['id', 'name'])
		->where('status', 'published')
		->where('is_featured', 1)
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url',
			'featuredProducts' => function($query) use ($productsLimit, $search, $minRating, $priceMin, $priceMax) {
				$query->select(['id', 'name', 'sku', 'currency_id', 'units_sold', 'alt_tags', 'quote_available'])
				->search($search)
				->minRating($minRating)
				->priceRange($priceMin, $priceMax)
				->with([
					'translations',
					'seoUrl:id,relational_id,relational_type,url',
					'productSuppliers:id,product_id,vendor_id,vendor_sku,cost_per_item,sale_price,price,inventory,in_stock,min_quantity,is_fixed,delivery_days,return_policy,free_shipping,shipping_charge,warranty_information',
					'reviews:id,product_id,star',
					'currency:id,title,symbol',
					'sellingUnitAttribute'
				])
				->withCount('reviews')
				->withAvg('reviews', 'star')
				->orderByDesc('units_sold')
				->limit($productsLimit);
			}
		])
		->has('featuredProducts', '>=', $minProducts)
		->take($limit)
		->get();

		/* Transform brands */
		$records->transform(function ($brand) use ($wishlistProductIds) {
			/* Transform brand name to locale object */
			$brand->name = $this->getLocalizedData($brand->translations, 'name_tr');

			/* Transform featured products */
			$brand->featuredProducts->each(function ($product) use ($wishlistProductIds) {
				$this->transformFeaturedProduct($product);
				$product->in_wishlist = in_array($product->id, $wishlistProductIds);
			});

			/* Remove unwanted attributes from brand */
			unset($brand->translations);
			unset($brand->seoUrl);

			return $brand;
		});

		return response()->json([
			'success' => true,
			'message' => 'Featured brands retrieved successfully',
			'data' => $records
		]);
	}

	// private function getWishlistProductIds()
	// {
	// 	$userId = Auth::id();

	// 	if ($userId) {
	// 		return Cache::remember("wishlist_user_{$userId}", 60, function () use ($userId) {
	// 			return DB::table('ec_wish_lists')
	// 			->where('customer_id', $userId)
	// 			->pluck('product_id')
	// 			->toArray();
	// 		});
	// 	}

	// 	return session()->get('guest_wishlist', []);
	// }

	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/brand/{id}/categories",
	//  *     tags={"Frontend-Brands"},
	//  *     summary="Get categories by brand",
	//  *     description="Retrieves all unique categories associated with products of the specified brand.",
	//  *     @OA\Parameter(
	//  *         name="id",
	//  *         in="path",
	//  *         description="Brand ID",
	//  *         required=true,
	//  *         @OA\Schema(type="integer", example=1)
	//  *     ),
	//  *     @OA\Response(
	//  *         response=200,
	//  *         description="Successful operation",
	//  *         @OA\JsonContent(
	//  *             @OA\Property(property="sucess", type="string", example="true"),
	//  *             @OA\Property(property="brand_id", type="integer", example=1),
	//  *             @OA\Property(
	//  *                 property="categories",
	//  *                 type="array",
	//  *                 @OA\Items(
	//  *                     @OA\Property(property="id", type="integer", example=1),
	//  *                     @OA\Property(property="name", type="string", example="Smartphones"),
	//  *                     @OA\Property(property="image", type="string", example="https://example.com/storage/categories/smartphones.jpg")
	//  *                 )
	//  *             )
	//  *         )
	//  *     ),
	//  *     @OA\Response(
	//  *         response=404,
	//  *         description="Brand not found",
	//  *         @OA\JsonContent(
	//  *             @OA\Property(property="message", type="string", example="Brand not found.")
	//  *         )
	//  *     )
	//  * )
	//  */
	// public function getCategories($id)
	// {
	// 	// 🧠 Determine if $id is numeric (brand ID) or a slug
	// 	if (is_numeric($id)) {
	// 		$brand = Brand::with([
	// 			'products' => function ($query) {
	// 				$query->where('status', 'published')
	// 				->whereHas('categories', fn($q) => $q->where('status', 'published'));
	// 			},
	// 			'products.categories.seoURL' // ✅ Load seoURL for categories
	// 		])->findOrFail($id);
	// 	} else {
	// 		$seoEntry = DB::table('seo_management')
	// 		->where('url', $id)
	// 		->where('relational_type', 'Brand')
	// 		->first();

	// 		if (!$seoEntry) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Brand not found with slug',
	// 			], 404);
	// 		}

	// 		$brand = Brand::with([
	// 			'products' => function ($query) {
	// 				$query->where('status', 'published')
	// 				->whereHas('categories', fn($q) => $q->where('status', 'published'));
	// 			},
	// 			'products.categories.seoURL' // ✅ Load seoURL for categories
	// 		])->findOrFail($seoEntry->relational_id);
	// 	}

	// 	$categoryCounts = [];

	// 	foreach ($brand->products as $product) {
	// 		foreach ($product->categories as $category) {
	// 		// Check if this category is a published leaf
	// 			$hasPublishedChildren = Category::where('parent_id', $category->id)
	// 			->where('status', 'published')
	// 			->exists();

	// 			if ($hasPublishedChildren) {
	// 				continue; // Skip non-leaf categories
	// 			}

	// 			if (!isset($categoryCounts[$category->id])) {
	// 				$categoryCounts[$category->id] = [
	// 					'id' => $category->id,
	// 					'name' => $category->name,
	// 					'image' => $category->image,
	// 					'url' => optional($category->seoURL)->url, // ✅ Add category URL
	// 					'product_count' => 0
	// 				];
	// 			}

	// 			$categoryCounts[$category->id]['product_count']++;
	// 		}
	// 	}

	// 	$categories = array_values($categoryCounts);

	// 	return response()->json([
	// 		'success' => true,
	// 		'brand_id' => $brand->id,
	// 		'categories' => $categories
	// 	]) ->header('Cache-Control', 'public, max-age=86400');
	// }

	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/products/brand/{brandId}/category/{categoryId?}",
	//  *     tags={"Frontend-Brands"},
	//  *     summary="Get products by brand and optional category",
	//  *     description="Retrieves published products for a specific brand, optionally filtered by category with search functionality and pagination.",
	//  *     @OA\Parameter(
	//  *         name="brandId",
	//  *         in="path",
	//  *         description="Brand ID",
	//  *         required=true,
	//  *         @OA\Schema(type="integer", example=1)
	//  *     ),
	//  *     @OA\Parameter(
	//  *         name="categoryId",
	//  *         in="path",
	//  *         description="Category ID (optional)",
	//  *         required=false,
	//  *         @OA\Schema(type="integer", example=1)
	//  *     ),
	//  *     @OA\Parameter(
	//  *         name="search",
	//  *         in="query",
	//  *         description="Search by product name or SKU",
	//  *         required=false,
	//  *         @OA\Schema(type="string", example="iPhone")
	//  *     ),
	//  *     @OA\Parameter(
	//  *         name="page",
	//  *         in="query",
	//  *         description="Page number for pagination",
	//  *         required=false,
	//  *         @OA\Schema(type="integer", minimum=1, example=1)
	//  *     ),
	//  *     @OA\Response(
	//  *         response=200,
	//  *         description="Successful operation",
	//  *         @OA\JsonContent(
	//  *             @OA\Property(property="success", type="boolean", example=true),
	//  *             @OA\Property(property="message", type="string", example="Products retrieved successfully"),
	//  *             @OA\Property(
	//  *                 property="data",
	//  *                 type="array",
	//  *                 @OA\Items(
	//  *                     @OA\Property(property="id", type="integer", example=1),
	//  *                     @OA\Property(property="name", type="string", example="iPhone 14 Pro"),
	//  *                     @OA\Property(
	//  *                         property="images",
	//  *                         type="array",
	//  *                         @OA\Items(type="string", example="https://example.com/storage/products/iphone14pro.jpg")
	//  *                     ),
	//  *                     @OA\Property(property="video_url", type="string", example="https://youtube.com/watch?v=xyz"),
	//  *                     @OA\Property(
	//  *                         property="video_path",
	//  *                         type="array",
	//  *                         @OA\Items(type="string", example="https://example.com/storage/videos/iphone14pro.mp4")
	//  *                     ),
	//  *                     @OA\Property(property="sku", type="string", example="IPH14PRO-001"),
	//  *                     @OA\Property(property="original_price", type="number", format="float", example=1099.99),
	//  *                     @OA\Property(property="front_sale_price", type="number", format="float", example=1099.99),
	//  *                     @OA\Property(property="sale_price", type="number", format="float", example=999.99),
	//  *                     @OA\Property(property="price", type="number", format="float", example=1099.99),
	//  *                     @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
	//  *                     @OA\Property(property="end_date", type="string", format="date", example="2024-12-31"),
	//  *                     @OA\Property(property="warranty_information", type="string", example="1 year limited warranty"),
	//  *                     @OA\Property(property="currency", type="string", example="USD"),
	//  *                     @OA\Property(property="total_reviews", type="integer", example=245),
	//  *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.7),
	//  *                     @OA\Property(property="best_price", type="number", format="float", example=999.99),
	//  *                     @OA\Property(property="delivery_days", type="string", nullable=true, example=null),
	//  *                     @OA\Property(property="leftStock", type="integer", example=42),
	//  *                     @OA\Property(property="currency_title", type="string", example="$1099.99")
	//  *                 )
	//  *             ),
	//  *             @OA\Property(
	//  *                 property="pagination",
	//  *                 @OA\Property(property="total", type="integer", example=150),
	//  *                 @OA\Property(property="per_page", type="integer", example=50),
	//  *                 @OA\Property(property="current_page", type="integer", example=1),
	//  *                 @OA\Property(property="last_page", type="integer", example=3)
	//  *             )
	//  *         )
	//  *     ),
	//  *     @OA\Response(
	//  *         response=404,
	//  *         description="Brand not found",
	//  *         @OA\JsonContent(
	//  *             @OA\Property(property="message", type="string", example="Brand not found.")
	//  *         )
	//  *     ),
	//  *     @OA\Response(
	//  *         response=500,
	//  *         description="Server error",
	//  *         @OA\JsonContent(
	//  *             @OA\Property(property="success", type="boolean", example=false),
	//  *             @OA\Property(property="message", type="string", example="An error occurred while fetching products"),
	//  *             @OA\Property(property="error", type="string", example="Database connection failed")
	//  *         )
	//  *     )
	//  * )
	//  */
	// public function getProductsByBrandAndCategory(Request $request, $brandId, $categoryId = null)
	// {
	// 	try {
	// 		$userId = auth()->id();
	// 		$isUserLoggedIn = $userId !== null;

	// 		// 🧠 Get wishlist product IDs
	// 		$wishlistProductIds = $isUserLoggedIn
	// 		? DB::table('ec_wish_lists')
	// 		->where('customer_id', $userId)
	// 		->pluck('product_id')
	// 		->map(fn($id) => (int) $id)
	// 		->toArray()
	// 		: session()->get('guest_wishlist', []);

	// 		$searchTerm = strtolower($request->input('search'));

	// 		// 🔍 Determine if $brandId is a numeric ID or a slug from seo_management
	// 		if (is_numeric($brandId)) {
	// 			$brand = Brand::with([
	// 				'products' => function ($query) {
	// 					$query->where('status', 'published')
	// 					->whereHas('categories', function ($catQuery) {
	// 						$catQuery->where('status', 'published');
	// 					});
	// 				},
	// 				'products.categories' => function ($query) {
	// 					$query->where('status', 'published');
	// 				}
	// 			])->findOrFail($brandId);
	// 		} else {
	// 			$seoEntry = \DB::table('seo_management')
	// 			->where('url', $brandId)
	// 			->where('relational_type', 'Brand')
	// 			->first();

	// 			if (!$seoEntry) {
	// 				return response()->json(['success' => false, 'message' => 'Brand not found'], 404);
	// 			}

	// 			$brand = Brand::with([
	// 				'products' => function ($query) {
	// 					$query->where('status', 'published')
	// 					->whereHas('categories', function ($catQuery) {
	// 						$catQuery->where('status', 'published');
	// 					});
	// 				},
	// 				'products.categories' => function ($query) {
	// 					$query->where('status', 'published');
	// 				}
	// 			])->findOrFail($seoEntry->relational_id);
	// 		}


	// 		// 🔎 Filter by category (works with both ID or URL)
	// 		if (!is_null($categoryId)) {
	// 			if (!is_numeric($categoryId)) {
	// 		// If slug, resolve to category ID
	// 				$seoCategory = DB::table('seo_management')
	// 				->where('url', $categoryId)
	// 				->where('relational_type', 'Category')
	// 				->first();

	// 				if (!$seoCategory) {
	// 					return response()->json([
	// 						'success' => false,
	// 						'message' => 'Category not found'
	// 					], 404) ->header('Cache-Control', 'public, max-age=86400');
	// 				}

	// 				$categoryId = $seoCategory->relational_id;
	// 			}

	// 		// Now always filter by ID (whether original or resolved)
	// 			$filteredProducts = $brand->products->filter(function ($product) use ($categoryId) {
	// 				return $product->categories->contains('id', $categoryId);
	// 			})->values();
	// 		} else {
	// 			$filteredProducts = $brand->products;
	// 		}


	// 		// 🔍 Filter by search term
	// 		if (!empty($searchTerm)) {
	// 			$filteredProducts = $filteredProducts->filter(function ($product) use ($searchTerm) {
	// 				return stripos($product->name, $searchTerm) !== false;
	// 			})->values();
	// 		}

	// 		if ($filteredProducts->isEmpty()) {
	// 			return response()->json([
	// 				'success' => true,
	// 				'message' => 'No products found for this brand' . ($categoryId ? ' and category' : '') . ($searchTerm ? ' with search term' : ''),
	// 				'data' => [],
	// 				'pagination' => $this->emptyPagination(),
	// 			]);
	// 		}

	// 		$productIds = $filteredProducts->pluck('id')->toArray();

	// 		$productsWithRelations = Product::whereIn('id', $productIds)
	// 		->with([
	// 			'reviews:id,product_id,star',
	// 			'currency',
	// 			'productSuppliers',
	// 			'seoUrl'
	// 		])
	// 		->get()
	// 		->keyBy('id');

	// 		$perPage = 50;
	// 		$page = max(1, (int) $request->input('page', 1));
	// 		$total = count($productIds);
	// 		$offset = ($page - 1) * $perPage;
	// 		$paginatedProducts = $filteredProducts->slice($offset, $perPage);

	// 		$pagination = $this->buildPagination($page, $perPage, $total);

	// 		$transformedProducts = $paginatedProducts->map(function ($product) use ($productsWithRelations, $wishlistProductIds) {
	// 			$productWithRelations = $productsWithRelations->get($product->id) ?? $product;

	// 			$imageUrls = is_string($product->images)
	// 			? json_decode($product->images, true)
	// 			: (array) $product->images;

	// 			$videos = is_string($product->video_path)
	// 			? json_decode($product->video_path, true) ?? []
	// 			: ($product->video_path ?? []);

	// 			$totalReviews = $productWithRelations->reviews ? $productWithRelations->reviews->count() : 0;
	// 			$avgRating = $totalReviews > 0 ? $productWithRelations->reviews->avg('star') : null;

	// 			$quantity = $product->quantity ?? 0;
	// 			$unitsSold = $product->units_sold ?? 0;
	// 			$leftStock = $quantity - $unitsSold;

	// 			$sellingType = null;

	// 			if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
	// 				$fullValue = $product->sellingUnitAttribute->attribute_value;

	// 				$attributeUnit = strpos($fullValue, '/') !== false
	// 				? trim(explode('/', $fullValue)[1])
	// 				: $fullValue;

	// 				$sellingType = [
	// 					'attribute_value' => $product->sellingUnitAttribute->attribute_value,
	// 					'attribute_value_unit' => $attributeUnit,
	// 				];
	// 			}

	// 			$firstSupplier = $product->productSuppliers->first();

	// 			return [
	// 				'id' => $product->id,
	// 				'name' => $product->name,
	// 				'sku' => $product->sku,
	// 				'category_url' => $product->category_url(),
	// 				'parent_category_url' => $product->parent_category_url(),
	// 				'url' => $product->seoUrl->url ?? null,
	// 				'vendor_sku' => $firstSupplier->vendor_sku ?? null,
	// 				'price' => $firstSupplier ? (float) $firstSupplier->price : null,
	// 				'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
	// 				'total_reviews' => $totalReviews,
	// 				'avg_rating' => $avgRating,
	// 				'left_stock' => $leftStock,
	// 				'currency_title' => $productWithRelations->currency
	// 				? ($productWithRelations->currency->is_prefix_symbol
	// 					? $productWithRelations->currency->symbol
	// 					: ($product->price . ' ' . $productWithRelations->currency->symbol))
	// 				: $product->price,
	// 				'in_wishlist' => in_array($product->id, $wishlistProductIds),
	// 				'images' => $imageUrls,
	// 				"original_price" => $firstSupplier ? (float) $firstSupplier->price : null,
	// 				'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
	// 				"best_price" => $firstSupplier ? (float) $firstSupplier->price : null,
	// 				"selling_type" => $sellingType,
	// 				"per_unit_price" => $product->per_unit_price,
	// 				'vendor_id' => $firstSupplier->vendor_id ?? null,
	// 				'map' => $firstSupplier ? (float) $firstSupplier->map : null,
	// 				'inventory' => $firstSupplier->inventory ?? null,
	// 				'in_stock' => $firstSupplier->in_stock ?? null,
	// 				'delivery_days' => $firstSupplier->delivery_days ?? null,
	// 				'return_policy' => $firstSupplier->return_policy ?? null,
	// 				'free_shipping' => $firstSupplier->free_shipping ?? null,
	// 				'warranty_information' => $firstSupplier->warranty_information ?? null,
	// 				'min_quantity' => $firstSupplier->min_quantity ?? 0,
	// 				'is_fixed' => $firstSupplier->is_fixed ?? 0,
	// 				'quote_available' => $product->quote_available ?? null,
	// 				'isRequired' => $product->isRequired,
	// 			];
	// 		});

	// 		return response()->json([
	// 			'success' => true,
	// 			'data' => $transformedProducts->values(),
	// 			'pagination' => $pagination,
	// 			'message' => 'Products retrieved successfully',
	// 		]) ->header('Cache-Control', 'public, max-age=86400');
	// 	} catch (\Exception $e) {
	// 		Log::error('Error in getProductsByBrandAndCategory: ' . $e->getMessage());
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'An error occurred while fetching products',
	// 			'error' => $e->getMessage(),
	// 		], 500);
	// 	}
	// }

	// protected function emptyPagination()
	// {
	// 	return [
	// 		'total' => 0,
	// 		'per_page' => 0,
	// 		'current_page' => 1,
	// 		'last_page' => 1,
	// 	];
	// }


	// protected function buildPagination($page, $perPage, $total)
	// {
	// 	return [
	// 		'total' => $total,
	// 		'per_page' => $perPage,
	// 		'current_page' => $page,
	// 		'last_page' => ceil($total / $perPage),
	// 	];
	// }

	// protected function normalizeMediaUrls($media)
	// {
	// 	if (is_array($media)) {
	// 		return array_map(fn ($url) => url($url), $media);
	// 	}
	// 	return $media ? url($media) : null;
	// }


	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/brands/alphabetical",
	//  *     tags={"Frontend-Brands"},
	//  *     summary="Get all brands alphabetically",
	//  *     description="Retrieves all published brands either grouped alphabetically or filtered by starting letter.",
	//  *     @OA\Parameter(
	//  *         name="letter",
	//  *         in="query",
	//  *         description="Filter brands by starting letter (A-Z)",
	//  *         required=false,
	//  *         @OA\Schema(
	//  *             type="string",
	//  *             pattern="^[A-Z]$",
	//  *             example="A"
	//  *         )
	//  *     ),
	//  *     @OA\Response(
	//  *         response="200",
	//  *         description="Successful operation - Filtered by letter",
	//  *         @OA\JsonContent(
	//  *             @OA\Property(property="success", type="boolean", example=true),
	//  *             @OA\Property(property="message", type="string", example="Brands starting with letter 'A'."),
	//  *             @OA\Property(
	//  *                 property="data",
	//  *                 type="array",
	//  *                 @OA\Items(
	//  *                     @OA\Property(property="id", type="integer", example=1),
	//  *                     @OA\Property(property="name", type="string", example="Apple"),
	//  *                     @OA\Property(property="logo", type="string", example="https://example.com/storage/brands/apple-logo.png")
	//  *                 )
	//  *             )
	//  *         )
	//  *     )
	//  * )
	//  */

	// public function getAllBrandsAlphabetically(Request $request): JsonResponse
	// {
	// 	$letter = strtoupper($request->query('letter')); // e.g. ?letter=B

	// 	$brandsQuery = Brand::where('status', 'published')
	// 	->whereNotNull('thumbnail') // Only include brands with a thumbnail
	// 	->select('id', 'name', 'logo', 'thumbnail', 'ar_thumbnail')
	// 	->orderBy('name');

	// 	if ($letter) {
	// 		$brandsQuery->where('name', 'LIKE', $letter . '%');
	// 	}

	// 	$brands = $brandsQuery->get()->map(function ($brand) {
	// 		$brand->logo = $brand->logo ? asset($brand->logo) : null;
	// 		$brand->thumbnail = $brand->thumbnail ? asset($brand->thumbnail) : null;
	// 		$brand->ar_thumbnail = $brand->ar_thumbnail ? asset($brand->ar_thumbnail) : null;

	// 	// 👇 Add the slug from seo_management
	// 		$seoEntry = DB::table('seo_management')
	// 		->where('relational_id', $brand->id)
	// 		->where('relational_type', 'Brand')
	// 		->first();

	// 		$brand->slug = $seoEntry?->url ?? null;

	// 		return $brand;
	// 	});

	// 	if ($letter) {
	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => "Brands starting with letter '$letter'.",
	// 			'data' => $brands
	// 		]);
	// 	} else {
	// 		$grouped = $brands->groupBy(function ($brand) {
	// 			return strtoupper(substr($brand->name, 0, 1));
	// 		})->sortKeys();

	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => 'Brands grouped alphabetically.',
	// 			'data' => $grouped
	// 		]) ->header('Cache-Control', 'public, max-age=86400');
	// 	}
	// }
}
