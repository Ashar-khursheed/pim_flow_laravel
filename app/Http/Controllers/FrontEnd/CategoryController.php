<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use App\Models\Product;
use App\Models\Brand;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Models\AttributeValue;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\ProductAttribute;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{

	/**
	 * @OA\Get(
	 *     path="/api/frontend/categories",
	 *     tags={"Frontend-Categories"},
	 *     summary="Get all product categories (with optional filter)",
	 *     description="Returns a parent-child tree of product categories. You can optionally filter by parent category ID and limit the number of child categories.",
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="query",
	 *         required=false,
	 *         description="Parent category ID to filter by",
	 *         @OA\Schema(type="integer", example=5)
	 *     ),
	 *     @OA\Parameter(
	 *         name="limit",
	 *         in="query",
	 *         required=false,
	 *         description="Limit the number of child categories returned per parent (default is 12)",
	 *         @OA\Schema(type="integer", example=10)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Categories tree fetched successfully",
	 *         @OA\JsonContent(
	 *             type="array",
	 *             @OA\Items(
	 *                 type="object",
	 *                 @OA\Property(property="id", type="integer", example=1),
	 *                 @OA\Property(property="name", type="string", example="Electronics"),
	 *                 @OA\Property(property="image", type="string", example="http://example.com/storage/categories/electronics.jpg"),
	 *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
	 *                 @OA\Property(
	 *                     property="children",
	 *                     type="array",
	 *                     @OA\Items(ref="#/components/schemas/Category")
	 *                 )
	 *             )
	 *         )
	 *     )
	 * )
	 */

	public function index(Request $request)
	{
		$filterId = $request->get('id'); // Optional ID filter
		$limit = $request->get('limit', 12); // Default limit to 12

		if ($filterId) {
			// Fetch the specific category and its children (parent included)
			$categories = Category::where('status', 'published')
			->where(function ($query) use ($filterId) {
				$query->where('id', $filterId)
				->orWhere('parent_id', $filterId);
			})
			->get();
		} else {
			// Fetch all categories if no ID is provided
			$categories = Category::all();
		}

		// Transform categories into a parent-child structure
		$categoriesTree = $this->buildTree($categories, $filterId, $limit);

		// // Add full URLs for images (both parent and child categories)
		// foreach ($categoriesTree as $category) {
		// 	// $category->image = $this->getImageUrl($category->image); // Modify image for parent category

		// 	// Recursively modify images for children and children's children
		// 	// $this->addImageUrlsRecursively($category);
		// }

		return response()->json($categoriesTree);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/categories/{slug}",
	 *     tags={"Frontend-Categories"},
	 *     summary="Get a category and its children by slug",
	 *     description="Returns a tree of product categories for a given category slug, including its direct children. Optionally limit the number of child categories.",
	 *     @OA\Parameter(
	 *         name="slug",
	 *         in="path",
	 *         required=true,
	 *         description="Slug of the parent category",
	 *         @OA\Schema(type="string", example="electronics")
	 *     ),
	 *     @OA\Parameter(
	 *         name="limit",
	 *         in="query",
	 *         required=false,
	 *         description="Limit the number of child categories returned per parent (default is 12)",
	 *         @OA\Schema(type="integer", example=10)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Category tree fetched successfully",
	 *         @OA\JsonContent(
	 *             type="array",
	 *             @OA\Items(ref="#/components/schemas/Category")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Category not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Category not found")
	 *         )
	 *     )
	 * )
	 */
	public function categoryslug(Request $request, $slug)
	{
		$limit = $request->get('limit', 12); // Default limit to 12

		// Fetch the specific category by slug and its children (parent included)
		$parentCategory = Category::where('slug', $slug)->first();

		if (!$parentCategory) {
			return response()->json(['message' => 'Category not found'], 404);
		}

		$categories = Category::where('id', $parentCategory->id)
		->orWhere('parent_id', $parentCategory->id)
		->get();

		// Transform categories into a parent-child structure
		$categoriesTree = $this->buildTree($categories, null, $limit);

		// Add full URLs for images (both parent and child categories)
		// foreach ($categoriesTree as $category) {
		// 	$category->image = $this->getImageUrl($category->image); // Modify image for parent category

		// 	// Recursively modify images for children and children's children
		// 	$this->addImageUrlsRecursively($category);
		// }

		return response()->json($categoriesTree);
	}


	/**
	 * @OA\Get(
	 *     path="/api/frontend/categories/{id}",
	 *     tags={"Frontend-Categories"},
	 *     summary="Get a category by ID",
	 *     description="Retrieve the details of a single category using its ID.",
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="The ID of the category to retrieve",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Category details retrieved successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="category", ref="#/components/schemas/Category")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Category not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Category not found")
	 *         )
	 *     )
	 * )
	 */
	public function show($id)
	{
		// Validate that the ID is numeric
		if (!is_numeric($id)) {
			return response()->json([
				'message' => "Invalid category ID format."
			], 400);
		}

		$category = Category::find($id);

		if (!$category) {
			return response()->json([
				'message' => "Category with ID $id not found."
			], 404);
		}

		// // Add image URL if image exists
		// $category->image = $this->getImageUrl($category->image);

		return response()->json([
			'category' => $category,
		]);
	}


	/**
	 * @OA\Get(
	 *     path="/api/frontend/categories/{categoryId}/products",
	 *     tags={"Frontend-Categories"},
	 *     summary="Get products by category ID",
	 *     description="Retrieve a paginated list of products that belong to a specific category, including related data like brand, tags, and product types.",
	 *     @OA\Parameter(
	 *         name="categoryId",
	 *         in="path",
	 *         required=true,
	 *         description="The ID of the category to fetch products from",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         required=false,
	 *         description="Number of products per page (pagination)",
	 *         @OA\Schema(type="integer", example=10)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of products for the category",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="category", ref="#/components/schemas/Category"),
	 *             @OA\Property(property="products", type="object",
	 *                 @OA\Property(property="current_page", type="integer", example=1),
	 *                 @OA\Property(property="data", type="array",
	 *                     @OA\Items(ref="#/components/schemas/Product")
	 *                 ),
	 *                 @OA\Property(property="last_page", type="integer", example=5),
	 *                 @OA\Property(property="per_page", type="integer", example=10),
	 *                 @OA\Property(property="total", type="integer", example=50)
	 *             ),
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Category not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Category not found")
	 *         )
	 *     )
	 * )
	 */

	public function getProductsByCategory($categoryId)
	{
		$category = Category::find($categoryId);

		if (!$category) {
			return response()->json(['message' => 'Category not found'], 404);
		}

		// Update category image URL to include the full path
		// $category->image = $this->getCategoryImageUrl($category->image); // Convert the image name to the full URL

		$perPage = request()->get('per_page', 10);
		$perPage = is_numeric($perPage) && $perPage > 0 ? (int)$perPage : 10;

		$products = $category->products()->with(['categories', 'brand'])->paginate($perPage);

		$products->getCollection()->transform(function ($product) {
			$totalReviews = $product->reviews->count();
			$avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;

			$product->total_reviews = $totalReviews;
			$product->avg_rating = $avgRating;

			if ($product->currency) {
				$product->currency_title = $product->currency->is_prefix_symbol
				? $product->currency->symbol . ' '
				: $product->price . ' ' . $product->currency->symbol;
			} else {
				$product->currency_title = $product->price;
			}

			// // Update product images URLs
			// $product->images = collect($product->images)->map(function ($image) {
			// 	// Check if image exists in 'storage/products/' directory
			// 	$imagePath = public_path('storage/products/' . $image);
			// 	if (file_exists($imagePath)) {
			// 		return asset('storage/products/' . $image);
			// 	}

			// 	// Check if image exists in the general 'storage/' directory
			// 	$imagePath = public_path('storage/' . $image);
			// 	if (file_exists($imagePath)) {
			// 		return asset('storage/' . $image);
			// 	}

			// 	// If image doesn't exist in either directory, return a default placeholder or null
			// 	return asset('storage/default-placeholder.jpg'); // Replace with a valid placeholder image
			// });

			return $product;
		});

		return response()->json([
			'category' => $category,
			'products' => $products
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/products/specification-filters",
	 *     operationId="getSpecificationFilters",
	 *     tags={"Frontend-Categories"},
	 *     summary="Fetch products with dynamic specification filters",
	 *     description="Retrieve filtered products based on category, specifications, price, brand, and rating. Also returns filter options.",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"category_id"},
	 *             @OA\Property(property="category_id", type="integer", example=1, description="ID of the product category"),
	 *             @OA\Property(property="filters", type="array", @OA\Items(
	 *                 type="object",
	 *                 @OA\Property(property="specification_name", type="string", example="Color"),
	 *                 @OA\Property(property="specification_value", oneOf={
	 *                     @OA\Schema(type="string", example="Red"),
	 *                     @OA\Schema(type="array", @OA\Items(type="string", example="Red")),
	 *                     @OA\Schema(
	 *                         type="array",
	 *                         @OA\Items(
	 *                             type="object",
	 *                             @OA\Property(property="min", type="number", example=10),
	 *                             @OA\Property(property="max", type="number", example=100)
	 *                         )
	 *                     )
	 *                 })
	 *             )),
	 *             @OA\Property(property="price_min", type="number", example=100, description="Minimum price"),
	 *             @OA\Property(property="price_max", type="number", example=1000, description="Maximum price"),
	 *             @OA\Property(property="price_order", type="string", enum={"high_to_low", "low_to_high"}, example="low_to_high"),
	 *             @OA\Property(property="brand_id", type="array", @OA\Items(type="integer", example=2), description="Filter by brand IDs"),
	 *             @OA\Property(property="rating", type="number", format="float", example=4, description="Minimum average rating"),
	 *             @OA\Property(property="per_page", type="integer", example=10, description="Pagination count per page"),
	 *             @OA\Property(property="sort_by", type="string", example="price", description="Sort field (price or created_at)"),
	 *             @OA\Property(property="sort_by_type", type="string", example="asc", description="Sort direction (asc or desc)")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of filtered products and available filters",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="filters", type="array", @OA\Items(type="object")),
	 *             @OA\Property(property="products", type="array", @OA\Items(type="object")),
	 *             @OA\Property(property="brands", type="array", @OA\Items(type="object")),
	 *             @OA\Property(
	 *                 property="rating_filter",
	 *                 type="object",
	 *                 @OA\Property(property="filter_name", type="string", example="Rating"),
	 *                 @OA\Property(property="filter_type", type="string", example="rating"),
	 *                 @OA\Property(property="filter_values", type="array", @OA\Items(type="integer", example=5))
	 *             ),
	 *             @OA\Property(property="debug_info", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="Validation error or category not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="object")
	 *         )
	 *     )
	 * )
	 */


	

	// public function getSpecificationFilters1(Request $request)
	// {
	// 	// Existing validation code
	// 	$validator = Validator::make($request->all(), [
	// 		'category_id' => 'required|string',
	// 		'filters' => 'nullable|array',
	// 		'price_min' => 'nullable|numeric|min:0',
	// 		'price_max' => 'nullable|numeric|min:0',
	// 		'price_order' => 'nullable|in:high_to_low,low_to_high',
	// 		'brand_id' => 'nullable|array',
	// 		'brand_id.*' => 'integer',
	// 		'rating' => 'nullable|numeric|min:1|max:5',
	// 	]);
	
	// 	if ($validator->fails()) {
	// 		return response()->json(['success' => false, 'message' => $validator->errors()], 400);
	// 	}
	
	// 	$perPage = $request->get('per_page', 10);
	// 	$categoryIdentifier = $request->input('category_id');
	// 	$category = null;

	// 	if (is_numeric($categoryIdentifier)) {
	// 		$category = Category::find($categoryIdentifier);
	// 	} else {
	// 		$category = Category::where('slug', $categoryIdentifier)->first();
	// 	}

	// 	if (!$category) {
	// 		return response()->json(['success' => false, 'message' => 'Category does not exist.'], 400);
	// 	}

	
	// 	// Get products from current category
	// 	$currentCategoryProducts = $category->products()->where('status', 'published')->pluck('id')->all();
	// 	// Get all child categories based on parent_id
	// 	$childCategories = Category::where('parent_id', $category->id)->get();
	// 	$childCategoryIds = $childCategories->pluck('id')->toArray();
	
	// 	// Get all products from child categories
	// 	$childProductIds = [];
	// 	if (!empty($childCategoryIds)) {
	// 		foreach ($childCategories as $childCategory) {
	// 			$childProductIds = array_merge($childProductIds, $childCategory->products()->where('status', 'published')->pluck('id')->all());
	// 		}
	// 	}
	
	// 	// Combine products from current category and all child categories
	// 	$allCategoryProductIds = array_unique(array_merge($currentCategoryProducts, $childProductIds));
	
	// 	// Debug info for verification
	// 	$debugInfo = [
	// 		'category_id' => $request->category_id,
	// 		'current_category_product_count' => count($currentCategoryProducts),
	// 		'child_categories' => $childCategoryIds,
	// 		'child_categories_count' => count($childCategoryIds),
	// 		'child_products_count' => count($childProductIds),
	// 		'total_products' => count($allCategoryProductIds)
	// 	];
	
	// 	if (empty($allCategoryProductIds)) {
	// 		return response()->json([
	// 			'success' => true,
	// 			'filters' => [],
	// 			'products' => [],
	// 			'brands' => [],
	// 			'price_min' => 0,
	// 			'price_max' => 0,
	// 			'rating_filter' => [
	// 				'filter_name' => 'Rating',
	// 				'filter_type' => 'rating',
	// 				'filter_values' => [5, 4, 3, 2, 1],
	// 			],
	// 			'debug_info' => $debugInfo
	// 		]);
	// 	}
	
	// 	// Start with all category product IDs (including child categories)
	// 	$filteredProductIds = collect($allCategoryProductIds);
	
	// 	// Group filters by specification name for proper application
	// 	$groupedFilters = [];
	// 	$rangeFiltersByAttribute = [];
	// 	$selectedFilters = []; // Track selected filters
	
	// 	if ($request->has('filters') && is_array($request->filters)) {
	// 		foreach ($request->filters as $filter) {
	// 			if (!isset($filter['specification_name']) || !isset($filter['specification_value']) || empty($filter['specification_value'])) {
	// 				continue;
	// 			}
	
	// 			$specName = $filter['specification_name'];
	// 			$specValues = is_array($filter['specification_value']) ? $filter['specification_value'] : [$filter['specification_value']];
	
	// 			// Track selected filters
	// 			$selectedFilters[$specName] = $specValues;
	
	// 			// Check if this is a range filter
	// 			$isRangeFilter = false;
	// 			foreach ($specValues as $value) {
	// 				if (is_array($value) && isset($value['min']) && isset($value['max'])) {
	// 					$isRangeFilter = true;
	
	// 					// Store range filters by attribute name
	// 					if (!isset($rangeFiltersByAttribute[$specName])) {
	// 						$rangeFiltersByAttribute[$specName] = [];
	// 					}
	// 					$rangeFiltersByAttribute[$specName][] = $value;
	// 				}
	// 			}
	
	// 			// If not a range filter, add to regular grouped filters
	// 			if (!$isRangeFilter) {
	// 				if (!isset($groupedFilters[$specName])) {
	// 					$groupedFilters[$specName] = [];
	// 				}
	// 				$groupedFilters[$specName] = array_merge($groupedFilters[$specName], $specValues);
	// 			}
	// 		}
	// 	}
	
	// 	$debugInfo['grouped_filters'] = $groupedFilters;
	// 	$debugInfo['range_filters_by_attribute'] = $rangeFiltersByAttribute;
	// 	$debugInfo['selected_filters'] = $selectedFilters;
	
	// 	// Apply regular attribute filters if provided, grouped by specification name
	// 	foreach ($groupedFilters as $specName => $specValues) {
	// 		// Find attribute ID based on name
	// 		$attribute = Attribute::where('name', $specName)->first();
	// 		if (!$attribute) {
	// 			continue;
	// 		}
	
	// 		// Find product IDs that match this attribute and values
	// 		$matchingProductIds = DB::table('product_attributes as pa')
	// 			->where('pa.attribute_id', $attribute->id)
	// 			->whereIn('pa.attribute_value', $specValues)
	// 			->whereIn('pa.product_id', $filteredProductIds)
	// 			->pluck('pa.product_id')
	// 			->unique();
	
	// 		// Intersect with our running list of product IDs
	// 		$filteredProductIds = $filteredProductIds->intersect($matchingProductIds);
	
	// 		// If no products match these filters, return empty results early
	// 		if ($filteredProductIds->isEmpty()) {
	// 			return response()->json([
	// 				'success' => true,
	// 				'filters' => [],
	// 				'products' => [],
	// 				'brands' => [],
	// 				'price_min' => 0,
	// 				'price_max' => 0,
	// 				'rating_filter' => [
	// 					'filter_name' => 'Rating',
	// 					'filter_type' => 'rating',
	// 					'filter_values' => [5, 4, 3, 2, 1],
	// 				],
	// 				'debug_info' => array_merge($debugInfo, ['filter_applied' => $specName, 'empty_after' => true])
	// 			]);
	// 		}
	// 	}
	
	// 	// Apply range filters by attribute
	// 	foreach ($rangeFiltersByAttribute as $specName => $ranges) {
	// 		// Find attribute ID based on name
	// 		$attribute = Attribute::where('name', $specName)->first();
	// 		if (!$attribute) {
	// 			continue;
	// 		}
	
	// 		// Start with the base query
	// 		$query = DB::table('product_attributes as pa')
	// 			->where('pa.attribute_id', $attribute->id)
	// 			->whereIn('pa.product_id', $filteredProductIds);
	
	// 		// Build range conditions for this attribute - using OR between ranges of the same attribute
	// 		$rangeConditions = [];
	// 		foreach ($ranges as $range) {
	// 			$min = $range['min'];
	// 			$max = $range['max'];
	
	// 			// For numeric attribute values, handle different formats
	// 			$rangeConditions[] = "(CAST(pa.attribute_value AS DECIMAL(10,2)) BETWEEN $min AND $max OR
	// 				CAST(REGEXP_REPLACE(pa.attribute_value, '[^0-9].*', '') AS DECIMAL(10,2)) BETWEEN $min AND $max)";
	// 		}
	
	// 		// Only add WHERE condition if we have range conditions
	// 		if (count($rangeConditions) > 0) {
	// 			// Use OR between ranges of the same attribute
	// 			$query->whereRaw('(' . implode(' OR ', $rangeConditions) . ')');
	// 		}
	
	// 		// Get products that match ANY of the ranges for this attribute
	// 		$matchingProductIds = $query->pluck('pa.product_id')->unique();
	
	// 		// Intersect with our running list of product IDs
	// 		$filteredProductIds = $filteredProductIds->intersect($matchingProductIds);
	
	// 		// If no products match these filters, return empty results early
	// 		if ($filteredProductIds->isEmpty()) {
	// 			return response()->json([
	// 				'success' => true,
	// 				'filters' => [],
	// 				'products' => [],
	// 				'price_min' => 0,
	// 				'price_max' => 0,
	// 				'brands' => [],
	// 				'rating_filter' => [
	// 					'filter_name' => 'Rating',
	// 					'filter_type' => 'rating',
	// 					'filter_values' => [5, 4, 3, 2, 1],
	// 				],
	// 				'debug_info' => array_merge($debugInfo, ['range_filter_applied' => $specName, 'empty_after_range' => true])
	// 			]);
	// 		}
	// 	}
	
	// 	// Apply brand filter before rating filter
	// 	if ($request->has('brand_id') && $request->brand_id) {
	// 		$brandFilteredIds = Product::whereIn('id', $filteredProductIds)
	// 			->whereIn('brand_id', $request->brand_id)
	// 			->pluck('id');
	
	// 		$filteredProductIds = $filteredProductIds->intersect($brandFilteredIds);
	
	// 		if ($filteredProductIds->isEmpty()) {
	// 			return response()->json([
	// 				'success' => true,
	// 				'filters' => [],
	// 				'products' => [],
	// 				'brands' => [],
	// 				'price_min' => 0,
	// 				'price_max' => 0,
	// 				'rating_filter' => [
	// 					'filter_name' => 'Rating',
	// 					'filter_type' => 'rating',
	// 					'filter_values' => [5, 4, 3, 2, 1],
	// 				],
	// 				'debug_info' => array_merge($debugInfo, ['empty_after_brand' => true])
	// 			]);
	// 		}
	// 	}
	
	// 	if ($request->has('price_min') || $request->has('price_max')) 
	// 	{
	// 		$min = $request->input('price_min', 0);
	// 		$max = $request->input('price_max', PHP_INT_MAX);

	// 		// Debug the price filtering
	// 		$debugInfo['price_filter_applied'] = [
	// 			'min' => $min,
	// 			'max' => $max,
	// 			'input_product_count' => $filteredProductIds->count()
	// 		];

	// 		// First, let's check what price data we have
	// 		$priceCheckQuery = DB::table('product_suppliers as ps')
	// 			->whereIn('ps.product_id', $filteredProductIds->toArray())
	// 			->select('ps.product_id', 'ps.price', 'ps.sale_price',
	// 					DB::raw('CASE WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 THEN ps.sale_price ELSE ps.price END as effective_price'))
	// 			->get();

	// 		$debugInfo['price_check_sample'] = $priceCheckQuery->take(5)->toArray();
	// 		$debugInfo['price_check_count'] = $priceCheckQuery->count();

	// 		// Filter products based on price range
	// 		$priceFilteredIds = DB::table('product_suppliers as ps')
	// 			->whereIn('ps.product_id', $filteredProductIds->toArray())
	// 			->where(function($query) use ($min, $max) {
	// 				$query->whereRaw("CASE WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 THEN ps.sale_price ELSE ps.price END BETWEEN ? AND ?", [$min, $max]);
	// 			})
	// 			->pluck('ps.product_id')
	// 			->unique();

	// 		$debugInfo['price_filtered_count'] = $priceFilteredIds->count();
	// 		$debugInfo['price_filtered_sample'] = $priceFilteredIds->take(5)->toArray();

	// 		// If no results, try alternative approach
	// 		if ($priceFilteredIds->isEmpty()) {
	// 				// Try using COALESCE instead
	// 				$priceFilteredIds = DB::table('product_suppliers as ps')
	// 					->whereIn('ps.product_id', $filteredProductIds->toArray())
	// 					->whereRaw("COALESCE(ps.sale_price, ps.price) BETWEEN ? AND ?", [$min, $max])
	// 					->pluck('ps.product_id')
	// 					->unique();
					
	// 				$debugInfo['price_filtered_coalesce_count'] = $priceFilteredIds->count();
					
	// 				// If still no results, try checking the products table directly
	// 				if ($priceFilteredIds->isEmpty()) {
	// 					$priceFilteredIds = DB::table('ec_products as p')
	// 						->whereIn('p.id', $filteredProductIds->toArray())
	// 						->whereRaw("COALESCE(p.sale_price, p.price) BETWEEN ? AND ?", [$min, $max])
	// 						->pluck('p.id')
	// 						->unique();
						
	// 					$debugInfo['price_filtered_products_table_count'] = $priceFilteredIds->count();
	// 					$debugInfo['used_products_table_for_price'] = true;
	// 				}
	// 			}

	// 			$filteredProductIds = $filteredProductIds->intersect($priceFilteredIds);

	// 			if ($filteredProductIds->isEmpty()) {
	// 				return response()->json([
	// 					'success' => true,
	// 					'filters' => [],
	// 					'products' => [],
	// 					'brands' => [],
	// 					'price_min' => 0,
	// 					'price_max' => 0,
	// 					'rating_filter' => [
	// 						'filter_name' => 'Rating',
	// 						'filter_type' => 'rating',
	// 						'filter_values' => [5, 4, 3, 2, 1],
	// 					],
	// 					'debug_info' => array_merge($debugInfo, ['empty_after_price' => true])
	// 				]);
	// 			}
	// 	}

	
	// 	// If a rating filter is applied, filter the already filtered product IDs
	// 	if ($request->has('rating') && $request->rating) {
	// 		$ratingFilteredIds = DB::table('ec_reviews')
	// 			->whereIn('product_id', $filteredProductIds)
	// 			->select('product_id')
	// 			->groupBy('product_id')
	// 			->havingRaw('ROUND(AVG(star)) = ?', [$request->rating])
	// 			->pluck('product_id');
	
	// 		$filteredProductIds = $filteredProductIds->intersect($ratingFilteredIds);
	
	// 		if ($filteredProductIds->isEmpty()) {
	// 			return response()->json([
	// 				'success' => true,
	// 				'message' => 'No products found with ' . $request->rating . ' star' . ($request->rating == 1 ? '' : 's') . ' rating. Try adjusting your filters.',
	// 				'filters' => [],
	// 				'products' => [],
	// 				'brands' => [],
	// 				'price_min' => 0,
	// 				'price_max' => 0,
	// 				'rating_filter' => [
	// 					'filter_name' => 'Rating',
	// 					'filter_type' => 'rating',
	// 					'filter_values' => [5, 4, 3, 2, 1],
	// 				],
	// 				'debug_info' => array_merge($debugInfo, ['empty_after_rating' => true, 'applied_rating' => $request->rating])
	// 			]);
	// 		}
	// 	}
	
	// 	// Now we have the final filtered product IDs - use these for both products and filters
	// 	$debugInfo['filtered_product_ids_count'] = $filteredProductIds->count();
	
	// 	// Fetching products based on filters
	// 	$products = Product::whereIn('id', $filteredProductIds)
	// 		->where('status', 'published')
	// 		->with(['currency', 'reviews', 'productSuppliers', 'brand', 'seoUrl', 'productAttributes' => function ($query) {
	// 			$query->whereHas('attributeDetails', function ($q) {
	// 				$q->whereIn('name', ['Units per Case', 'Pack Type']);
	// 			});
	// 		}]);
	
	// 	// Apply sorting
	// 	// Apply sorting
	// 	// Apply sorting
	// 	$sortBy = $request->input('sort_by', 'created_at');
	// 	$sortByType = $request->input('sort_by_type', 'desc');

	// 	// Handle price_order parameter - this takes precedence over sort_by
	// 	if ($request->has('price_order')) {
	// 		$priceOrder = $request->input('price_order');
	// 		$sortByType = $priceOrder === 'high_to_low' ? 'desc' : 'asc';
	// 		$sortBy = 'price';
	// 	}

	// 	if ($sortBy == 'price') {
	// 		// For price sorting, we need to get the best price for each product
	// 		// Get the filtered product IDs
	// 		$productIds = $filteredProductIds->toArray();
			
	// 		// Build a fresh query for price sorting
	// 		$products = Product::with(['currency', 'reviews', 'productSuppliers', 'brand', 'productAttributes' => function ($query) {
	// 				$query->whereHas('attributeDetails', function ($q) {
	// 					$q->whereIn('name', ['Units per Case', 'Pack Type']);
	// 				});
	// 			}])
	// 			->leftJoin('product_suppliers as ps', 'ec_products.id', '=', 'ps.product_id')
	// 			->select('ec_products.*',
	// 				DB::raw('MIN(CASE 
    //             WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 
    //             THEN ps.sale_price 
    //             ELSE ps.price 
    //         END) as best_price')
    //     )
    //     ->whereIn('ec_products.id', $productIds)
    //     ->where('ec_products.status', 'published')
    //     ->groupBy('ec_products.id')
    //     ->orderBy('best_price', $sortByType);
        
   	//  // Now paginate the query

	// 	$paginatedProducts = $products->paginate($perPage);
		
	// 	} else {
	// 		// For other sorting (created_at, name, etc.)
	// 		// Make sure to prefix the column with table name if needed
	// 		$orderColumn = in_array($sortBy, ['created_at', 'updated_at', 'name', 'status']) 
	// 			? "ec_products.{$sortBy}" 
	// 			: $sortBy;
			
	// 		$products = $products->orderBy($orderColumn, $sortByType);
	// 		$paginatedProducts = $products->paginate($perPage);
	// 	}
			
	// 	$paginatedProducts = $products->paginate($perPage);
	
	// 	// Get wishlist product IDs
	// 	$wishlistProductIds = auth()->check() ?
	// 		\App\Models\Wishlist::where('user_id', auth()->id())->pluck('product_id')->toArray() :
	// 		[];
	
	// 	$modifiedProducts = $paginatedProducts->getCollection()->map(function ($product) use ($wishlistProductIds) {
	// 		// Calculate reviews data
	// 		$totalReviews = $product->reviews->count();
	// 		$avgRating = $totalReviews > 0 ? round($product->reviews->avg('star')) : null;
	
	// 		// Clean images
	// 		$cleanedImages = is_string($product->images)
	// 			? json_decode($product->images, true)
	// 			: (array) $product->images;
	
	// 		// Calculate left stock
	// 		$leftStock = $firstSupplier->quantity ?? 0;
	
	// 		$sellingType = null;
	// 		if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
	// 			$fullValue = $product->sellingUnitAttribute->attribute_value;
	
	// 			$attributeUnit = strpos($fullValue, '/') !== false
	// 				? trim(explode('/', $fullValue)[1])
	// 				: $fullValue;
	
	// 			$sellingType = [
	// 				'attribute_value' => $product->sellingUnitAttribute->attribute_value,
	// 				'attribute_value_unit' => $attributeUnit,
	// 			];
	// 		}
	// 		$firstSupplier = $product->productSuppliers->first();
	
	// 		// Calculate per unit price
	// 						$unitsPerCase = null;
	// 			$packType = null;

	// 			if (!empty($details->per_unit_price_attributes)) {
	// 				$unitsPerCase = collect($details->per_unit_price_attributes)
	// 					->first(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
	// 				$packType = collect($details->per_unit_price_attributes)
	// 					->first(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');
	// 			}

	
	// 		$basePrice = null;
	// 		if ($firstSupplier) {
	// 			$basePrice = ($firstSupplier->sale_price > 0) ? $firstSupplier->sale_price : $firstSupplier->price;
	// 		}
	// 		$perUnitPrice = null;
	
	// 		if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
	// 			$unitValue = (float) $unitsPerCase->attribute_value;
	// 			if ($unitValue > 0) {
	// 				$calculated = round($basePrice / $unitValue, 2);
	// 				$perUnitPrice = $calculated . ' ' . '/' . ($packType?->attribute_value ?? '');
	// 			}
	// 		}
	
	// 		$product->per_unit_price = $perUnitPrice;
	
	// 		// FIX: Add null check for firstSupplier
	
	// 		return [
	// 			'id' => $product->id,
	// 			'name' => $product->name,
	// 			'images' => $cleanedImages,
	// 			'url' => $product->seoUrl->url ?? null,
	// 			'video_url' => $product->video_url,
	// 			'video_path' => is_array($product->video_path) ? $product->video_path : (json_decode($product->video_path, true) ?: []),
	// 			'sku' => $product->sku,
	// 			'start_date' => $product->start_date,
	// 			'end_date' => $product->end_date,
	// 			'currency' => $product->currency?->symbol,
	// 			'total_reviews' => $totalReviews,
	// 			'avg_rating' => $avgRating,
	// 			'leftStock' => $leftStock,
	// 			'currency_title' => $product->currency
	// 				? ($product->currency->is_prefix_symbol
	// 					? $product->currency->symbol
	// 					: ($product->price . ' ' . $product->currency->symbol))
	// 				: $product->price,
	// 			'in_wishlist' => in_array($product->id, $wishlistProductIds),
	// 			'selling_type' => $sellingType,
	// 			'per_unit_price' => $product->per_unit_price,
	// 			// FIX: Use null-safe access for all supplier properties
	// 			'vendor_sku' => $firstSupplier?->vendor_sku ?? null,
	// 			'price' => (float) ($firstSupplier?->price ?? 0),
	// 			'sale_price' => (float) ($firstSupplier?->sale_price ?? 0),
	// 			'original_price' => (float) ($firstSupplier?->price ?? 0),
	// 			'front_sale_price' => (float) ($firstSupplier?->sale_price ?? $firstSupplier?->price ?? 0),
	// 			'best_price' => (float) ($firstSupplier?->price ?? 0),
	// 			'vendor_id' => $firstSupplier?->vendor_id ?? null,
	// 			'map' => $firstSupplier ? (float) $firstSupplier->map : null,
	// 			'inventory' => $firstSupplier?->inventory ?? null,
	// 			'in_stock' => $firstSupplier?->in_stock ?? null,
	// 			'delivery_days' => $firstSupplier?->delivery_days ?? null,
	// 			'delivery_days' => $firstSupplier?->delivery_days ?? null,
	// 			'return_policy' => $firstSupplier?->return_policy ?? null,
	// 			'free_shipping' => $firstSupplier?->free_shipping ?? null,
	// 			'warranty_information' => $firstSupplier?->warranty_information ?? null,
	// 		];
	// 	});
	
	// 	$paginatedProducts->setCollection($modifiedProducts);
	
	// 	// Initialize filters array
	// 	$filters = [];
	
	// 	// Get subcategory for this category
	// 	$subCategory = DB::table('sub_categories')
	// 		->where('category_id', $request->category_id)
	// 		->first();
	
	// 	$debugInfo['has_subcategory'] = $subCategory ? true : false;
	
	// 	// Only process attribute filters if subcategory exists
	// 	if ($subCategory) {
	// 		$attributeIdsField = null;
	// 		$attributeIds = [];
	
	// 		// Check which attribute ID field exists
	// 		if (property_exists($subCategory, 'attributes_ids') || isset($subCategory->attributes_ids)) {
	// 			$attributeIdsField = 'attributes_ids';
	// 		} else if (property_exists($subCategory, 'attributes_jd') || isset($subCategory->attributes_jd)) {
	// 			$attributeIdsField = 'attributes_jd';
	// 		}
	
	// 		$debugInfo['attribute_ids_field'] = $attributeIdsField;
	
	// 		// Process attribute IDs if the field exists and has value
	// 		if ($attributeIdsField && !empty($subCategory->$attributeIdsField)) {
	// 			$attributeIdsValue = $subCategory->$attributeIdsField;
	
	// 			// Parse attribute IDs based on data type
	// 			if (is_string($attributeIdsValue)) {
	// 				$attributeIds = json_decode($attributeIdsValue, true);
	// 				$debugInfo['json_decode_error'] = json_last_error_msg();
	
	// 				if (json_last_error() !== JSON_ERROR_NONE) {
	// 					$attributeIds = explode(',', $attributeIdsValue);
	// 					$debugInfo['using_comma_separated'] = true;
	// 				} else if (count($attributeIds) === 1 && is_string($attributeIds[0]) && strpos($attributeIds[0], ',') !== false) {
	// 					$attributeIds = explode(',', $attributeIds[0]);
	// 					$debugInfo['using_nested_comma_separated'] = true;
	// 				}
	// 			} else {
	// 				$attributeIds = $attributeIdsValue;
	// 			}
	
	// 			// Ensure we have an array of integers
	// 			$attributeIds = array_map('intval', (array)$attributeIds);
	// 			$debugInfo['attribute_ids_parsed'] = $attributeIds;
	
	// 			// Only proceed if we have valid attribute IDs
	// 			if (!empty($attributeIds)) {
	// 				// MODIFIED: Get attribute values based on whether filter is selected or not
	// 				foreach ($attributeIds as $attributeId) {
	// 					$attribute = Attribute::find($attributeId);
	// 					if (!$attribute) {
	// 						continue;
	// 					}
	
	// 					$attributeName = $attribute->name;
	// 					$isFilterSelected = isset($selectedFilters[$attributeName]);
	
	// 					// If filter is selected, get ALL values from category (not dynamic)
	// 					// If filter is not selected, get values from filtered products (dynamic)
	// 					$productIdsToUse = $isFilterSelected ? $allCategoryProductIds : $filteredProductIds;
	
	// 					$attributeValues = DB::table('product_attributes as pa')
	// 						->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
	// 						->whereIn('pa.product_id', $productIdsToUse)
	// 						->where('pa.attribute_id', $attributeId)
	// 						->orderBy('pa.attribute_value', 'asc')
	// 						->select('at.name as attribute_name', 'pa.attribute_value', 'at.id as attribute_id', 'pa.product_id')
	// 						->get();
	
	// 					$debugInfo['attribute_values_count_' . $attributeName] = $attributeValues->count();
	// 					$debugInfo['is_filter_selected_' . $attributeName] = $isFilterSelected;
	// 					$debugInfo['product_ids_used_' . $attributeName] = $isFilterSelected ? 'all_category' : 'filtered';
	
	// 					// If we have any attribute values
	// 					if ($attributeValues->count() > 0) {
	// 						$uniqueValues = $attributeValues->pluck('attribute_value')->unique()->filter()->values();
	
	// 						// Helper function to extract clean integer from various formats
	// 						$extractIntegerValue = function($value) {
	// 							if (preg_match('/^(\d+)\s+\d+\/\d+$/', $value, $matches)) {
	// 								return (int)$matches[1];
	// 							} else if (preg_match('/^(\d+)\s+\d+\.\d+$/', $value, $matches)) {
	// 								return (int)$matches[1];
	// 							} else if (is_numeric($value)) {
	// 								return (int)$value;
	// 							}
	// 							return $value;
	// 						};
	
	// 						// Check if all values are numeric-like
	// 						$numericValues = true;
	// 						$cleanedValues = $uniqueValues->map(function($val) use ($extractIntegerValue, &$numericValues) {
	// 							$cleanedVal = $extractIntegerValue($val);
	// 							if (!is_numeric($cleanedVal)) {
	// 								$numericValues = false;
	// 							}
	// 							return $cleanedVal;
	// 						});
	
	// 						// For range filters - sort by min value ascending
	// 						if ($numericValues && $cleanedValues->count() > 2) {
	// 							$sorted = $cleanedValues->filter(function($value) {
	// 								return is_numeric($value);
	// 							})->map(function($val) {
	// 								return (int)$val;
	// 							})->unique()->sort()->values();
	
	// 							$debugInfo['numeric_values_' . $attributeName] = $sorted->toArray();
	
	// 							// Calculate ranges based on actual data
	// 							$chunkCount = min(5, ceil($sorted->count() / 2));
	// 							$chunkSize = ceil($sorted->count() / $chunkCount);
	
	// 							// Check if this attribute has selected range filters
	// 							$selectedRanges = isset($selectedFilters[$attributeName]) ? $selectedFilters[$attributeName] : [];
	
	// 							$ranges = $sorted->chunk($chunkSize)->map(function ($chunk) use ($attributeName, $filteredProductIds, $isFilterSelected) {
	// 								$min = $chunk->first();
	// 								$max = $chunk->last();
	
	// 								// Count products - always from filtered results for actual count
	// 								$productCount = DB::table('product_attributes as pa')
	// 									->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
	// 									->join('ec_products as p', 'p.id', '=', 'pa.product_id')
	// 									->where('at.name', $attributeName)
	// 									->where('p.status', 'published')
	// 									->whereIn('pa.product_id', $filteredProductIds) // Always use filtered for count
	// 									->whereRaw("(CAST(pa.attribute_value AS DECIMAL(10,2)) BETWEEN ? AND ? OR CAST(REGEXP_REPLACE(pa.attribute_value, '[^0-9].*', '') AS DECIMAL(10,2)) BETWEEN ? AND ?)", [$min, $max, $min, $max])
	// 									->distinct('pa.product_id')
	// 									->count('pa.product_id');
	
	// 								return [
	// 									'min' => $min,
	// 									'max' => $max,
	// 									'product_count' => $productCount,
	// 									'display_value' => $min == $max ? $min : "$min - $max",
	// 								];
	// 							})->filter(function($range) use ($isFilterSelected) {
	// 								// If filter is selected, show all ranges (even with 0 count)
	// 								// If filter is not selected, only show ranges with products
	// 								return $isFilterSelected || $range['product_count'] > 0;
	// 							})->sortBy('min')->values()->toArray();
	
	// 							// Add selected ranges that might not have products in current filter
	// 							foreach ($selectedRanges as $selectedRange) {
	// 								if (is_array($selectedRange) && isset($selectedRange['min']) && isset($selectedRange['max'])) {
	// 									$selectedMin = $selectedRange['min'];
	// 									$selectedMax = $selectedRange['max'];
	
	// 									// Check if this selected range is already in the ranges array
	// 									$rangeExists = false;
	// 									foreach ($ranges as $range) {
	// 										if ($range['min'] == $selectedMin && $range['max'] == $selectedMax) {
	// 											$rangeExists = true;
	// 											break;
	// 										}
	// 									}
	
	// 									// If selected range doesn't exist, add it with current count
	// 									if (!$rangeExists) {
	// 										$productCount = DB::table('product_attributes as pa')
	// 											->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
	// 											->join('ec_products as p', 'p.id', '=', 'pa.product_id')
	// 											->where('at.name', $attributeName)
	// 											->where('p.status', 'published')
	// 											->whereIn('pa.product_id', $filteredProductIds)
	// 											->whereRaw("(CAST(pa.attribute_value AS DECIMAL(10,2)) BETWEEN ? AND ? OR CAST(REGEXP_REPLACE(pa.attribute_value, '[^0-9].*', '') AS DECIMAL(10,2)) BETWEEN ? AND ?)", [$selectedMin, $selectedMax, $selectedMin, $selectedMax])
	// 											->distinct('pa.product_id')
	// 											->count('pa.product_id');
	
	// 										$ranges[] = [
	// 											'min' => $selectedMin,
	// 											'max' => $selectedMax,
	// 											'product_count' => $productCount,
	// 											'display_value' => $selectedMin == $selectedMax ? $selectedMin : "$selectedMin - $selectedMax",
	// 											'selected' => true // Mark as selected
	// 										];
	// 									}
	// 								}
	// 							}
	
	// 							// Sort ranges again after adding selected ones
	// 							usort($ranges, function($a, $b) {
	// 								return $a['min'] - $b['min'];
	// 							});
	
	// 							// Only add if we have valid ranges
	// 							if (!empty($ranges)) {
	// 								$filters[] = [
	// 									'specification_name' => $attributeName,
	// 									'specification_type' => 'range',
	// 									'specification_value' => $ranges,
	// 								];
	// 							}
	// 						} else {
	// 							// For fixed values
	// 							$valueCountMap = [];
	
	// 							// Get selected values for this attribute
	// 							$selectedValues = isset($selectedFilters[$attributeName]) ? $selectedFilters[$attributeName] : [];
	
	// 							foreach ($uniqueValues as $value) {
	// 								// Count products - always from filtered results for actual count
	// 								$productCount = DB::table('product_attributes as pa')
	// 									->join('ec_products as p', 'p.id', '=', 'pa.product_id')
	// 									->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
	// 									->where('at.name', $attributeName)
	// 									->where('pa.attribute_value', $value)
	// 									->where('p.status', 'published')
	// 									->whereIn('pa.product_id', $filteredProductIds) // Always use filtered for count
	// 									->distinct('pa.product_id')
	// 									->count('pa.product_id');
	
	// 								// If filter is selected, show all values (even with 0 count)
	// 								// If filter is not selected, only show values with products
	// 								if ($isFilterSelected || $productCount > 0) {
	// 									$valueCountMap[] = [
	// 										'value' => $value,
	// 										'product_count' => $productCount,
	// 										'display_value' => $value . ' (' . $productCount . ')'
	// 									];
	// 								}
	// 							}
	
	// 							// Add selected values that might not have products in current filter
	// 							foreach ($selectedValues as $selectedValue) {
	// 								// Check if this selected value is already in the valueCountMap
	// 								$valueExists = false;
	// 								foreach ($valueCountMap as $valueCount) {
	// 									if ($valueCount['value'] == $selectedValue) {
	// 										$valueExists = true;
	// 										break;
	// 									}
	// 								}
	// 								// If selected value doesn't exist, add it with current count
	// 								if (!$valueExists) {
	// 									$productCount = DB::table('product_attributes as pa')
	// 										->join('ec_products as p', 'p.id', '=', 'pa.product_id')
	// 										->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
	// 										->where('at.name', $attributeName)
	// 										->where('pa.attribute_value', $selectedValue)
	// 										->where('p.status', 'published')
	// 										->whereIn('pa.product_id', $filteredProductIds)
	// 										->distinct('pa.product_id')
	// 										->count('pa.product_id');
	
	// 									$valueCountMap[] = [
	// 										'value' => $selectedValue,
	// 										'product_count' => $productCount,
	// 										'display_value' => $selectedValue . ' (' . $productCount . ')',
	// 										'selected' => true
	// 									];
	// 								}
	// 							}
	
	// 							// Sort by value name
	// 							usort($valueCountMap, function($a, $b) {
	// 								return strcmp($a['value'], $b['value']);
	// 							});
	
	// 							// Only add if we have valid values
	// 							if (!empty($valueCountMap)) {
	// 								$filters[] = [
	// 									'specification_name' => $attributeName,
	// 									'specification_type' => 'fixed',
	// 									'specification_value' => $valueCountMap,
	// 								];
	// 							}
	// 						}
	// 					}
	// 				}
	// 			}
	// 		}
	// 	}
	
	// 	// Get brands for the filtered products
	// 	$selectedBrandIds = $request->brand_id ?? [];

	// 	// Get brands from all category products (not filtered) to show all available brands
	// 	$brands = DB::table('ec_products as p')
	// 		->join('ec_brands as b', 'b.id', '=', 'p.brand_id')
	// 		->whereIn('p.id', $allCategoryProductIds) // Use all category products instead of filtered
	// 		->where('p.status', 'published')
	// 		->select('b.id', 'b.name')
	// 		->groupBy('b.id', 'b.name')
	// 		->orderBy('b.name')
	// 		->get()
	// 		->map(function($brand) use ($filteredProductIds, $selectedBrandIds) {
	// 			// Count products for this brand from filtered results
	// 			$productCount = DB::table('ec_products')
	// 			->where('brand_id', $brand->id)
	// 			->whereIn('id', $filteredProductIds->toArray())
	// 			->where('status', 'published')
	// 			->count();
				
	// 			// Show all brands but with their actual count from filtered results
	// 			// If brand is selected, show it even if count is 0
	// 			$isSelected = in_array($brand->id, $selectedBrandIds);
				
	// 			return [
	// 				'id' => $brand->id,
	// 				'name' => $brand->name,
	// 				'product_count' => $productCount,
	// 				'display_name' => $brand->name . ' (' . $productCount . ')',
	// 				'is_selected' => $isSelected
	// 			];
	// 		})
	// 		->toArray();
	
	// 	// Get price range for the filtered products
	// 	// $priceRange = DB::table('ec_products')
	// 	// 	->whereIn('id', $filteredProductIds)
	// 	// 	->where('status', 'published')
	// 	// 	->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
	// 	// 	->first();
	// 			// Debug the price range query
	// 		// Replace the price range calculation section with this enhanced debugging version

	// 	// Get price range for the filtered products
	// 	$productIdsArray = $filteredProductIds->toArray();

	// 	// First, let's check if we have any product suppliers for these products
	// 	$supplierExists = DB::table('product_suppliers')
	// 		->whereIn('product_id', $productIdsArray)
	// 		->exists();

	// 	$debugInfo['supplier_exists'] = $supplierExists;

	// 	if ($supplierExists) {
	// 		// Let's get detailed information about the supplier data
	// 		$detailedSuppliers = DB::table('product_suppliers')
	// 			->whereIn('product_id', $productIdsArray)
	// 			->select('product_id', 'price', 'sale_price', 
	// 					DB::raw('COALESCE(sale_price, price) as effective_price'))
	// 			->get();
    
	// 	$debugInfo['detailed_suppliers'] = $detailedSuppliers->toArray();
		
	// 	// Count how many have non-zero prices
	// 	$nonZeroPrices = $detailedSuppliers->filter(function($supplier) {
	// 		return $supplier->price > 0 || $supplier->sale_price > 0;
	// 	});
		
	// 	$debugInfo['non_zero_price_count'] = $nonZeroPrices->count();
	// 	$debugInfo['total_supplier_records'] = $detailedSuppliers->count();
		
	// 	// Get some sample non-zero prices if they exist
	// 	$sampleNonZero = $nonZeroPrices->take(5)->toArray();
	// 	$debugInfo['sample_non_zero_prices'] = $sampleNonZero;
		
	// 	// Try different approaches to get the price range
		
	// 	// Approach 1: Using COALESCE (current approach)
	// 	$priceRange1 = DB::table('product_suppliers')
    //     ->whereIn('product_id', $productIdsArray)
    //     ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
    //     ->first();
    
	// 	// Approach 2: Using CASE WHEN
	// 	$priceRange2 = DB::table('product_suppliers')
	// 		->whereIn('product_id', $productIdsArray)
	// 		->selectRaw('MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as min_price, 
	// 					MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as max_price')
	// 		->first();
		
	// 	// Approach 3: Only where prices are greater than 0
	// 	$priceRange3 = DB::table('product_suppliers')
	// 		->whereIn('product_id', $productIdsArray)
	// 		->where(function($query) {
	// 			$query->where('price', '>', 0)
	// 				->orWhere('sale_price', '>', 0);
	// 		})
	// 		->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
	// 		->first();
		
	// 	$debugInfo['price_range_approaches'] = [
	// 		'approach_1_coalesce' => $priceRange1,
	// 		'approach_2_case_when' => $priceRange2,
	// 		'approach_3_non_zero_only' => $priceRange3
	// 	];
    
	// 	// Use the approach that gives us non-zero results
	// 	$priceRange = $priceRange3 && ($priceRange3->min_price > 0 || $priceRange3->max_price > 0) 
	// 				? $priceRange3 
	// 				: ($priceRange2 && ($priceRange2->min_price > 0 || $priceRange2->max_price > 0) 
	// 					? $priceRange2 
	// 					: $priceRange1);
		
	// 	$debugInfo['price_source'] = 'product_suppliers';
	// 	$debugInfo['selected_approach'] = $priceRange === $priceRange3 ? 'approach_3' : 
	// 									($priceRange === $priceRange2 ? 'approach_2' : 'approach_1');
		
	// 		} else {
	// 			// Fallback to products table if no suppliers found
	// 			$priceRange = DB::table('ec_products')
	// 				->whereIn('id', $filteredProductIds)
	// 				->where('status', 'published')
	// 				->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
	// 				->first();
				
	// 			$debugInfo['price_source'] = 'ec_products';
	// 	}

	// 	// Additional debugging to see what's happening
	// 	$debugInfo['price_range_query'] = [
	// 		'filtered_product_ids_count' => count($filteredProductIds),
	// 		'price_range_result' => $priceRange,
	// 		'raw_min' => $priceRange ? $priceRange->min_price : 'null',
	// 		'raw_max' => $priceRange ? $priceRange->max_price : 'null'
	// 	];

	// 	$priceMin = $priceRange ? (float)$priceRange->min_price : 0;
	// 	$priceMax = $priceRange ? (float)$priceRange->max_price : 0;

	// 	$debugInfo['final_price_min'] = $priceMin;
	// 	$debugInfo['final_price_max'] = $priceMax;

	// 	// If we still have zero prices, let's check the actual data structure
	// 	if ($priceMin == 0 && $priceMax == 0) {
	// 		// Check if products have prices in the main products table
	// 		$productPrices = DB::table('ec_products')
	// 			->whereIn('id', array_slice($productIdsArray, 0, 5))
	// 			->select('id', 'price', 'sale_price')
	// 			->get();
			
	// 		$debugInfo['sample_product_prices'] = $productPrices->toArray();
			
	// 		// Check table structure
	// 		$supplierColumns = DB::select("DESCRIBE product_suppliers");
	// 		$debugInfo['supplier_table_structure'] = $supplierColumns;
	// 	}
	// 	// Rating filter
	// 	$ratingFilter = [
	// 		'filter_name' => 'Rating',
	// 		'filter_type' => 'rating',
	// 		'filter_values' => [5, 4, 3, 2, 1],
	// 	];
	
	// 	return response()->json([
	// 		'success' => true,
	// 		'filters' => $filters,
	// 		'products' => $paginatedProducts,
	// 		'brands' => $brands,
	// 		'price_min' => $priceMin,
	// 		'price_max' => $priceMax,
	// 		'rating_filter' => $ratingFilter,
	// 		'debug_info' => $debugInfo
	// 	]);
	// }

public function getSpecificationFilters1(Request $request)
{
    // Validation
    $validator = Validator::make($request->all(), [
        'category_id' => 'required|string',
        'filters' => 'nullable|array',
        'price_min' => 'nullable|numeric|min:0',
        'price_max' => 'nullable|numeric|min:0',
        'price_order' => 'nullable|in:high_to_low,low_to_high',
        'brand_id' => 'nullable|array',
        'brand_id.*' => 'integer',
        'rating' => 'nullable|numeric|min:1|max:5',
    ]);

    if ($validator->fails()) {
        return response()->json(['success' => false, 'message' => $validator->errors()], 400);
    }

    $perPage = $request->get('per_page', 10);
    $categoryIdentifier = $request->input('category_id');
    $category = null;

    if (is_numeric($categoryIdentifier)) {
        $category = Category::find($categoryIdentifier);
    } else {
        $category = Category::where('slug', $categoryIdentifier)->first();
    }

    if (!$category) {
        return response()->json(['success' => false, 'message' => 'Category does not exist.'], 400);
    }

    // Get category measurement unit priorities
    $categoryMeasurementPriorities = DB::table('category_measurement_unit_priorities as cmup')
        ->join('measurement_types as mt', 'mt.id', '=', 'cmup.measurement_type_id')
        ->join('measurement_units as mu_primary', 'mu_primary.id', '=', 'cmup.measurement_unit_primary_id')
        ->where('cmup.category_id', $category->id)
        ->select('mt.name as measurement_type', 'mu_primary.name as primary_unit', 'mu_primary.symbol as primary_symbol')
        ->get()
        ->keyBy('measurement_type');

    // Enhanced helper function to convert attribute values with fallback units
    $convertAttributeValue = function($attributeName, $originalValue) use ($categoryMeasurementPriorities) {
        // First try the database-configured measurement priorities
        foreach ($categoryMeasurementPriorities as $measurementType => $priority) {
            $shouldConvert = false;
            
            switch (strtolower($measurementType)) {
                case 'length':
                    $shouldConvert = (
                        stripos($attributeName, 'length') !== false ||
                        stripos($attributeName, 'height') !== false ||
                        stripos($attributeName, 'width') !== false ||
                        stripos($attributeName, 'depth') !== false ||
                        stripos($attributeName, 'diameter') !== false ||
                        stripos($attributeName, 'dimension') !== false ||
                        stripos($attributeName, 'size') !== false
                    );
                    break;
                case 'mass':
                case 'weight':
                    $shouldConvert = (
                        stripos($attributeName, 'weight') !== false ||
                        stripos($attributeName, 'mass') !== false
                    );
                    break;
                case 'volume':
                    $shouldConvert = (
                        stripos($attributeName, 'volume') !== false ||
                        stripos($attributeName, 'capacity') !== false
                    );
                    break;
                default:
                    $shouldConvert = stripos($attributeName, $measurementType) !== false;
                    break;
            }
            
            if ($shouldConvert) {
                if (preg_match('/^(\d+(?:\.\d+)?)\s*([a-zA-Z]+)$/', trim($originalValue), $matches)) {
                    $numericValue = (float)$matches[1];
                    $originalUnit = $matches[2];
                    $targetUnit = $priority->primary_unit;
                    
                    $convertedValue = convert_unit($measurementType, $numericValue, $originalUnit, $targetUnit);
                    
                    if (is_numeric($convertedValue)) {
                        $roundedValue = (int)round($convertedValue);
                        return [
                            'converted_value' => $roundedValue,
                            'unit' => $targetUnit,
                            'symbol' => $priority->primary_symbol,
                            'display_value' => $roundedValue . ' ' . $priority->primary_symbol,
                            'original_value' => $originalValue,
                            'conversion_applied' => true
                        ];
                    }
                } else if (is_numeric($originalValue)) {
                    $roundedValue = (int)round((float)$originalValue);
                    return [
                        'converted_value' => $roundedValue,
                        'unit' => $priority->primary_unit,
                        'symbol' => $priority->primary_symbol,
                        'display_value' => $roundedValue . ' ' . $priority->primary_symbol,
                        'original_value' => $originalValue,
                        'conversion_applied' => false
                    ];
                }
            }
        }
        
        // Fallback: Assign common units based on attribute names if no database config found
        $fallbackUnits = [
            'width' => ['symbol' => 'cm', 'name' => 'centimeters'],
            'length' => ['symbol' => 'cm', 'name' => 'centimeters'], 
            'height' => ['symbol' => 'cm', 'name' => 'centimeters'],
            'depth' => ['symbol' => 'cm', 'name' => 'centimeters'],
            'diameter' => ['symbol' => 'cm', 'name' => 'centimeters'],
            'weight' => ['symbol' => 'kg', 'name' => 'kilograms'],
            'capacity' => ['symbol' => 'L', 'name' => 'liters'],
            'volume' => ['symbol' => 'L', 'name' => 'liters'],
        ];
        
        foreach ($fallbackUnits as $unitType => $unitInfo) {
            if (stripos($attributeName, $unitType) !== false) {
                if (is_numeric($originalValue)) {
                    $roundedValue = (int)round((float)$originalValue);
                    return [
                        'converted_value' => $roundedValue,
                        'unit' => $unitInfo['name'],
                        'symbol' => $unitInfo['symbol'],
                        'display_value' => $roundedValue . ' ' . $unitInfo['symbol'],
                        'original_value' => $originalValue,
                        'conversion_applied' => false
                    ];
                }
            }
        }
        
        // Return original value if no conversion needed/possible
        return [
            'converted_value' => $originalValue,
            'unit' => null,
            'symbol' => '',
            'display_value' => $originalValue,
            'original_value' => $originalValue,
            'conversion_applied' => false
        ];
    };

    // Get products from current category
    $currentCategoryProducts = $category->products()->where('status', 'published')->pluck('id')->all();
    
    // Get all child categories
    $childCategories = Category::where('parent_id', $category->id)->get();
    $childCategoryIds = $childCategories->pluck('id')->toArray();

    // Get all products from child categories
    $childProductIds = [];
    if (!empty($childCategoryIds)) {
        foreach ($childCategories as $childCategory) {
            $childProductIds = array_merge($childProductIds, $childCategory->products()->where('status', 'published')->pluck('id')->all());
        }
    }

    // Combine products from current category and all child categories
    $allCategoryProductIds = array_unique(array_merge($currentCategoryProducts, $childProductIds));

    if (empty($allCategoryProductIds)) {
        return response()->json([
            'success' => true,
            'filters' => [],
            'products' => [],
            'brands' => [],
            'price_min' => 0,
            'price_max' => 0,
            'rating_filter' => [
                'filter_name' => 'Rating',
                'filter_type' => 'rating',
                'filter_values' => [5, 4, 3, 2, 1],
            ]
        ]);
    }

    // Start with all category product IDs
    $filteredProductIds = collect($allCategoryProductIds);

    // Group filters by specification name
    $groupedFilters = [];
    $rangeFiltersByAttribute = [];
    $selectedFilters = [];

    if ($request->has('filters') && is_array($request->filters)) {
        foreach ($request->filters as $filter) {
            if (!isset($filter['specification_name']) || !isset($filter['specification_value']) || empty($filter['specification_value'])) {
                continue;
            }

            $specName = $filter['specification_name'];
            $specValues = is_array($filter['specification_value']) ? $filter['specification_value'] : [$filter['specification_value']];

            $selectedFilters[$specName] = $specValues;

            $isRangeFilter = false;
            foreach ($specValues as $value) {
                if (is_array($value) && isset($value['min']) && isset($value['max'])) {
                    $isRangeFilter = true;

                    if (!isset($rangeFiltersByAttribute[$specName])) {
                        $rangeFiltersByAttribute[$specName] = [];
                    }
                    $rangeFiltersByAttribute[$specName][] = $value;
                }
            }

            if (!$isRangeFilter) {
                if (!isset($groupedFilters[$specName])) {
                    $groupedFilters[$specName] = [];
                }
                $groupedFilters[$specName] = array_merge($groupedFilters[$specName], $specValues);
            }
        }
    }

    // Apply regular attribute filters
    foreach ($groupedFilters as $specName => $specValues) {
        $attribute = Attribute::where('name', $specName)->first();
        if (!$attribute) {
            continue;
        }

        $convertedSpecValues = [];
        foreach ($specValues as $specValue) {
            $conversionResult = $convertAttributeValue($specName, $specValue);
            $convertedSpecValues[] = $conversionResult['original_value'];
        }

        $matchingProductIds = DB::table('product_attributes as pa')
            ->where('pa.attribute_id', $attribute->id)
            ->whereIn('pa.attribute_value', $convertedSpecValues)
            ->whereIn('pa.product_id', $filteredProductIds)
            ->pluck('pa.product_id')
            ->unique();

        $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

        if ($filteredProductIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'filters' => [],
                'products' => [],
                'brands' => [],
                'price_min' => 0,
                'price_max' => 0,
                'rating_filter' => [
                    'filter_name' => 'Rating',
                    'filter_type' => 'rating',
                    'filter_values' => [5, 4, 3, 2, 1],
                ]
            ]);
        }
    }

    // Apply range filters
    foreach ($rangeFiltersByAttribute as $specName => $ranges) {
        $attribute = Attribute::where('name', $specName)->first();
        if (!$attribute) {
            continue;
        }

        $query = DB::table('product_attributes as pa')
            ->where('pa.attribute_id', $attribute->id)
            ->whereIn('pa.product_id', $filteredProductIds);

        $rangeConditions = [];
        foreach ($ranges as $range) {
            $min = (int)$range['min'];
            $max = (int)$range['max'];

            $minConversion = $convertAttributeValue($specName, $min);
            $maxConversion = $convertAttributeValue($specName, $max);
            
            if ($minConversion['conversion_applied']) {
                $convertedMin = (int)round((float)$minConversion['converted_value']);
                $convertedMax = (int)round((float)$maxConversion['converted_value']);
                
                $rangeConditions[] = "pa.product_id IN (
                    SELECT DISTINCT pa2.product_id 
                    FROM product_attributes pa2 
                    WHERE pa2.attribute_id = {$attribute->id}
                    AND (
                        (pa2.attribute_value REGEXP '^[0-9]+\.?[0-9]*$' AND CAST(pa2.attribute_value AS DECIMAL(10,2)) BETWEEN {$convertedMin} AND {$convertedMax})
                        OR
                        (pa2.attribute_value REGEXP '^[0-9]+\.?[0-9]*[[:space:]]*[a-zA-Z]+$' AND 
                         CAST(REGEXP_REPLACE(pa2.attribute_value, '[^0-9.].*', '') AS DECIMAL(10,2)) BETWEEN {$convertedMin} AND {$convertedMax})
                    )
                )";
            } else {
                $rangeConditions[] = "(
                    (pa.attribute_value REGEXP '^[0-9]+\.?[0-9]*$' AND CAST(pa.attribute_value AS DECIMAL(10,2)) BETWEEN {$min} AND {$max})
                    OR
                    (pa.attribute_value REGEXP '^[0-9]+\.?[0-9]*[[:space:]]*[a-zA-Z]+$' AND 
                     CAST(REGEXP_REPLACE(pa.attribute_value, '[^0-9.].*', '') AS DECIMAL(10,2)) BETWEEN {$min} AND {$max})
                )";
            }
        }

        if (count($rangeConditions) > 0) {
            $query->whereRaw('(' . implode(' OR ', $rangeConditions) . ')');
        }

        $matchingProductIds = $query->pluck('pa.product_id')->unique();
        $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

        if ($filteredProductIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'filters' => [],
                'products' => [],
                'price_min' => 0,
                'price_max' => 0,
                'brands' => [],
                'rating_filter' => [
                    'filter_name' => 'Rating',
                    'filter_type' => 'rating',
                    'filter_values' => [5, 4, 3, 2, 1],
                ]
            ]);
        }
    }

    // Apply brand filter
    if ($request->has('brand_id') && $request->brand_id) {
        $brandFilteredIds = Product::whereIn('id', $filteredProductIds)
            ->whereIn('brand_id', $request->brand_id)
            ->pluck('id');

        $filteredProductIds = $filteredProductIds->intersect($brandFilteredIds);

        if ($filteredProductIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'filters' => [],
                'products' => [],
                'brands' => [],
                'price_min' => 0,
                'price_max' => 0,
                'rating_filter' => [
                    'filter_name' => 'Rating',
                    'filter_type' => 'rating',
                    'filter_values' => [5, 4, 3, 2, 1],
                ]
            ]);
        }
    }

    // Apply price filter
    if ($request->has('price_min') || $request->has('price_max')) {
        $min = $request->input('price_min', 0);
        $max = $request->input('price_max', PHP_INT_MAX);

        $priceFilteredIds = DB::table('product_suppliers as ps')
            ->whereIn('ps.product_id', $filteredProductIds->toArray())
            ->where(function($query) use ($min, $max) {
                $query->whereRaw("CASE WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 THEN ps.sale_price ELSE ps.price END BETWEEN ? AND ?", [$min, $max]);
            })
            ->pluck('ps.product_id')
            ->unique();

        if ($priceFilteredIds->isEmpty()) {
            $priceFilteredIds = DB::table('product_suppliers as ps')
                ->whereIn('ps.product_id', $filteredProductIds->toArray())
                ->whereRaw("COALESCE(ps.sale_price, ps.price) BETWEEN ? AND ?", [$min, $max])
                ->pluck('ps.product_id')
                ->unique();
            
            if ($priceFilteredIds->isEmpty()) {
                $priceFilteredIds = DB::table('ec_products as p')
                    ->whereIn('p.id', $filteredProductIds->toArray())
                    ->whereRaw("COALESCE(p.sale_price, p.price) BETWEEN ? AND ?", [$min, $max])
                    ->pluck('p.id')
                    ->unique();
            }
        }

        $filteredProductIds = $filteredProductIds->intersect($priceFilteredIds);

        if ($filteredProductIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'filters' => [],
                'products' => [],
                'brands' => [],
                'price_min' => 0,
                'price_max' => 0,
                'rating_filter' => [
                    'filter_name' => 'Rating',
                    'filter_type' => 'rating',
                    'filter_values' => [5, 4, 3, 2, 1],
                ]
            ]);
        }
    }

    // Apply rating filter
    if ($request->has('rating') && $request->rating) {
        $ratingFilteredIds = DB::table('ec_reviews')
            ->whereIn('product_id', $filteredProductIds)
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('ROUND(AVG(star)) = ?', [$request->rating])
            ->pluck('product_id');

        $filteredProductIds = $filteredProductIds->intersect($ratingFilteredIds);

        if ($filteredProductIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No products found with ' . $request->rating . ' star' . ($request->rating == 1 ? '' : 's') . ' rating.',
                'filters' => [],
                'products' => [],
                'brands' => [],
                'price_min' => 0,
                'price_max' => 0,
                'rating_filter' => [
                    'filter_name' => 'Rating',
                    'filter_type' => 'rating',
                    'filter_values' => [5, 4, 3, 2, 1],
                ]
            ]);
        }
    }

    // Fetch products
    $products = Product::whereIn('id', $filteredProductIds)
        ->where('status', 'published')
        ->with(['currency', 'reviews', 'productSuppliers', 'brand', 'seoUrl', 'productAttributes' => function ($query) {
            $query->whereHas('attributeDetails', function ($q) {
                $q->whereIn('name', ['Units per Case', 'Pack Type']);
            });
        }]);

    // Apply sorting
    $sortBy = $request->input('sort_by', 'created_at');
    $sortByType = $request->input('sort_by_type', 'desc');

    if ($request->has('price_order')) {
        $priceOrder = $request->input('price_order');
        $sortByType = $priceOrder === 'high_to_low' ? 'desc' : 'asc';
        $sortBy = 'price';
    }

    if ($sortBy == 'price') {
        $productIds = $filteredProductIds->toArray();
        
        $products = Product::with(['currency', 'reviews', 'productSuppliers', 'brand', 'productAttributes' => function ($query) {
                $query->whereHas('attributeDetails', function ($q) {
                    $q->whereIn('name', ['Units per Case', 'Pack Type']);
                });
            }])
            ->leftJoin('product_suppliers as ps', 'ec_products.id', '=', 'ps.product_id')
            ->select('ec_products.*',
                DB::raw('MIN(CASE 
            WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 
            THEN ps.sale_price 
            ELSE ps.price 
        END) as best_price')
    )
    ->whereIn('ec_products.id', $productIds)
    ->where('ec_products.status', 'published')
    ->groupBy('ec_products.id')
    ->orderBy('best_price', $sortByType);
    } else {
        $orderColumn = in_array($sortBy, ['created_at', 'updated_at', 'name', 'status']) 
            ? "ec_products.{$sortBy}" 
            : $sortBy;
        
        $products = $products->orderBy($orderColumn, $sortByType);
    }
        
    $paginatedProducts = $products->paginate($perPage);

    // Get wishlist product IDs
    $wishlistProductIds = auth()->check() ?
        \App\Models\Wishlist::where('user_id', auth()->id())->pluck('product_id')->toArray() :
        [];

    $modifiedProducts = $paginatedProducts->getCollection()->map(function ($product) use ($wishlistProductIds) {
        $totalReviews = $product->reviews->count();
        $avgRating = $totalReviews > 0 ? round($product->reviews->avg('star')) : null;

        $cleanedImages = is_string($product->images)
            ? json_decode($product->images, true)
            : (array) $product->images;

        $firstSupplier = $product->productSuppliers->first();
        $leftStock = $firstSupplier->quantity ?? 0;

        $sellingType = null;
        if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
            $fullValue = $product->sellingUnitAttribute->attribute_value;

            $attributeUnit = strpos($fullValue, '/') !== false
                ? trim(explode('/', $fullValue)[1])
                : $fullValue;

            $sellingType = [
                'attribute_value' => $product->sellingUnitAttribute->attribute_value,
                'attribute_value_unit' => $attributeUnit,
            ];
        }

        $unitsPerCase = null;
        $packType = null;

        if (!empty($product->productAttributes)) {
            $unitsPerCase = $product->productAttributes
                ->first(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
            $packType = $product->productAttributes
                ->first(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');
        }

        $basePrice = null;
        if ($firstSupplier) {
            $basePrice = ($firstSupplier->sale_price > 0) ? $firstSupplier->sale_price : $firstSupplier->price;
        }
        $perUnitPrice = null;

        if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
            $unitValue = (float) $unitsPerCase->attribute_value;
            if ($unitValue > 0) {
                $calculated = round($basePrice / $unitValue, 2);
                $perUnitPrice = $calculated . ' ' . '/' . ($packType?->attribute_value ?? '');
            }
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'images' => $cleanedImages,
            'url' => $product->seoUrl->url ?? null,
            'video_url' => $product->video_url,
            'video_path' => is_array($product->video_path) ? $product->video_path : (json_decode($product->video_path, true) ?: []),
            'sku' => $product->sku,
            'start_date' => $product->start_date,
            'end_date' => $product->end_date,
            'currency' => $product->currency?->symbol,
            'total_reviews' => $totalReviews,
            'avg_rating' => $avgRating,
            'leftStock' => $leftStock,
            'currency_title' => $product->currency
                ? ($product->currency->is_prefix_symbol
                    ? $product->currency->symbol
                    : ($product->price . ' ' . $product->currency->symbol))
                : $product->price,
            'in_wishlist' => in_array($product->id, $wishlistProductIds),
            'selling_type' => $sellingType,
            'per_unit_price' => $perUnitPrice,
            'vendor_sku' => $firstSupplier?->vendor_sku ?? null,
            'price' => (float) ($firstSupplier?->price ?? 0),
            'sale_price' => (float) ($firstSupplier?->sale_price ?? 0),
            'original_price' => (float) ($firstSupplier?->price ?? 0),
            'front_sale_price' => (float) ($firstSupplier?->sale_price ?? $firstSupplier?->price ?? 0),
            'best_price' => (float) ($firstSupplier?->price ?? 0),
            'vendor_id' => $firstSupplier?->vendor_id ?? null,
            'map' => $firstSupplier ? (float) $firstSupplier->map : null,
            'inventory' => $firstSupplier?->inventory ?? null,
            'in_stock' => $firstSupplier?->in_stock ?? null,
            'delivery_days' => $firstSupplier?->delivery_days ?? null,
            'return_policy' => $firstSupplier?->return_policy ?? null,
            'free_shipping' => $firstSupplier?->free_shipping ?? null,
            'warranty_information' => $firstSupplier?->warranty_information ?? null,
        ];
    });

    $paginatedProducts->setCollection($modifiedProducts);

    // Build filters
    $filters = [];

    $subCategory = DB::table('sub_categories')
        ->where('category_id', $request->category_id)
        ->first();

    if ($subCategory) {
        $attributeIdsField = null;
        $attributeIds = [];

        if (property_exists($subCategory, 'attributes_ids') || isset($subCategory->attributes_ids)) {
            $attributeIdsField = 'attributes_ids';
        } else if (property_exists($subCategory, 'attributes_jd') || isset($subCategory->attributes_jd)) {
            $attributeIdsField = 'attributes_jd';
        }

        if ($attributeIdsField && !empty($subCategory->$attributeIdsField)) {
            $attributeIdsValue = $subCategory->$attributeIdsField;

            if (is_string($attributeIdsValue)) {
                $attributeIds = json_decode($attributeIdsValue, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $attributeIds = explode(',', $attributeIdsValue);
                } else if (count($attributeIds) === 1 && is_string($attributeIds[0]) && strpos($attributeIds[0], ',') !== false) {
                    $attributeIds = explode(',', $attributeIds[0]);
                }
            } else {
                $attributeIds = $attributeIdsValue;
            }

            $attributeIds = array_map('intval', (array)$attributeIds);

            if (!empty($attributeIds)) {
                foreach ($attributeIds as $attributeId) {
                    $attribute = Attribute::find($attributeId);
                    if (!$attribute) {
                        continue;
                    }

                    $attributeName = $attribute->name;
                    $isFilterSelected = isset($selectedFilters[$attributeName]);

                    $productIdsToUse = $isFilterSelected ? $allCategoryProductIds : $filteredProductIds;

                    $attributeValues = DB::table('product_attributes as pa')
                        ->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
                        ->whereIn('pa.product_id', $productIdsToUse)
                        ->where('pa.attribute_id', $attributeId)
                        ->orderBy('pa.attribute_value', 'asc')
                        ->select('at.name as attribute_name', 'pa.attribute_value', 'at.id as attribute_id', 'pa.product_id')
                        ->get();

                    if ($attributeValues->count() > 0) {
                        $convertedAttributeValues = $attributeValues->map(function($item) use ($convertAttributeValue, $attributeName) {
                            $conversionResult = $convertAttributeValue($attributeName, $item->attribute_value);
                            return (object)[
                                'attribute_name' => $item->attribute_name,
                                'attribute_value' => $item->attribute_value,
                                'converted_value' => $conversionResult['converted_value'],
                                'display_value' => $conversionResult['display_value'],
                                'unit' => $conversionResult['unit'],
                                'symbol' => $conversionResult['symbol'],
                                'conversion_applied' => $conversionResult['conversion_applied'],
                                'attribute_id' => $item->attribute_id,
                                'product_id' => $item->product_id
                            ];
                        });

                        $uniqueValues = $convertedAttributeValues->pluck('display_value')->unique()->filter()->values();

                        $extractNumericValue = function($value) {
                            if (preg_match('/^(\d+(?:\.\d+)?)\s*[a-zA-Z]*$/', $value, $matches)) {
                                return (int)round((float)$matches[1]);
                            } else if (is_numeric($value)) {
                                return (int)round((float)$value);
                            }
                            return $value;
                        };

                        $numericValues = true;
                        $cleanedValues = $uniqueValues->map(function($val) use ($extractNumericValue, &$numericValues) {
                            $cleanedVal = $extractNumericValue($val);
                            if (!is_numeric($cleanedVal)) {
                                $numericValues = false;
                            }
                            return $cleanedVal;
                        });

                        if ($numericValues && $cleanedValues->count() > 2) {
                            $sorted = $cleanedValues->filter(function($value) {
                                return is_numeric($value);
                            })->map(function($val) {
                                return (int)$val;
                            })->unique()->sort()->values();

                            $chunkCount = min(5, ceil($sorted->count() / 2));
                            $chunkSize = ceil($sorted->count() / $chunkCount);

                            $selectedRanges = isset($selectedFilters[$attributeName]) ? $selectedFilters[$attributeName] : [];

                            $ranges = $sorted->chunk($chunkSize)->map(function ($chunk) use ($attributeName, $filteredProductIds, $isFilterSelected, $convertedAttributeValues) {
                                $min = (int)$chunk->first();
                                $max = (int)$chunk->last();

                                $matchingConvertedValues = $convertedAttributeValues->filter(function($item) use ($min, $max) {
                                    $numericValue = is_numeric($item->converted_value) ? (int)round((float)$item->converted_value) : null;
                                    return $numericValue !== null && $numericValue >= $min && $numericValue <= $max;
                                });

                                $productCount = $matchingConvertedValues->whereIn('product_id', $filteredProductIds)->pluck('product_id')->unique()->count();

                                $sampleConvertedValue = $matchingConvertedValues->first();
                                $unit = $sampleConvertedValue ? $sampleConvertedValue->symbol : '';

                                $displayValue = $min == $max ? $min . ' ' . $unit : $min . ' - ' . $max . ' ' . $unit;

                                return [
                                    'min' => $min,
                                    'max' => $max,
                                    'product_count' => $productCount,
                                    'display_value' => $displayValue,
                                    'unit' => $unit
                                ];
                            })->filter(function($range) use ($isFilterSelected) {
                                return $isFilterSelected || $range['product_count'] > 0;
                            })->sortBy('min')->values()->toArray();

                            foreach ($selectedRanges as $selectedRange) {
                                if (is_array($selectedRange) && isset($selectedRange['min']) && isset($selectedRange['max'])) {
                                    $selectedMin = (int)$selectedRange['min'];
                                    $selectedMax = (int)$selectedRange['max'];

                                    $rangeExists = false;
                                    foreach ($ranges as $range) {
                                        if ($range['min'] == $selectedMin && $range['max'] == $selectedMax) {
                                            $rangeExists = true;
                                            break;
                                        }
                                    }

                                    if (!$rangeExists) {
                                        $matchingConvertedValues = $convertedAttributeValues->filter(function($item) use ($selectedMin, $selectedMax) {
                                            $numericValue = is_numeric($item->converted_value) ? (int)round((float)$item->converted_value) : null;
                                            return $numericValue !== null && $numericValue >= $selectedMin && $numericValue <= $selectedMax;
                                        });

                                        $productCount = $matchingConvertedValues->whereIn('product_id', $filteredProductIds)->pluck('product_id')->unique()->count();

                                        $sampleConvertedValue = $matchingConvertedValues->first();
                                        $unit = $sampleConvertedValue ? $sampleConvertedValue->symbol : '';

                                        $displayValue = $selectedMin == $selectedMax ? $selectedMin . ' ' . $unit : $selectedMin . ' - ' . $selectedMax . ' ' . $unit;

                                        $ranges[] = [
                                            'min' => $selectedMin,
                                            'max' => $selectedMax,
                                            'product_count' => $productCount,
                                            'display_value' => $displayValue,
                                            'selected' => true,
                                            'unit' => $unit
                                        ];
                                    }
                                }
                            }

                            usort($ranges, function($a, $b) {
                                return $a['min'] - $b['min'];
                            });

                            if (!empty($ranges)) {
                                $filters[] = [
                                    'specification_name' => $attributeName,
                                    'specification_type' => 'range',
                                    'specification_value' => $ranges,
                                ];
                            }
                        } else {
                            $valueCountMap = [];
                            $selectedValues = isset($selectedFilters[$attributeName]) ? $selectedFilters[$attributeName] : [];

                            foreach ($uniqueValues as $displayValue) {
                                $correspondingItem = $convertedAttributeValues->firstWhere('display_value', $displayValue);
                                
                                if (!$correspondingItem) continue;

                                $productCount = $convertedAttributeValues
                                    ->where('display_value', $displayValue)
                                    ->whereIn('product_id', $filteredProductIds)
                                    ->pluck('product_id')
                                    ->unique()
                                    ->count();

                                if ($isFilterSelected || $productCount > 0) {
                                    $valueCountMap[] = [
                                        'value' => $correspondingItem->attribute_value,
                                        'display_value' => $displayValue,
                                        'converted_value' => $correspondingItem->converted_value,
                                        'unit' => $correspondingItem->unit,
                                        'symbol' => $correspondingItem->symbol,
                                        'product_count' => $productCount,
                                        'display_with_count' => $displayValue . ' (' . $productCount . ')',
                                        'conversion_applied' => $correspondingItem->conversion_applied
                                    ];
                                }
                            }

                            foreach ($selectedValues as $selectedValue) {
                                $valueExists = false;
                                foreach ($valueCountMap as $valueCount) {
                                    if ($valueCount['value'] == $selectedValue) {
                                        $valueExists = true;
                                        break;
                                    }
                                }

                                if (!$valueExists) {
                                    $conversionResult = $convertAttributeValue($attributeName, $selectedValue);
                                    
                                    $productCount = $convertedAttributeValues
                                        ->where('attribute_value', $selectedValue)
                                        ->whereIn('product_id', $filteredProductIds)
                                        ->pluck('product_id')
                                        ->unique()
                                        ->count();

                                    $valueCountMap[] = [
                                        'value' => $selectedValue,
                                        'display_value' => $conversionResult['display_value'],
                                        'converted_value' => $conversionResult['converted_value'],
                                        'unit' => $conversionResult['unit'],
                                        'symbol' => $conversionResult['symbol'],
                                        'product_count' => $productCount,
                                        'display_with_count' => $conversionResult['display_value'] . ' (' . $productCount . ')',
                                        'selected' => true,
                                        'conversion_applied' => $conversionResult['conversion_applied']
                                    ];
                                }
                            }

                            usort($valueCountMap, function($a, $b) {
                                if (is_numeric($a['converted_value']) && is_numeric($b['converted_value'])) {
                                    return (int)round((float)$a['converted_value']) - (int)round((float)$b['converted_value']);
                                }
                                return strcmp($a['display_value'], $b['display_value']);
                            });

                            if (!empty($valueCountMap)) {
                                $filters[] = [
                                    'specification_name' => $attributeName,
                                    'specification_type' => 'fixed',
                                    'specification_value' => $valueCountMap,
                                ];
                            }
                        }
                    }
                }
            }
        }
    }

    // Get brands
    $selectedBrandIds = $request->brand_id ?? [];

    $brands = DB::table('ec_products as p')
        ->join('ec_brands as b', 'b.id', '=', 'p.brand_id')
        ->whereIn('p.id', $allCategoryProductIds)
        ->where('p.status', 'published')
        ->select('b.id', 'b.name')
        ->groupBy('b.id', 'b.name')
        ->orderBy('b.name')
        ->get()
        ->map(function($brand) use ($filteredProductIds, $selectedBrandIds) {
            $productCount = DB::table('ec_products')
            ->where('brand_id', $brand->id)
            ->whereIn('id', $filteredProductIds->toArray())
            ->where('status', 'published')
            ->count();
            
            $isSelected = in_array($brand->id, $selectedBrandIds);
            
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'product_count' => $productCount,
                'display_name' => $brand->name . ' (' . $productCount . ')',
                'is_selected' => $isSelected
            ];
        })
        ->toArray();

    // Get price range
    $productIdsArray = $filteredProductIds->toArray();

    $supplierExists = DB::table('product_suppliers')
        ->whereIn('product_id', $productIdsArray)
        ->exists();

    if ($supplierExists) {
        $priceRange = DB::table('product_suppliers')
            ->whereIn('product_id', $productIdsArray)
            ->where(function($query) {
                $query->where('price', '>', 0)
                    ->orWhere('sale_price', '>', 0);
            })
            ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
            ->first();

        if (!$priceRange || ($priceRange->min_price <= 0 && $priceRange->max_price <= 0)) {
            $priceRange = DB::table('product_suppliers')
                ->whereIn('product_id', $productIdsArray)
                ->selectRaw('MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as min_price, 
                            MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as max_price')
                ->first();
        }
    } else {
        $priceRange = DB::table('ec_products')
            ->whereIn('id', $filteredProductIds)
            ->where('status', 'published')
            ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
            ->first();
    }

    $priceMin = $priceRange ? (float)$priceRange->min_price : 0;
    $priceMax = $priceRange ? (float)$priceRange->max_price : 0;

    // Rating filter
    $ratingFilter = [
        'filter_name' => 'Rating',
        'filter_type' => 'rating',
        'filter_values' => [5, 4, 3, 2, 1],
    ];

    return response()->json([
        'success' => true,
        'filters' => $filters,
        'products' => $paginatedProducts,
        'brands' => $brands,
        'price_min' => $priceMin,
        'price_max' => $priceMax,
        'rating_filter' => $ratingFilter,
        'category_measurement_priorities' => $categoryMeasurementPriorities->toArray()
    ]);
}
// public function getSpecificationFilters1(Request $request)
// {
//     // Validation
//     $validator = Validator::make($request->all(), [
//         'category_id' => 'required|string',
//         'filters' => 'nullable|array',
//         'price_min' => 'nullable|numeric|min:0',
//         'price_max' => 'nullable|numeric|min:0',
//         'price_order' => 'nullable|in:high_to_low,low_to_high',
//         'brand_id' => 'nullable|array',
//         'brand_id.*' => 'integer',
//         'rating' => 'nullable|numeric|min:1|max:5',
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['success' => false, 'message' => $validator->errors()], 400);
//     }

//     $perPage = $request->get('per_page', 10);
//     $categoryIdentifier = $request->input('category_id');
//     $category = null;

//     if (is_numeric($categoryIdentifier)) {
//         $category = Category::find($categoryIdentifier);
//     } else {
//         $category = Category::where('slug', $categoryIdentifier)->first();
//     }

//     if (!$category) {
//         return response()->json(['success' => false, 'message' => 'Category does not exist.'], 400);
//     }

//     // Get category measurement unit priorities
//     $categoryMeasurementPriorities = DB::table('category_measurement_unit_priorities as cmup')
//         ->join('measurement_types as mt', 'mt.id', '=', 'cmup.measurement_type_id')
//         ->join('measurement_units as mu_primary', 'mu_primary.id', '=', 'cmup.measurement_unit_primary_id')
//         ->where('cmup.category_id', $category->id)
//         ->select('mt.name as measurement_type', 'mu_primary.name as primary_unit', 'mu_primary.symbol as primary_symbol')
//         ->get()
//         ->keyBy('measurement_type');

//     // Enhanced helper function to convert attribute values with fallback units
//     $convertAttributeValue = function($attributeName, $originalValue) use ($categoryMeasurementPriorities) {
//         // First try the database-configured measurement priorities
//         foreach ($categoryMeasurementPriorities as $measurementType => $priority) {
//             $shouldConvert = false;
            
//             switch (strtolower($measurementType)) {
//                 case 'length':
//                     $shouldConvert = (
//                         stripos($attributeName, 'length') !== false ||
//                         stripos($attributeName, 'height') !== false ||
//                         stripos($attributeName, 'width') !== false ||
//                         stripos($attributeName, 'depth') !== false ||
//                         stripos($attributeName, 'diameter') !== false ||
//                         stripos($attributeName, 'dimension') !== false ||
//                         stripos($attributeName, 'size') !== false
//                     );
//                     break;
//                 case 'mass':
//                 case 'weight':
//                     $shouldConvert = (
//                         stripos($attributeName, 'weight') !== false ||
//                         stripos($attributeName, 'mass') !== false
//                     );
//                     break;
//                 case 'volume':
//                     $shouldConvert = (
//                         stripos($attributeName, 'volume') !== false ||
//                         stripos($attributeName, 'capacity') !== false
//                     );
//                     break;
//                 default:
//                     $shouldConvert = stripos($attributeName, $measurementType) !== false;
//                     break;
//             }
            
//             if ($shouldConvert) {
//                 if (preg_match('/^(\d+(?:\.\d+)?)\s*([a-zA-Z]+)$/', trim($originalValue), $matches)) {
//                     $numericValue = (float)$matches[1];
//                     $originalUnit = $matches[2];
//                     $targetUnit = $priority->primary_unit;
                    
//                     $convertedValue = convert_unit($measurementType, $numericValue, $originalUnit, $targetUnit);
                    
//                     if (is_numeric($convertedValue)) {
//                         $roundedValue = (int)round($convertedValue);
//                         return [
//                             'converted_value' => $roundedValue,
//                             'unit' => $targetUnit,
//                             'symbol' => $priority->primary_symbol,
//                             'display_value' => $roundedValue . ' ' . $priority->primary_symbol,
//                             'original_value' => $originalValue,
//                             'conversion_applied' => true
//                         ];
//                     }
//                 } else if (is_numeric($originalValue)) {
//                     $roundedValue = (int)round((float)$originalValue);
//                     return [
//                         'converted_value' => $roundedValue,
//                         'unit' => $priority->primary_unit,
//                         'symbol' => $priority->primary_symbol,
//                         'display_value' => $roundedValue . ' ' . $priority->primary_symbol,
//                         'original_value' => $originalValue,
//                         'conversion_applied' => false
//                     ];
//                 }
//             }
//         }
        
//         // Fallback: Assign common units based on attribute names if no database config found
//         $fallbackUnits = [
//             'width' => ['symbol' => 'cm', 'name' => 'centimeters'],
//             'length' => ['symbol' => 'cm', 'name' => 'centimeters'], 
//             'height' => ['symbol' => 'cm', 'name' => 'centimeters'],
//             'depth' => ['symbol' => 'cm', 'name' => 'centimeters'],
//             'diameter' => ['symbol' => 'cm', 'name' => 'centimeters'],
//             'weight' => ['symbol' => 'kg', 'name' => 'kilograms'],
//             'capacity' => ['symbol' => 'L', 'name' => 'liters'],
//             'volume' => ['symbol' => 'L', 'name' => 'liters'],
//         ];
        
//         foreach ($fallbackUnits as $unitType => $unitInfo) {
//             if (stripos($attributeName, $unitType) !== false) {
//                 if (is_numeric($originalValue)) {
//                     $roundedValue = (int)round((float)$originalValue);
//                     return [
//                         'converted_value' => $roundedValue,
//                         'unit' => $unitInfo['name'],
//                         'symbol' => $unitInfo['symbol'],
//                         'display_value' => $roundedValue . ' ' . $unitInfo['symbol'],
//                         'original_value' => $originalValue,
//                         'conversion_applied' => false
//                     ];
//                 }
//             }
//         }
        
//         // Return original value if no conversion needed/possible
//         return [
//             'converted_value' => $originalValue,
//             'unit' => null,
//             'symbol' => '',
//             'display_value' => $originalValue,
//             'original_value' => $originalValue,
//             'conversion_applied' => false
//         ];
//     };

//     // Get products from current category
//     $currentCategoryProducts = $category->products()->where('status', 'published')->pluck('id')->all();
    
//     // Get all child categories
//     $childCategories = Category::where('parent_id', $category->id)->get();
//     $childCategoryIds = $childCategories->pluck('id')->toArray();

//     // Get all products from child categories
//     $childProductIds = [];
//     if (!empty($childCategoryIds)) {
//         foreach ($childCategories as $childCategory) {
//             $childProductIds = array_merge($childProductIds, $childCategory->products()->where('status', 'published')->pluck('id')->all());
//         }
//     }

//     // Combine products from current category and all child categories
//     $allCategoryProductIds = array_unique(array_merge($currentCategoryProducts, $childProductIds));

//     if (empty($allCategoryProductIds)) {
//         return response()->json([
//             'success' => true,
//             'filters' => [],
//             'products' => [],
//             'brands' => [],
//             'price_min' => 0,
//             'price_max' => 0,
//             'rating_filter' => [
//                 'filter_name' => 'Rating',
//                 'filter_type' => 'rating',
//                 'filter_values' => [5, 4, 3, 2, 1],
//             ]
//         ]);
//     }

//     // Start with all category product IDs
//     $filteredProductIds = collect($allCategoryProductIds);

//     // Group filters by specification name
//     $groupedFilters = [];
//     $rangeFiltersByAttribute = [];
//     $selectedFilters = [];

//     if ($request->has('filters') && is_array($request->filters)) {
//         foreach ($request->filters as $filter) {
//             if (!isset($filter['specification_name']) || !isset($filter['specification_value']) || empty($filter['specification_value'])) {
//                 continue;
//             }

//             $specName = $filter['specification_name'];
//             $specValues = is_array($filter['specification_value']) ? $filter['specification_value'] : [$filter['specification_value']];

//             $selectedFilters[$specName] = $specValues;

//             $isRangeFilter = false;
            
//             // Handle single range filter format (start/end)
//             if (is_array($filter['specification_value']) && 
//                 isset($filter['specification_value']['start']) && 
//                 isset($filter['specification_value']['end'])) {
                
//                 $isRangeFilter = true;
//                 if (!isset($rangeFiltersByAttribute[$specName])) {
//                     $rangeFiltersByAttribute[$specName] = [];
//                 }
//                 $rangeFiltersByAttribute[$specName][] = [
//                     'min' => (int)$filter['specification_value']['start'],
//                     'max' => (int)$filter['specification_value']['end']
//                 ];
                
//                 // Update selected filters for proper display
//                 $selectedFilters[$specName] = [[
//                     'min' => (int)$filter['specification_value']['start'],
//                     'max' => (int)$filter['specification_value']['end']
//                 ]];
//             } else {
//                 // Handle array of range filters (min/max format)
//                 foreach ($specValues as $value) {
//                     if (is_array($value)) {
//                         // Check for min/max format
//                         if (isset($value['min']) && isset($value['max'])) {
//                             $isRangeFilter = true;
//                             if (!isset($rangeFiltersByAttribute[$specName])) {
//                                 $rangeFiltersByAttribute[$specName] = [];
//                             }
//                             $rangeFiltersByAttribute[$specName][] = [
//                                 'min' => (int)$value['min'],
//                                 'max' => (int)$value['max']
//                             ];
//                         }
//                         // Check for start/end format in array
//                         else if (isset($value['start']) && isset($value['end'])) {
//                             $isRangeFilter = true;
//                             if (!isset($rangeFiltersByAttribute[$specName])) {
//                                 $rangeFiltersByAttribute[$specName] = [];
//                             }
//                             $rangeFiltersByAttribute[$specName][] = [
//                                 'min' => (int)$value['start'],
//                                 'max' => (int)$value['end']
//                             ];
//                         }
//                     }
//                 }
//             }

//             if (!$isRangeFilter) {
//                 if (!isset($groupedFilters[$specName])) {
//                     $groupedFilters[$specName] = [];
//                 }
//                 $groupedFilters[$specName] = array_merge($groupedFilters[$specName], $specValues);
//             }
//         }
//     }

//     // Apply regular attribute filters
//     foreach ($groupedFilters as $specName => $specValues) {
//         $attribute = Attribute::where('name', $specName)->first();
//         if (!$attribute) {
//             continue;
//         }

//         $convertedSpecValues = [];
//         foreach ($specValues as $specValue) {
//             $conversionResult = $convertAttributeValue($specName, $specValue);
//             $convertedSpecValues[] = $conversionResult['original_value'];
//         }

//         $matchingProductIds = DB::table('product_attributes as pa')
//             ->where('pa.attribute_id', $attribute->id)
//             ->whereIn('pa.attribute_value', $convertedSpecValues)
//             ->whereIn('pa.product_id', $filteredProductIds)
//             ->pluck('pa.product_id')
//             ->unique();

//         $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

//         if ($filteredProductIds->isEmpty()) {
//             return response()->json([
//                 'success' => true,
//                 'filters' => [],
//                 'products' => [],
//                 'brands' => [],
//                 'price_min' => 0,
//                 'price_max' => 0,
//                 'rating_filter' => [
//                     'filter_name' => 'Rating',
//                     'filter_type' => 'rating',
//                     'filter_values' => [5, 4, 3, 2, 1],
//                 ]
//             ]);
//         }
//     }

//     // Apply range filters
//     foreach ($rangeFiltersByAttribute as $specName => $ranges) {
//         $attribute = Attribute::where('name', $specName)->first();
//         if (!$attribute) {
//             continue;
//         }

//         $query = DB::table('product_attributes as pa')
//             ->where('pa.attribute_id', $attribute->id)
//             ->whereIn('pa.product_id', $filteredProductIds);

//         $rangeConditions = [];
//         foreach ($ranges as $range) {
//             $min = (int)$range['min'];
//             $max = (int)$range['max'];

//             $minConversion = $convertAttributeValue($specName, $min);
//             $maxConversion = $convertAttributeValue($specName, $max);
            
//             if ($minConversion['conversion_applied']) {
//                 $convertedMin = (int)round((float)$minConversion['converted_value']);
//                 $convertedMax = (int)round((float)$maxConversion['converted_value']);
                
//                 $rangeConditions[] = "pa.product_id IN (
//                     SELECT DISTINCT pa2.product_id 
//                     FROM product_attributes pa2 
//                     WHERE pa2.attribute_id = {$attribute->id}
//                     AND (
//                         (pa2.attribute_value REGEXP '^[0-9]+\.?[0-9]*$' AND CAST(pa2.attribute_value AS DECIMAL(10,2)) BETWEEN {$convertedMin} AND {$convertedMax})
//                         OR
//                         (pa2.attribute_value REGEXP '^[0-9]+\.?[0-9]*[[:space:]]*[a-zA-Z]+$' AND 
//                          CAST(REGEXP_REPLACE(pa2.attribute_value, '[^0-9.].*', '') AS DECIMAL(10,2)) BETWEEN {$convertedMin} AND {$convertedMax})
//                     )
//                 )";
//             } else {
//                 $rangeConditions[] = "(
//                     (pa.attribute_value REGEXP '^[0-9]+\.?[0-9]*$' AND CAST(pa.attribute_value AS DECIMAL(10,2)) BETWEEN {$min} AND {$max})
//                     OR
//                     (pa.attribute_value REGEXP '^[0-9]+\.?[0-9]*[[:space:]]*[a-zA-Z]+$' AND 
//                      CAST(REGEXP_REPLACE(pa.attribute_value, '[^0-9.].*', '') AS DECIMAL(10,2)) BETWEEN {$min} AND {$max})
//                 )";
//             }
//         }

//         if (count($rangeConditions) > 0) {
//             $query->whereRaw('(' . implode(' OR ', $rangeConditions) . ')');
//         }

//         $matchingProductIds = $query->pluck('pa.product_id')->unique();
//         $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

//         if ($filteredProductIds->isEmpty()) {
//             return response()->json([
//                 'success' => true,
//                 'filters' => [],
//                 'products' => [],
//                 'price_min' => 0,
//                 'price_max' => 0,
//                 'brands' => [],
//                 'rating_filter' => [
//                     'filter_name' => 'Rating',
//                     'filter_type' => 'rating',
//                     'filter_values' => [5, 4, 3, 2, 1],
//                 ]
//             ]);
//         }
//     }

//     // Apply brand filter
//     if ($request->has('brand_id') && $request->brand_id) {
//         $brandFilteredIds = Product::whereIn('id', $filteredProductIds)
//             ->whereIn('brand_id', $request->brand_id)
//             ->pluck('id');

//         $filteredProductIds = $filteredProductIds->intersect($brandFilteredIds);

//         if ($filteredProductIds->isEmpty()) {
//             return response()->json([
//                 'success' => true,
//                 'filters' => [],
//                 'products' => [],
//                 'brands' => [],
//                 'price_min' => 0,
//                 'price_max' => 0,
//                 'rating_filter' => [
//                     'filter_name' => 'Rating',
//                     'filter_type' => 'rating',
//                     'filter_values' => [5, 4, 3, 2, 1],
//                 ]
//             ]);
//         }
//     }

//     // Apply price filter
//     if ($request->has('price_min') || $request->has('price_max')) {
//         $min = $request->input('price_min', 0);
//         $max = $request->input('price_max', PHP_INT_MAX);

//         $priceFilteredIds = DB::table('product_suppliers as ps')
//             ->whereIn('ps.product_id', $filteredProductIds->toArray())
//             ->where(function($query) use ($min, $max) {
//                 $query->whereRaw("CASE WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 THEN ps.sale_price ELSE ps.price END BETWEEN ? AND ?", [$min, $max]);
//             })
//             ->pluck('ps.product_id')
//             ->unique();

//         if ($priceFilteredIds->isEmpty()) {
//             $priceFilteredIds = DB::table('product_suppliers as ps')
//                 ->whereIn('ps.product_id', $filteredProductIds->toArray())
//                 ->whereRaw("COALESCE(ps.sale_price, ps.price) BETWEEN ? AND ?", [$min, $max])
//                 ->pluck('ps.product_id')
//                 ->unique();
            
//             if ($priceFilteredIds->isEmpty()) {
//                 $priceFilteredIds = DB::table('ec_products as p')
//                     ->whereIn('p.id', $filteredProductIds->toArray())
//                     ->whereRaw("COALESCE(p.sale_price, p.price) BETWEEN ? AND ?", [$min, $max])
//                     ->pluck('p.id')
//                     ->unique();
//             }
//         }

//         $filteredProductIds = $filteredProductIds->intersect($priceFilteredIds);

//         if ($filteredProductIds->isEmpty()) {
//             return response()->json([
//                 'success' => true,
//                 'filters' => [],
//                 'products' => [],
//                 'brands' => [],
//                 'price_min' => 0,
//                 'price_max' => 0,
//                 'rating_filter' => [
//                     'filter_name' => 'Rating',
//                     'filter_type' => 'rating',
//                     'filter_values' => [5, 4, 3, 2, 1],
//                 ]
//             ]);
//         }
//     }

//     // Apply rating filter
//     if ($request->has('rating') && $request->rating) {
//         $ratingFilteredIds = DB::table('ec_reviews')
//             ->whereIn('product_id', $filteredProductIds)
//             ->select('product_id')
//             ->groupBy('product_id')
//             ->havingRaw('ROUND(AVG(star)) = ?', [$request->rating])
//             ->pluck('product_id');

//         $filteredProductIds = $filteredProductIds->intersect($ratingFilteredIds);

//         if ($filteredProductIds->isEmpty()) {
//             return response()->json([
//                 'success' => true,
//                 'message' => 'No products found with ' . $request->rating . ' star' . ($request->rating == 1 ? '' : 's') . ' rating.',
//                 'filters' => [],
//                 'products' => [],
//                 'brands' => [],
//                 'price_min' => 0,
//                 'price_max' => 0,
//                 'rating_filter' => [
//                     'filter_name' => 'Rating',
//                     'filter_type' => 'rating',
//                     'filter_values' => [5, 4, 3, 2, 1],
//                 ]
//             ]);
//         }
//     }

//     // Fetch products
//     $products = Product::whereIn('id', $filteredProductIds)
//         ->where('status', 'published')
//         ->with(['currency', 'reviews', 'productSuppliers', 'brand', 'seoUrl', 'productAttributes' => function ($query) {
//             $query->whereHas('attributeDetails', function ($q) {
//                 $q->whereIn('name', ['Units per Case', 'Pack Type']);
//             });
//         }]);

//     // Apply sorting
//     $sortBy = $request->input('sort_by', 'created_at');
//     $sortByType = $request->input('sort_by_type', 'desc');

//     if ($request->has('price_order')) {
//         $priceOrder = $request->input('price_order');
//         $sortByType = $priceOrder === 'high_to_low' ? 'desc' : 'asc';
//         $sortBy = 'price';
//     }

//     if ($sortBy == 'price') {
//         $productIds = $filteredProductIds->toArray();
        
//         $products = Product::with(['currency', 'reviews', 'productSuppliers', 'brand', 'productAttributes' => function ($query) {
//                 $query->whereHas('attributeDetails', function ($q) {
//                     $q->whereIn('name', ['Units per Case', 'Pack Type']);
//                 });
//             }])
//             ->leftJoin('product_suppliers as ps', 'ec_products.id', '=', 'ps.product_id')
//             ->select('ec_products.*',
//                 DB::raw('MIN(CASE 
//             WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 
//             THEN ps.sale_price 
//             ELSE ps.price 
//         END) as best_price')
//     )
//     ->whereIn('ec_products.id', $productIds)
//     ->where('ec_products.status', 'published')
//     ->groupBy('ec_products.id')
//     ->orderBy('best_price', $sortByType);
//     } else {
//         $orderColumn = in_array($sortBy, ['created_at', 'updated_at', 'name', 'status']) 
//             ? "ec_products.{$sortBy}" 
//             : $sortBy;
        
//         $products = $products->orderBy($orderColumn, $sortByType);
//     }
        
//     $paginatedProducts = $products->paginate($perPage);

//     // Get wishlist product IDs
//     $wishlistProductIds = auth()->check() ?
//         \App\Models\Wishlist::where('user_id', auth()->id())->pluck('product_id')->toArray() :
//         [];

//     $modifiedProducts = $paginatedProducts->getCollection()->map(function ($product) use ($wishlistProductIds) {
//         $totalReviews = $product->reviews->count();
//         $avgRating = $totalReviews > 0 ? round($product->reviews->avg('star')) : null;

//         $cleanedImages = is_string($product->images)
//             ? json_decode($product->images, true)
//             : (array) $product->images;

//         $firstSupplier = $product->productSuppliers->first();
//         $leftStock = $firstSupplier->quantity ?? 0;

//         $sellingType = null;
//         if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
//             $fullValue = $product->sellingUnitAttribute->attribute_value;

//             $attributeUnit = strpos($fullValue, '/') !== false
//                 ? trim(explode('/', $fullValue)[1])
//                 : $fullValue;

//             $sellingType = [
//                 'attribute_value' => $product->sellingUnitAttribute->attribute_value,
//                 'attribute_value_unit' => $attributeUnit,
//             ];
//         }

//         $unitsPerCase = null;
//         $packType = null;

//         if (!empty($product->productAttributes)) {
//             $unitsPerCase = $product->productAttributes
//                 ->first(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
//             $packType = $product->productAttributes
//                 ->first(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');
//         }

//         $basePrice = null;
//         if ($firstSupplier) {
//             $basePrice = ($firstSupplier->sale_price > 0) ? $firstSupplier->sale_price : $firstSupplier->price;
//         }
//         $perUnitPrice = null;

//         if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
//             $unitValue = (float) $unitsPerCase->attribute_value;
//             if ($unitValue > 0) {
//                 $calculated = round($basePrice / $unitValue, 2);
//                 $perUnitPrice = $calculated . ' ' . '/' . ($packType?->attribute_value ?? '');
//             }
//         }

//         return [
//             'id' => $product->id,
//             'name' => $product->name,
//             'images' => $cleanedImages,
//             'url' => $product->seoUrl->url ?? null,
//             'video_url' => $product->video_url,
//             'video_path' => is_array($product->video_path) ? $product->video_path : (json_decode($product->video_path, true) ?: []),
//             'sku' => $product->sku,
//             'start_date' => $product->start_date,
//             'end_date' => $product->end_date,
//             'currency' => $product->currency?->symbol,
//             'total_reviews' => $totalReviews,
//             'avg_rating' => $avgRating,
//             'leftStock' => $leftStock,
//             'currency_title' => $product->currency
//                 ? ($product->currency->is_prefix_symbol
//                     ? $product->currency->symbol
//                     : ($product->price . ' ' . $product->currency->symbol))
//                 : $product->price,
//             'in_wishlist' => in_array($product->id, $wishlistProductIds),
//             'selling_type' => $sellingType,
//             'per_unit_price' => $perUnitPrice,
//             'vendor_sku' => $firstSupplier?->vendor_sku ?? null,
//             'price' => (float) ($firstSupplier?->price ?? 0),
//             'sale_price' => (float) ($firstSupplier?->sale_price ?? 0),
//             'original_price' => (float) ($firstSupplier?->price ?? 0),
//             'front_sale_price' => (float) ($firstSupplier?->sale_price ?? $firstSupplier?->price ?? 0),
//             'best_price' => (float) ($firstSupplier?->price ?? 0),
//             'vendor_id' => $firstSupplier?->vendor_id ?? null,
//             'map' => $firstSupplier ? (float) $firstSupplier->map : null,
//             'inventory' => $firstSupplier?->inventory ?? null,
//             'in_stock' => $firstSupplier?->in_stock ?? null,
//             'delivery_days' => $firstSupplier?->delivery_days ?? null,
//             'return_policy' => $firstSupplier?->return_policy ?? null,
//             'free_shipping' => $firstSupplier?->free_shipping ?? null,
//             'warranty_information' => $firstSupplier?->warranty_information ?? null,
//         ];
//     });

//     $paginatedProducts->setCollection($modifiedProducts);

//     // Build filters - IMPROVED LOGIC
//     $filters = [];
//     $attributeIds = [];

//     // First, try to get attributes from sub_categories table
//     $subCategory = DB::table('sub_categories')
//         ->where('category_id', $request->category_id)
//         ->first();

//     if ($subCategory) {
//         $attributeIdsField = null;

//         if (property_exists($subCategory, 'attributes_ids') || isset($subCategory->attributes_ids)) {
//             $attributeIdsField = 'attributes_ids';
//         } else if (property_exists($subCategory, 'attributes_jd') || isset($subCategory->attributes_jd)) {
//             $attributeIdsField = 'attributes_jd';
//         }

//         if ($attributeIdsField && !empty($subCategory->$attributeIdsField)) {
//             $attributeIdsValue = $subCategory->$attributeIdsField;

//             try {
//                 if (is_string($attributeIdsValue)) {
//                     $decoded = json_decode($attributeIdsValue, true);
//                     if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
//                         $attributeIds = $decoded;
//                     } else {
//                         // Fallback to comma-separated
//                         $attributeIds = array_filter(explode(',', $attributeIdsValue));
//                     }
//                 } else if (is_array($attributeIdsValue)) {
//                     $attributeIds = $attributeIdsValue;
//                 }
                
//                 // Clean and validate attribute IDs
//                 $attributeIds = array_map('intval', array_filter($attributeIds));
//                 $attributeIds = array_filter($attributeIds, function($id) { return $id > 0; });
//             } catch (Exception $e) {
//                 \Log::error("Failed to parse attribute IDs for category: " . $request->category_id, [
//                     'error' => $e->getMessage(),
//                     'value' => $attributeIdsValue
//                 ]);
//                 $attributeIds = [];
//             }
//         }
//     }

//     // FALLBACK: If no attributes found from sub_categories, get all attributes used by products in this category
//     if (empty($attributeIds)) {
//         $attributeIds = DB::table('product_attributes as pa')
//             ->join('attributes as a', 'a.id', '=', 'pa.attribute_id')
//             ->whereIn('pa.product_id', $allCategoryProductIds)
//             ->select('a.id')
//             ->distinct()
//             ->pluck('a.id')
//             ->toArray();

//         // Log fallback usage for debugging
//         \Log::info("Using fallback attribute logic for category: " . $request->category_id, [
//             'found_attributes' => count($attributeIds),
//             'sub_category_exists' => $subCategory ? 'yes' : 'no'
//         ]);
//     }

//     // Build filters if we have attribute IDs
//     if (!empty($attributeIds)) {
//         foreach ($attributeIds as $attributeId) {
//             $attribute = Attribute::find($attributeId);
//             if (!$attribute) {
//                 continue;
//             }

//             $attributeName = $attribute->name;
//             $isFilterSelected = isset($selectedFilters[$attributeName]);

//             $productIdsToUse = $isFilterSelected ? $allCategoryProductIds : $filteredProductIds;

//             $attributeValues = DB::table('product_attributes as pa')
//                 ->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
//                 ->whereIn('pa.product_id', $productIdsToUse)
//                 ->where('pa.attribute_id', $attributeId)
//                 ->orderBy('pa.attribute_value', 'asc')
//                 ->select('at.name as attribute_name', 'pa.attribute_value', 'at.id as attribute_id', 'pa.product_id')
//                 ->get();

//             if ($attributeValues->count() > 0) {
//                 $convertedAttributeValues = $attributeValues->map(function($item) use ($convertAttributeValue, $attributeName) {
//                     $conversionResult = $convertAttributeValue($attributeName, $item->attribute_value);
//                     return (object)[
//                         'attribute_name' => $item->attribute_name,
//                         'attribute_value' => $item->attribute_value,
//                         'converted_value' => $conversionResult['converted_value'],
//                         'display_value' => $conversionResult['display_value'],
//                         'unit' => $conversionResult['unit'],
//                         'symbol' => $conversionResult['symbol'],
//                         'conversion_applied' => $conversionResult['conversion_applied'],
//                         'attribute_id' => $item->attribute_id,
//                         'product_id' => $item->product_id
//                     ];
//                 });

//                 $uniqueValues = $convertedAttributeValues->pluck('display_value')->unique()->filter()->values();

//                 $extractNumericValue = function($value) {
//                     if (preg_match('/^(\d+(?:\.\d+)?)\s*[a-zA-Z]*$/', $value, $matches)) {
//                         return (int)round((float)$matches[1]);
//                     } else if (is_numeric($value)) {
//                         return (int)round((float)$value);
//                     }
//                     return $value;
//                 };

//                 $numericValues = true;
//                 $cleanedValues = $uniqueValues->map(function($val) use ($extractNumericValue, &$numericValues) {
//                     $cleanedVal = $extractNumericValue($val);
//                     if (!is_numeric($cleanedVal)) {
//                         $numericValues = false;
//                     }
//                     return $cleanedVal;
//                 });

//                 if ($numericValues && $cleanedValues->count() > 2) {
//                     $sorted = $cleanedValues->filter(function($value) {
//                         return is_numeric($value);
//                     })->map(function($val) {
//                         return (int)$val;
//                     })->unique()->sort()->values();

//                     $chunkCount = min(5, ceil($sorted->count() / 2));
//                     $chunkSize = ceil($sorted->count() / $chunkCount);

//                     $selectedRanges = isset($selectedFilters[$attributeName]) ? $selectedFilters[$attributeName] : [];

//                     $ranges = $sorted->chunk($chunkSize)->map(function ($chunk) use ($attributeName, $filteredProductIds, $isFilterSelected, $convertedAttributeValues) {
//                         $min = (int)$chunk->first();
//                         $max = (int)$chunk->last();

//                         $matchingConvertedValues = $convertedAttributeValues->filter(function($item) use ($min, $max) {
//                             $numericValue = is_numeric($item->converted_value) ? (int)round((float)$item->converted_value) : null;
//                             return $numericValue !== null && $numericValue >= $min && $numericValue <= $max;
//                         });

//                         $productCount = $matchingConvertedValues->whereIn('product_id', $filteredProductIds)->pluck('product_id')->unique()->count();

//                         $sampleConvertedValue = $matchingConvertedValues->first();
//                         $unit = $sampleConvertedValue ? $sampleConvertedValue->symbol : '';

//                         $displayValue = $min == $max ? $min . ' ' . $unit : $min . ' - ' . $max . ' ' . $unit;

//                         return [
//                             'min' => $min,
//                             'max' => $max,
//                             'product_count' => $productCount,
//                             'display_value' => $displayValue,
//                             'symbol' => $unit
//                         ];
//                     })->filter(function($range) use ($isFilterSelected) {
//                         return $isFilterSelected || $range['product_count'] > 0;
//                     })->sortBy('min')->values()->toArray();

//                     foreach ($selectedRanges as $selectedRange) {
//                         if (is_array($selectedRange) && isset($selectedRange['min']) && isset($selectedRange['max'])) {
//                             $selectedMin = (int)$selectedRange['min'];
//                             $selectedMax = (int)$selectedRange['max'];

//                             $rangeExists = false;
//                             foreach ($ranges as $range) {
//                                 if ($range['min'] == $selectedMin && $range['max'] == $selectedMax) {
//                                     $rangeExists = true;
//                                     break;
//                                 }
//                             }

//                             if (!$rangeExists) {
//                                 $matchingConvertedValues = $convertedAttributeValues->filter(function($item) use ($selectedMin, $selectedMax) {
//                                     $numericValue = is_numeric($item->converted_value) ? (int)round((float)$item->converted_value) : null;
//                                     return $numericValue !== null && $numericValue >= $selectedMin && $numericValue <= $selectedMax;
//                                 });

//                                 $productCount = $matchingConvertedValues->whereIn('product_id', $filteredProductIds)->pluck('product_id')->unique()->count();

//                                 $sampleConvertedValue = $matchingConvertedValues->first();
//                                 $unit = $sampleConvertedValue ? $sampleConvertedValue->symbol : '';

//                                 $displayValue = $selectedMin == $selectedMax ? $selectedMin . ' ' . $unit : $selectedMin . ' - ' . $selectedMax . ' ' . $unit;

//                                 $ranges[] = [
//                                     'min' => $selectedMin,
//                                     'max' => $selectedMax,
//                                     'product_count' => $productCount,
//                                     'display_value' => $displayValue,
//                                     'selected' => true,
//                                     'symbol' => $unit
//                                 ];
//                             }
//                         }
//                     }

//                     usort($ranges, function($a, $b) {
//                         return $a['min'] - $b['min'];
//                     });

//                     if (!empty($ranges)) {
//                         $filters[] = [
//                             'specification_name' => $attributeName,
//                             'specification_type' => 'range',
//                             'specification_value' => $ranges,
//                         ];
//                     }
//                 } else {
//                     $valueCountMap = [];
//                     $selectedValues = isset($selectedFilters[$attributeName]) ? $selectedFilters[$attributeName] : [];

//                     foreach ($uniqueValues as $displayValue) {
//                         $correspondingItem = $convertedAttributeValues->firstWhere('display_value', $displayValue);
                        
//                         if (!$correspondingItem) continue;

//                         $productCount = $convertedAttributeValues
//                             ->where('display_value', $displayValue)
//                             ->whereIn('product_id', $filteredProductIds)
//                             ->pluck('product_id')
//                             ->unique()
//                             ->count();

//                         if ($isFilterSelected || $productCount > 0) {
//                             $valueCountMap[] = [
//                                 'value' => $correspondingItem->attribute_value,
//                                 'display_value' => $displayValue,
//                                 'converted_value' => $correspondingItem->converted_value,
//                                 'unit' => $correspondingItem->unit,
//                                 'symbol' => $correspondingItem->symbol,
//                                 'product_count' => $productCount,
//                                 'display_with_count' => $displayValue . ' (' . $productCount . ')',
//                                 'conversion_applied' => $correspondingItem->conversion_applied
//                             ];
//                         }
//                     }

//                     foreach ($selectedValues as $selectedValue) {
//                         $valueExists = false;
//                         foreach ($valueCountMap as $valueCount) {
//                             if ($valueCount['value'] == $selectedValue) {
//                                 $valueExists = true;
//                                 break;
//                             }
//                         }

//                         if (!$valueExists) {
//                             $conversionResult = $convertAttributeValue($attributeName, $selectedValue);
                            
//                             $productCount = $convertedAttributeValues
//                                 ->where('attribute_value', $selectedValue)
//                                 ->whereIn('product_id', $filteredProductIds)
//                                 ->pluck('product_id')
//                                 ->unique()
//                                 ->count();

//                             $valueCountMap[] = [
//                                 'value' => $selectedValue,
//                                 'display_value' => $conversionResult['display_value'],
//                                 'converted_value' => $conversionResult['converted_value'],
//                                 'unit' => $conversionResult['unit'],
//                                 'symbol' => $conversionResult['symbol'],
//                                 'product_count' => $productCount,
//                                 'display_with_count' => $conversionResult['display_value'] . ' (' . $productCount . ')',
//                                 'selected' => true,
//                                 'conversion_applied' => $conversionResult['conversion_applied']
//                             ];
//                         }
//                     }

//                     usort($valueCountMap, function($a, $b) {
//                         if (is_numeric($a['converted_value']) && is_numeric($b['converted_value'])) {
//                             return (int)round((float)$a['converted_value']) - (int)round((float)$b['converted_value']);
//                         }
//                         return strcmp($a['display_value'], $b['display_value']);
//                     });

//                     if (!empty($valueCountMap)) {
//                         $filters[] = [
//                             'specification_name' => $attributeName,
//                             'specification_type' => 'fixed',
//                             'specification_value' => $valueCountMap,
//                         ];
//                     }
//                 }
//             }
//         }
//     }

//     // Get brands
//     $selectedBrandIds = $request->brand_id ?? [];

//     $brands = DB::table('ec_products as p')
//         ->join('ec_brands as b', 'b.id', '=', 'p.brand_id')
//         ->whereIn('p.id', $allCategoryProductIds)
//         ->where('p.status', 'published')
//         ->select('b.id', 'b.name')
//         ->groupBy('b.id', 'b.name')
//         ->orderBy('b.name')
//         ->get()
//         ->map(function($brand) use ($filteredProductIds, $selectedBrandIds) {
//             $productCount = DB::table('ec_products')
//             ->where('brand_id', $brand->id)
//             ->whereIn('id', $filteredProductIds->toArray())
//             ->where('status', 'published')
//             ->count();
            
//             $isSelected = in_array($brand->id, $selectedBrandIds);
            
//             return [
//                 'id' => $brand->id,
//                 'name' => $brand->name,
//                 'product_count' => $productCount,
//                 'display_name' => $brand->name . ' (' . $productCount . ')',
//                 'is_selected' => $isSelected
//             ];
//         })
//         ->toArray();

//     // Get price range
//     $productIdsArray = $filteredProductIds->toArray();

//     $supplierExists = DB::table('product_suppliers')
//         ->whereIn('product_id', $productIdsArray)
//         ->exists();

//     if ($supplierExists) {
//         $priceRange = DB::table('product_suppliers')
//             ->whereIn('product_id', $productIdsArray)
//             ->where(function($query) {
//                 $query->where('price', '>', 0)
//                     ->orWhere('sale_price', '>', 0);
//             })
//             ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
//             ->first();

//         if (!$priceRange || ($priceRange->min_price <= 0 && $priceRange->max_price <= 0)) {
//             $priceRange = DB::table('product_suppliers')
//                 ->whereIn('product_id', $productIdsArray)
//                 ->selectRaw('MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as min_price, 
//                             MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as max_price')
//                 ->first();
//         }
//     } else {
//         $priceRange = DB::table('ec_products')
//             ->whereIn('id', $filteredProductIds)
//             ->where('status', 'published')
//             ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
//             ->first();
//     }

//     $priceMin = $priceRange ? (float)$priceRange->min_price : 0;
//     $priceMax = $priceRange ? (float)$priceRange->max_price : 0;

//     // Rating filter
//     $ratingFilter = [
//         'filter_name' => 'Rating',
//         'filter_type' => 'rating',
//         'filter_values' => [5, 4, 3, 2, 1],
//     ];

//     return response()->json([
//         'success' => true,
//         'filters' => $filters,
//         'products' => $paginatedProducts,
//         'brands' => $brands,
//         'price_min' => $priceMin,
//         'price_max' => $priceMax,
//         'rating_filter' => $ratingFilter,
//         'category_measurement_priorities' => $categoryMeasurementPriorities->toArray(),
//         'debug_info' => [
//             'category_id' => $category->id,
//             'total_products' => count($allCategoryProductIds),
//             'filtered_products' => count($filteredProductIds),
//             'sub_category_exists' => $subCategory ? true : false,
//             'attributes_found' => count($attributeIds),
//             'filters_generated' => count($filters),
//             'fallback_used' => empty($attributeIds) && $subCategory ? false : true
//         ]
//     ]);
// }

	/**
	 * @OA\Get(
	 *     path="/api/frontend/home-categories",
	 *     tags={"Frontend-Categories"},
	 *     summary="Fetch a limited set of parent and child categories",
	 *     description="Returns up to 14 categories including parent and child, with product count and image URL.",
	 *     @OA\Response(
	 *         response=200,
	 *         description="Categories fetched successfully",
	 *         @OA\JsonContent(
	 *             type="array",
	 *             @OA\Items(
	 *                 @OA\Property(property="id", type="integer", example=1),
	 *                 @OA\Property(property="name", type="string", example="Electronics"),
	 *                 @OA\Property(property="slug", type="string", example="electronics"),
	 *                 @OA\Property(property="parent_id", type="integer", example=0),
	 *                 @OA\Property(property="image", type="string", example="http://example.com/storage/categories/electronics.jpg"),
	 *                 @OA\Property(property="productCount", type="integer", example=42)
	 *             )
	 *         )
	 *     )
	 * )
	 */

	// public function fetchCategories(Request $request)
	// {
	// 	$limit = 15;

	// 	// Get only published leaf categories (no children), eager load seoUrl
	// 	$leafCategories = Category::where('status', 'published')
	// 		->whereDoesntHave('children')
	// 		->with(['seoUrl:id,relational_id,url'])
	// 		->get(['id', 'name', 'parent_id', 'image']); // ⛔ omit 'slug'

	// 	// Limit results
	// 	$limitedCategories = $leafCategories->take($limit);

	// 	foreach ($limitedCategories as $category) {
	// 		// Use SEO URL as slug
	// 		$category->slug = optional($category->seoUrl)->url;
	// 		unset($category->seoUrl); // optional: clean up relation from response

	// 		// Add product count
	// 		$category->productCount = $category->products()
	// 			->where('status', 'published')
	// 			->count();

	// 		// Build hierarchy
	// 		$hierarchy = [];
	// 		$current = $category;

	// 		while ($current && $current->parent_id) {
	// 			$parent = Category::where('id', $current->parent_id)
	// 				->where('status', 'published')
	// 				->with(['seoUrl:id,relational_id,url'])
	// 				->first(['id', 'name', 'parent_id']); // ⛔ omit 'slug'

	// 			if ($parent) {
	// 				$hierarchy[] = [
	// 					'id' => $parent->id,
	// 					'name' => $parent->name,
	// 					'slug' => optional($parent->seoUrl)->url, // only SEO url as slug
	// 				];
	// 				$current = $parent;
	// 			} else {
	// 				break;
	// 			}
	// 		}

	// 		$category->hierarchy = array_reverse($hierarchy);
	// 	}

	// 	return response()->json($limitedCategories);
	// }

	// 	public function fetchCategories(Request $request)
	// {
	// 	$limit = 15;

	// 	// Define allowed category names
	// 	$allowedNames = [
	// 		'Reach-In Refrigerators',
	// 		'Commercial Chef Base',
	// 		'Work Top Refrigerators',
	// 		'Undercounter Refrigerators',
	// 		'Pizza Prep Tables',
	// 		'Beer Dispensers',
	// 		'Glass Chillers and Frosters',
	// 		'Milk Cooler',
	// 		'Commercial Grills & Griddles',
	// 		'Commercial Food Processors',
	// 		'Commercial Espresso Machines',
	// 		'Commercial Gas And Electric Range',
	// 		'Deck Ovens',
	// 		'Commercial Gas Fryers',
	// 		'Back Bar Coolers',
	// 		'Planetary Mixer'
	// 	];

	// 	// Get only published leaf categories (no children), eager load seoUrl
	// 	$leafCategories = Category::where('status', 'published')
	// 		->whereDoesntHave('children')
	// 		->whereIn('name', $allowedNames) // 🔥 Only get allowed categories
	// 		->with(['seoUrl:id,relational_id,url'])
	// 		->get(['id', 'name', 'parent_id', 'image']);

	// 	// Limit results (optional if you want max 15)
	// 	$limitedCategories = $leafCategories->take($limit);

	// 	foreach ($limitedCategories as $category) {
	// 		$category->slug = optional($category->seoUrl)->url;
	// 		unset($category->seoUrl);

	// 		$category->productCount = $category->products()
	// 			->where('status', 'published')
	// 			->count();

	// 		$hierarchy = [];
	// 		$current = $category;

	// 		while ($current && $current->parent_id) {
	// 			$parent = Category::where('id', $current->parent_id)
	// 				->where('status', 'published')
	// 				->with(['seoUrl:id,relational_id,url'])
	// 				->first(['id', 'name', 'parent_id']);

	// 			if ($parent) {
	// 				$hierarchy[] = [
	// 					'id' => $parent->id,
	// 					'name' => $parent->name,
	// 					'slug' => optional($parent->seoUrl)->url,
	// 				];
	// 				$current = $parent;
	// 			} else {
	// 				break;
	// 			}
	// 		}

	// 		$category->hierarchy = array_reverse($hierarchy);
	// 	}

	// 	return response()->json($limitedCategories);
	// }
public function fetchCategories(Request $request)
{
	$limit = 15;

	$allowedNames = [
		'Reach-In Refrigerators',
		'Pizza Prep Tables',
		'Work Top Refrigerators',
		'Commercial Chef Base',
		'Undercounter Refrigerators',
		'Beer Dispensers',
		'Back Bar Coolers',
		'Glass Chillers and Frosters',
		'Milk Cooler',
		'Commercial Grills & Griddles',
		'Commercial Gas Fryers',
		'Commercial Gas And Electric Range',
		'Deck Ovens',
		'Commercial Food Processors',
		'Planetary Mixer',
		'Commercial Espresso Machines',
	];

	// Fetch matching leaf categories
	$leafCategories = Category::where('status', 'published')
		->whereDoesntHave('children')
		->whereIn('name', $allowedNames)
		->with(['seoUrl:id,relational_id,url'])
		->get(['id', 'name', 'parent_id', 'image']);

	// Sort categories in the same order as in $allowedNames
	$sortedCategories = collect($allowedNames)->map(function ($name) use ($leafCategories) {
		return $leafCategories->firstWhere('name', $name);
	})->filter(); // remove any nulls in case some names aren't found

	// Limit results
	$limitedCategories = $sortedCategories->take($limit);

	foreach ($limitedCategories as $category) {
		$category->slug = optional($category->seoUrl)->url;
		unset($category->seoUrl);

		$category->productCount = $category->products()
			->where('status', 'published')
			->count();

		$hierarchy = [];
		$current = $category;

		while ($current && $current->parent_id) {
			$parent = Category::where('id', $current->parent_id)
				->where('status', 'published')
				->with(['seoUrl:id,relational_id,url'])
				->first(['id', 'name', 'parent_id']);

			if ($parent) {
				$hierarchy[] = [
					'id' => $parent->id,
					'name' => $parent->name,
					'slug' => optional($parent->seoUrl)->url,
				];
				$current = $parent;
			} else {
				break;
			}
		}

		$category->hierarchy = array_reverse($hierarchy);
	}

	return response()->json($limitedCategories->values());
}



	/**
	 * @OA\Get(
	 *     path="/api/frontend/all-categories",
	 *     tags={"Frontend-Categories"},
	 *     summary="Fetch all parent and child categories",
	 *     description="Returns all parent and child categories with product count and image URL.",
	 *     @OA\Response(
	 *         response=200,
	 *         description="All categories fetched successfully",
	 *         @OA\JsonContent(
	 *             type="array",
	 *             @OA\Items(
	 *                 @OA\Property(property="id", type="integer", example=2),
	 *                 @OA\Property(property="name", type="string", example="Laptops"),
	 *                 @OA\Property(property="slug", type="string", example="laptops"),
	 *                 @OA\Property(property="parent_id", type="integer", example=1),
	 *                 @OA\Property(property="image", type="string", example="http://example.com/storage/categories/laptops.jpg"),
	 *                 @OA\Property(property="productCount", type="integer", example=15)
	 *             )
	 *         )
	 *     )
	 * )
	 */

	public function fetchAllCategories(Request $request)
{
    // Fetch published parent categories with SEO URL
    $parentCategories = Category::where('parent_id', 0)
        ->where('status', 'published')
        ->with(['seoUrl:id,relational_id,url'])
        ->get(['id', 'name', 'parent_id', 'image']); // ⛔ Don't select 'slug'

    // Fetch published child categories of published parents with SEO URL
    $childCategories = Category::whereIn('parent_id', $parentCategories->pluck('id'))
        ->where('status', 'published')
        ->with(['seoUrl:id,relational_id,url'])
        ->get(['id', 'name', 'parent_id', 'image']); // ⛔ Don't select 'slug'

    // Merge parent and child categories
    $allCategories = $parentCategories->merge($childCategories);

    foreach ($allCategories as $category) {
        // Set slug from SEO table
        $category->slug = optional($category->seoUrl)->url;
        unset($category->seoUrl); // optional: remove relation from response

        // Count only published products
        $category->productCount = $category->products()
            ->where('status', 'published')
            ->count();

        // Build full parent hierarchy
        $hierarchy = [];
        $current = $category;

        while ($current && $current->parent_id) {
            $parent = Category::where('id', $current->parent_id)
                ->where('status', 'published')
                ->with(['seoUrl:id,relational_id,url'])
                ->first(['id', 'name', 'parent_id']); // ⛔ Don't select 'slug'

            if ($parent) {
                $hierarchy[] = [
                    'id' => $parent->id,
                    'name' => $parent->name,
                    'slug' => optional($parent->seoUrl)->url, // Use SEO URL as slug
                ];
                $current = $parent;
            } else {
                break;
            }
        }

        $category->hierarchy = array_reverse($hierarchy);
    }

    return response()->json($allCategories);
}



	/**
	 * @OA\Get(
	 *     path="/api/frontend/categoryproducts",
	 *     tags={"Frontend-Categories"},
	 *     security={{"bearerAuth":{}}},
	 *     summary="Get all featured products grouped by third-level categories",
	 *     description="Returns featured products grouped under third-level categories. Includes wishlist status, best price, delivery date, reviews, stock, and images.",
	 *     @OA\Response(
	 *         response=200,
	 *         description="Featured products grouped by category fetched successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="category_name", type="string", example="Smartphones"),
	 *                     @OA\Property(
	 *                         property="featured_products",
	 *                         type="array",
	 *                         @OA\Items(
	 *                             type="object",
	 *                             @OA\Property(property="id", type="integer", example=101),
	 *                             @OA\Property(property="name", type="string", example="iPhone 14"),
	 *                             @OA\Property(property="sku", type="string", example="IP14-256GB"),
	 *                             @OA\Property(property="price", type="number", format="float", example=999.99),
	 *                             @OA\Property(property="sale_price", type="number", format="float", example=899.99),
	 *                             @OA\Property(property="original_price", type="number", format="float", example=999.99),
	 *                             @OA\Property(property="front_sale_price", type="number", format="float", example=899.99),
	 *                             @OA\Property(property="best_price", type="number", format="float", example=899.99),
	 *                             @OA\Property(property="delivery_days", type="integer", example=3),
	 *                             @OA\Property(property="total_reviews", type="integer", example=120),
	 *                             @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
	 *                             @OA\Property(property="left_stock", type="integer", example=20),
	 *                             @OA\Property(property="currency", type="string", example="USD"),
	 *                             @OA\Property(property="in_wishlist", type="boolean", example=true),
	 *                             @OA\Property(
	 *                                 property="images",
	 *                                 type="array",
	 *                                 @OA\Items(type="string", example="http://example.com/storage/products/image1.jpg")
	 *                             )
	 *                         )
	 *                     )
	 *                 )
	 *             )
	 *         )
	 *     )
	 * )
	*/

	public function getAllFeaturedProductsByCategory(Request $request)
	{
		$userId = Auth::id();
		$isUserLoggedIn = $userId !== null;

		// Fetch wishlist product IDs for logged-in users or guests
		$wishlistProductIds = $isUserLoggedIn
		? DB::table('ec_wish_lists')
		->where('customer_id', $userId)
		->pluck('product_id')
		->map(fn($id) => (int) $id)
		->toArray()
		: session()->get('guest_wishlist', []);

		// Get only third-level child categories that have featured products
		$categories = Category::whereHas('products', function ($query) {
			$query->where('is_featured', 1)
			->where('status', 'published');
		}, '>=', 5)
		->whereHas('parent.parent') // Ensures only third-level child categories
		->with(['products' => function ($query) {
			$query->where('is_featured', 1)
			->where('status', 'published')
				->select('id', 'name', 'sku', 'currency_id', 'units_sold'); // Select only necessary fields
			}])
		->take(5)
		->get();

		// Subquery for best price and delivery days
		$subQuery = Product::select('sku')
		->groupBy('sku');

		// Process categories and products
		$categories = $categories->map(function ($category) use ($subQuery, $wishlistProductIds) {
			$featuredProducts = $category->products->take(10);

			// Fetch all product details in one query
			$productDetails = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
				$join->on('ec_products.sku', '=', 'best_products.sku');
			})
			->whereIn('ec_products.id', $featuredProducts->pluck('id'))
			->with([
				'reviews',
				'currency',
				'productSuppliers',
				  'seoUrl',
				'productAttributes' => function ($query) {
					$query->whereHas('attributeDetails', function ($q) {
						$q->whereIn('name', ['Units per Case', 'Pack Type']);
					});
				},
			])
			->get()
			->keyBy('id');
			
				return [
					'category_name' => $category->name,
					'featured_products' => $featuredProducts->map(function ($product) use ($productDetails, $wishlistProductIds) {
						$details = $productDetails[$product->id] ?? null;
					if (!$details) return null; // Skip if no details found

					$totalReviews = $details->reviews->count();
					$avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
					
					$currencyTitle = $details->currency->symbol;
					$isInWishlist = in_array($details->id, $wishlistProductIds);

					// Process images efficiently
					$imageUrls = is_string($details->images)
					? json_decode($details->images, true)
					: (array) $details->images;

					$sellingType = null;

					if ($details->sellingUnitAttribute && $details->sellingUnitAttribute->attribute_value) {
						$fullValue = $details->sellingUnitAttribute->attribute_value;

						$attributeUnit = strpos($fullValue, '/') !== false
						? trim(explode('/', $fullValue)[1])
						: $fullValue;

						$sellingType = [
							'attribute_value' => $details->sellingUnitAttribute->attribute_value,
							'attribute_value_unit' => $attributeUnit,
						];
					}

					  // Calculate per unit price
					
					  $unitsPerCase = null;
					  $packType = null;
					  
					  if (!empty($details->per_unit_price_attributes)) {
						  $unitsPerCase = collect($details->per_unit_price_attributes)
							  ->first(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
						  $packType = collect($details->per_unit_price_attributes)
							  ->first(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');
					  }
					  
					  $firstSupplier = $details->productSuppliers->first();
					
					$basePrice = null;
					if ($firstSupplier) {
						$basePrice = ($firstSupplier->sale_price > 0) ? $firstSupplier->sale_price : $firstSupplier->price;
					}
							$perUnitPrice = null;

					if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
						$unitValue = (float) $unitsPerCase->attribute_value;
						if ($unitValue > 0) {
							$calculated = round($basePrice / $unitValue, 2);
							$perUnitPrice = $calculated . ' ' . '/' . ($packType?->attribute_value ?? '');
						}
					}
					

					$details->per_unit_price = $perUnitPrice;

					$leftStock = ($firstSupplier->quantity ?? 0) - ($details->units_sold ?? 0);

					return [
						'id' => $details->id,
						'name' => $details->name,
						'sku' => $details->sku,
						'url' => $details->seoUrl->url ?? null,
						'vendor_sku' => $firstSupplier->vendor_sku ?? null,
						'price' => $firstSupplier ? (float) $firstSupplier->price : null,
						'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
						'total_reviews' => $totalReviews,
						'avg_rating' => $avgRating,
						'left_stock' => $leftStock,
						'currency' => $currencyTitle,
						'in_wishlist' => $isInWishlist,
						'images' => $imageUrls,
						"original_price"=> $firstSupplier ? (float) $firstSupplier->price : null,
						'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
						"best_price"=> $firstSupplier ? (float) $firstSupplier->price : null,
						"selling_type"=> $sellingType,
						"per_unit_price"=>   $details->per_unit_price,
						'vendor_id' => $firstSupplier->vendor_id ?? null,
						'map' => $firstSupplier ? (float) $firstSupplier->map : null,
						'inventory' => $firstSupplier->inventory ?? null,
						'in_stock' => $firstSupplier->in_stock ?? null,
						'delivery_days' => $firstSupplier->delivery_days ?? null,
						'delivery_days' => $firstSupplier->delivery_days ?? null,
						'return_policy' => $firstSupplier->return_policy ?? null,
						'free_shipping' => $firstSupplier->free_shipping ?? null,
						'warranty_information' => $firstSupplier->warranty_information ?? null,
					];
				})->filter()->values(), // Remove null values and reset array keys
				];
			});

		return response()->json([
			'success' => true,
			'data' => $categories,
		]);
	}


	/**
	 * @OA\Get(
	 *     path="/api/frontend/categoryguestproducts",
	 *     tags={"Frontend-Categories"},
	 *     summary="Get all featured products by category for guest users",
	 *     description="Returns featured products grouped under third-level categories for guest users. Includes best price, delivery days, stock, reviews, and images.",
	 *     @OA\Response(
	 *         response=200,
	 *         description="Featured products grouped by category fetched successfully for guests",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="category_name", type="string", example="Electronics"),
	 *                     @OA\Property(
	 *                         property="featured_products",
	 *                         type="array",
	 *                         @OA\Items(
	 *                             type="object",
	 *                             @OA\Property(property="id", type="integer", example=202),
	 *                             @OA\Property(property="name", type="string", example="Samsung Galaxy S22"),
	 *                             @OA\Property(property="sku", type="string", example="SG-S22-128GB"),
	 *                             @OA\Property(property="price", type="number", format="float", example=849.99),
	 *                             @OA\Property(property="sale_price", type="number", format="float", example=799.99),
	 *                             @OA\Property(property="original_price", type="number", format="float", example=849.99),
	 *                             @OA\Property(property="front_sale_price", type="number", format="float", example=799.99),
	 *                             @OA\Property(property="best_price", type="number", format="float", example=799.99),
	 *                             @OA\Property(property="delivery_days", type="integer", example=5),
	 *                             @OA\Property(property="total_reviews", type="integer", example=85),
	 *                             @OA\Property(property="avg_rating", type="number", format="float", example=4.2),
	 *                             @OA\Property(property="left_stock", type="integer", example=50),
	 *                             @OA\Property(property="currency", type="string", example="USD"),
	 *                             @OA\Property(
	 *                                 property="images",
	 *                                 type="array",
	 *                                 @OA\Items(type="string", example="http://example.com/storage/products/samsung.jpg")
	 *                             )
	 *                         )
	 *                     )
	 *                 )
	 *             )
	 *         )
	 *     )
	 * )
	 */

	public function getAllGuestFeaturedProductsByCategory(Request $request)
	{
		$categories = Category::whereHas('products', function ($query) {
			$query->where('is_featured', 1)
			->where('status', 'published');
		}, '>=', 5)
		->whereHas('parent.parent') // Ensures only third-level child categories
		->with(['products' => function ($query) {
			$query->where('is_featured', 1)
			->where('status', 'published')
				->select('id', 'name', 'sku', 'currency_id', 'units_sold'); // Select only necessary fields
			}])
		->take(5)
		->get();

		// Subquery for best price and delivery days
		$subQuery = Product::select('sku')
		->groupBy('sku');

		// Process categories and products
		$categories = $categories->map(function ($category) use ($subQuery) {
			$featuredProducts = $category->products->take(10);

			// Fetch all product details in one query
			$productDetails = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
				$join->on('ec_products.sku', '=', 'best_products.sku');
			})
			->whereIn('ec_products.id', $featuredProducts->pluck('id'))
			->with([
				'reviews',
				'currency',
				'productSuppliers',
				'vendors',
				 'seoUrl',
				'productAttributes' => function ($query) {
					$query->whereHas('attributeDetails', function ($q) {
						$q->whereIn('name', ['Units per Case', 'Pack Type']);
					});
				},
			]) // Eager load relationships
			->get()			
				->keyBy('id'); // Use keyBy to quickly fetch by ID later
				return [
					'category_name' => $category->name,
					'featured_products' => $featuredProducts->map(function ($product) use ($productDetails) {
						$details = $productDetails[$product->id] ?? null;
					if (!$details) return null; // Skip if no details found

					$totalReviews = $details->reviews->count();
					$avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
					$currencyTitle = $details->currency->symbol ;

					// Process images efficiently
					$imageUrls = is_string($details->images)
					? json_decode($details->images, true)
					: (array) $details->images;

					$sellingType = null;

					if ($details->sellingUnitAttribute && $details->sellingUnitAttribute->attribute_value) {
						$fullValue = $details->sellingUnitAttribute->attribute_value;

						$attributeUnit = strpos($fullValue, '/') !== false
						? trim(explode('/', $fullValue)[1])
						: $fullValue;

						$sellingType = [
							'attribute_value' => $details->sellingUnitAttribute->attribute_value,
							'attribute_value_unit' => $attributeUnit,
						];
					}
					$firstSupplier = $details->productSuppliers->first();
					$leftStock = ($firstSupplier->quantity ?? 0) - ($details->units_sold ?? 0);

					  // Calculate per unit price
					$unitsPerCase = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
					$packType = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');

					$basePrice = null;
					if ($firstSupplier) {
						$basePrice = ($firstSupplier->sale_price > 0) ? $firstSupplier->sale_price : $firstSupplier->price;
					}

					// $basePrice = ($firstSupplier->sale_price > 0) ? $firstSupplier->sale_price : $firstSupplier->price;
					$perUnitPrice = null;

					if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
						$unitValue = (float) $unitsPerCase->attribute_value;
						if ($unitValue > 0) {
							$calculated = round($basePrice / $unitValue, 2);
							$perUnitPrice = $calculated . ' ' . '/' . ($packType?->attribute_value ?? '');
						}
					}

					$details->per_unit_price = $perUnitPrice;

					

					return [
						'id' => $details->id,
						'name' => $details->name,
						'sku' => $details->sku,
						'url' => $details->seoUrl->url ?? null,
						'vendor_sku' => $firstSupplier->vendor_sku ?? null,
						'price' => $firstSupplier?->price ? (float) $firstSupplier->price : (float) $details->price,
						"sale_price" => $firstSupplier?->sale_price ? (float) $firstSupplier->sale_price : null,
						'total_reviews' => $totalReviews,
						'avg_rating' => $avgRating,
						'left_stock' => $leftStock,
						'currency' => $currencyTitle,
						'images' => $imageUrls,
						"original_price" => $firstSupplier?->price ? (float) $firstSupplier->price : (float) $details->price,
						'front_sale_price' => $firstSupplier?->sale_price ? (float) $firstSupplier->sale_price : (float) $details->price,
						"best_price" => $firstSupplier?->price ? (float) $firstSupplier->price : (float) $details->price,
						"selling_type"=> $sellingType,
						"per_unit_price"=>   $details->per_unit_price,
						'vendor_id' => $firstSupplier->vendor_id ?? null,
						'map' => $firstSupplier ? (float) $firstSupplier->map : null,
						'inventory' => $firstSupplier->inventory ?? null,
						'in_stock' => $firstSupplier->in_stock ?? null,
						'delivery_days' => $firstSupplier->delivery_days ?? null,
						'delivery_days' => $firstSupplier->delivery_days ?? null,
						'return_policy' => $firstSupplier->return_policy ?? null,
						'free_shipping' => $firstSupplier->free_shipping ?? null,
						'warranty_information' => $firstSupplier->warranty_information ?? null,
					];
				})->filter()->values(), // Remove null values and reset array keys
				];
			});

		return response()->json([
			'success' => true,
			'data' => $categories,
		]);
	}


	// Recursive function to modify images for children and all sub-level categories
private function addImageUrlsRecursively($category)
{
		// If the category has children, modify their images as well
	if (isset($category->children) && !empty($category->children)) {
		foreach ($category->children as $childCategory) {
				$childCategory->image ; // Modify image for child category
				// Recursively handle children of children (grandchildren, etc.)
				$this->addImageUrlsRecursively($childCategory);
			}
		}
	}

	private function buildTree($categories, $parentId = 0, $limit = 12)
	{
		$branch = [];
		$count = 0;

		foreach ($categories as $category) {
			if ($category->parent_id == $parentId) {
				// Count products for the category
				$category->productCount = $category->products()->count();

				// Recursively build children
				$children = $this->buildTree($categories, $category->id, $limit);

				if ($children) {
					$category->children = array_slice($children, 0, $limit);
				} else {
					$category->children = [];
				}

				$branch[] = $category;

				$count++;
				if ($count >= $limit) {
					break;
				}
			}
		}

		return $branch;
	}


}
