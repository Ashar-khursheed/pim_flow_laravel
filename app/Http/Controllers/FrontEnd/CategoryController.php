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
use App\Models\Models\Specification;
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

	

	// private function getImageUrl($imagePath)
	// {
	// 	if (!$imagePath) {
	// 		return null; // Return null if there's no image path
	// 	}

	// 	// Check if the image exists in the 'products' directory inside storage
	// 	$productsPath = public_path("storage/products/{$imagePath}");
	// 	if (file_exists($productsPath)) {
	// 		return url("storage/products/{$imagePath}");
	// 	}

	// 	// Check if the image exists in the general 'storage' directory inside storage
	// 	$generalStoragePath = public_path("storage/{$imagePath}");
	// 	if (file_exists($generalStoragePath)) {
	// 		return url("storage/{$imagePath}");
	// 	}

	// 	return null; // Return null if the image doesn't exist
	// }

	
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
				? $product->currency->title . ' '
				: $product->price . ' ' . $product->currency->title;
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
    // public function getSpecificationFilters(Request $request)
    // {
    //     // Existing validation code
    //     $validator = Validator::make($request->all(), [
    //         'category_id' => 'required|integer',
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
    //     $category = Category::find($request->category_id);
    //     if (!$category) {
    //         return response()->json(['success' => false, 'message' => 'Category does not exist.'], 400);
    //     }

    //     // Get products from current category
    //     $currentCategoryProducts = $category->products()->where('status', 'published')->pluck('id')->all();
    //     // Get all child categories based on parent_id
    //     $childCategories = Category::where('parent_id', $category->id)->get();
    //     $childCategoryIds = $childCategories->pluck('id')->toArray();

    //     // Get all products from child categories
    //     $childProductIds = [];
    //     if (!empty($childCategoryIds)) {
    //         // Using a relationship between categories and products
    //         foreach ($childCategories as $childCategory) {
    //             $childProductIds = array_merge($childProductIds, $childCategory->products()->where('status', 'published')->pluck('id')->all());
    //         }
    //     }

    //     // Combine products from current category and all child categories
    //     $allCategoryProductIds = array_unique(array_merge($currentCategoryProducts, $childProductIds));

    //     // Debug info for verification
    //     $debugInfo = [
    //         'category_id' => $request->category_id,
    //         'current_category_product_count' => count($currentCategoryProducts),
    //         'child_categories' => $childCategoryIds,
    //         'child_categories_count' => count($childCategoryIds),
    //         'child_products_count' => count($childProductIds),
    //         'total_products' => count($allCategoryProductIds)
    //     ];

    //     if (empty($allCategoryProductIds)) {
    //         return response()->json([
    //             'success' => true,
    //             'filters' => [],
    //             'products' => [],
    //             'brands' => [],
    //             'rating_filter' => [
    //                 'filter_name' => 'Rating',
    //                 'filter_type' => 'rating',
    //                 'filter_values' => [5, 4, 3, 2, 1],
    //             ],
    //             'debug_info' => $debugInfo
    //         ]);
    //     }

    //     // Start with all category product IDs (including child categories)
    //     $filteredProductIds = collect($allCategoryProductIds);

    //     // Group filters by specification name for proper application
    //     $groupedFilters = [];
    //     $rangeFiltersByAttribute = []; // Changed: Store range filters by attribute name

    //     if ($request->has('filters') && is_array($request->filters)) {
    //         foreach ($request->filters as $filter) {
    //             if (!isset($filter['specification_name']) || !isset($filter['specification_value']) || empty($filter['specification_value'])) {
    //                 continue;
    //             }

    //             $specName = $filter['specification_name'];
    //             $specValues = is_array($filter['specification_value']) ? $filter['specification_value'] : [$filter['specification_value']];

    //             // Check if this is a range filter
    //             $isRangeFilter = false;
    //             foreach ($specValues as $value) {
    //                 if (is_array($value) && isset($value['min']) && isset($value['max'])) {
    //                     $isRangeFilter = true;

    //                     // Changed: Store range filters by attribute name
    //                     if (!isset($rangeFiltersByAttribute[$specName])) {
    //                         $rangeFiltersByAttribute[$specName] = [];
    //                     }
    //                     $rangeFiltersByAttribute[$specName][] = $value;
    //                 }
    //             }

    //             // If not a range filter, add to regular grouped filters
    //             if (!$isRangeFilter) {
    //                 if (!isset($groupedFilters[$specName])) {
    //                     $groupedFilters[$specName] = [];
    //                 }
    //                 $groupedFilters[$specName] = array_merge($groupedFilters[$specName], $specValues);
    //             }
    //         }
    //     }

    //     $debugInfo['grouped_filters'] = $groupedFilters;
    //     $debugInfo['range_filters_by_attribute'] = $rangeFiltersByAttribute; // Changed: Updated debug info

    //     // Apply regular attribute filters if provided, grouped by specification name
    //     foreach ($groupedFilters as $specName => $specValues) {
    //         // Find attribute ID based on name
    //         $attribute = Attribute::where('name', $specName)->first();
    //         if (!$attribute) {
    //             continue;
    //         }

    //         // Find product IDs that match this attribute and values
    //         $matchingProductIds = DB::table('product_attributes as pa')
    //             ->where('pa.attribute_id', $attribute->id)
    //             ->whereIn('pa.attribute_value', $specValues)
    //             ->whereIn('pa.product_id', $filteredProductIds)
    //             ->pluck('pa.product_id')
    //             ->unique();

    //         // Intersect with our running list of product IDs
    //         $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

    //         // If no products match these filters, return empty results early
    //         if ($filteredProductIds->isEmpty()) {
    //             return response()->json([
    //                 'success' => true,
    //                 'filters' => [],
    //                 'products' => [],
    //                 'brands' => [],
    //                 'rating_filter' => [
    //                     'filter_name' => 'Rating',
    //                     'filter_type' => 'rating',
    //                     'filter_values' => [5, 4, 3, 2, 1],
    //                 ],
    //                 'debug_info' => array_merge($debugInfo, ['filter_applied' => $specName, 'empty_after' => true])
    //             ]);
    //         }
    //     }

    //     // Changed: Apply range filters by attribute
    //     foreach ($rangeFiltersByAttribute as $specName => $ranges) {
    //         // Find attribute ID based on name
    //         $attribute = Attribute::where('name', $specName)->first();
    //         if (!$attribute) {
    //             continue;
    //         }

    //         // Start with the base query
    //         $query = DB::table('product_attributes as pa')
    //             ->where('pa.attribute_id', $attribute->id)
    //             ->whereIn('pa.product_id', $filteredProductIds);

    //         // Build range conditions for this attribute - using OR between ranges of the same attribute
    //         $rangeConditions = [];
    //         foreach ($ranges as $range) {
    //             $min = $range['min'];
    //             $max = $range['max'];

    //             // For numeric attribute values, handle different formats
    //             $rangeConditions[] = "(CAST(pa.attribute_value AS DECIMAL(10,2)) BETWEEN $min AND $max OR
    //                             CAST(REGEXP_REPLACE(pa.attribute_value, '[^0-9].*', '') AS DECIMAL(10,2)) BETWEEN $min AND $max)";
    //         }

    //         // Only add WHERE condition if we have range conditions
    //         if (count($rangeConditions) > 0) {
    //             // Use OR between ranges of the same attribute
    //             $query->whereRaw('(' . implode(' OR ', $rangeConditions) . ')');
    //         }

    //         // Get products that match ANY of the ranges for this attribute
    //         $matchingProductIds = $query->pluck('pa.product_id')->unique();

    //         // Intersect with our running list of product IDs
    //         $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

    //         // If no products match these filters, return empty results early
    //         if ($filteredProductIds->isEmpty()) {
    //             return response()->json([
    //                 'success' => true,
    //                 'filters' => [],
    //                 'products' => [],
    //                 'brands' => [],
    //                 'rating_filter' => [
    //                     'filter_name' => 'Rating',
    //                     'filter_type' => 'rating',
    //                     'filter_values' => [5, 4, 3, 2, 1],
    //                 ],
    //                 'debug_info' => array_merge($debugInfo, ['range_filter_applied' => $specName, 'empty_after_range' => true])
    //             ]);
    //         }
    //     }

    //     // If a rating filter is applied, filter the already filtered product IDs
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
    //                 'filters' => [],
    //                 'products' => [],
    //                 'brands' => [],
    //                 'rating_filter' => [
    //                     'filter_name' => 'Rating',
    //                     'filter_type' => 'rating',
    //                     'filter_values' => [5, 4, 3, 2, 1],
    //                 ],
    //                 'debug_info' => array_merge($debugInfo, ['empty_after_rating' => true])
    //             ]);
    //         }
    //     }

    //     // Fetching products based on filters
    //     $products = Product::whereIn('id', $filteredProductIds)
    //         ->where('status', 'published')
    //         ->with(['currency', 'reviews', 'brand'])
    //         ->when($request->has('price_min') || $request->has('price_max'), function ($query) use ($request) {
    //             $min = $request->input('price_min', 0);
    //             $max = $request->input('price_max', PHP_INT_MAX);
    //             return $query->whereRaw("COALESCE(sale_price, price) BETWEEN ? AND ?", [$min, $max]);
    //         })
    //         ->when($request->has('brand_id') && $request->brand_id, function ($query) use ($request) {
    //             return $query->whereIn('brand_id', $request->brand_id);
    //         });

    //     // Apply sorting
    //     $sortBy = $request->input('sort_by', 'created_at');
    //     $sortByType = $request->input('sort_by_type', 'desc');
    //     if ($sortBy == 'price') {
    //         $products = $products->orderByRaw("COALESCE(sale_price, price) $sortByType");
    //     } else {
    //         $products = $products->orderBy($sortBy, $sortByType);
    //     }

    //     $paginatedProducts = $products->paginate($perPage);
    //     $modifiedProducts = $paginatedProducts->getCollection()->map(function ($product) {
    //         $product->currency_title = optional($product->currency)->title;
    //         $product->avg_rating = $product->reviews->count() > 0 ? round($product->reviews->avg('star')) : null;
    //         $product->brand_name = optional($product->brand)->name;

    //         $product->images = is_string($product->images)
    //         ? json_decode($product->images, true)
    //         : (array) $product->images;
        

    //         $product->video_path = is_array($product->video_path) ? $product->video_path : (json_decode($product->video_path, true) ?: []);

    //         unset($product->currency, $product->reviews, $product->brand);
    //         return $product;
    //     });

    //     $paginatedProducts->setCollection($modifiedProducts);

    //     // Initialize filters array - will remain empty if subcategory doesn't exist
    //     $filters = [];

    //     // Get subcategory for this category
    //     $subCategory = DB::table('sub_categories')
    //         ->where('category_id', $request->category_id)
    //         ->first();

    //     $debugInfo['has_subcategory'] = $subCategory ? true : false;

    //     // Only process attribute filters if the subcategory exists
    //     if ($subCategory) {
    //         $attributeIdsField = null;
    //         $attributeIds = [];

    //         // Check which attribute ID field exists
    //         if (property_exists($subCategory, 'attributes_ids') || isset($subCategory->attributes_ids)) {
    //             $attributeIdsField = 'attributes_ids';
    //         } else if (property_exists($subCategory, 'attributes_jd') || isset($subCategory->attributes_jd)) {
    //             $attributeIdsField = 'attributes_jd';
    //         }

    //         $debugInfo['attribute_ids_field'] = $attributeIdsField;

    //         // Process attribute IDs if the field exists and has value
    //         if ($attributeIdsField && !empty($subCategory->$attributeIdsField)) {
    //             $attributeIdsValue = $subCategory->$attributeIdsField;

    //             // Parse attribute IDs based on data type
    //             if (is_string($attributeIdsValue)) {
    //                 $attributeIds = json_decode($attributeIdsValue, true);
    //                 $debugInfo['json_decode_error'] = json_last_error_msg();

    //                 // If it's not valid JSON, try comma-separated format
    //                 if (json_last_error() !== JSON_ERROR_NONE) {
    //                     $attributeIds = explode(',', $attributeIdsValue);
    //                     $debugInfo['using_comma_separated'] = true;
    //                 }
    //                 // Special case: Check if we have an array containing a single string with comma-separated values
    //                 else if (count($attributeIds) === 1 && is_string($attributeIds[0]) && strpos($attributeIds[0], ',') !== false) {
    //                     $attributeIds = explode(',', $attributeIds[0]);
    //                     $debugInfo['using_nested_comma_separated'] = true;
    //                 }
    //             } else {
    //                 $attributeIds = $attributeIdsValue;
    //             }

    //             // Ensure we have an array of integers
    //             $attributeIds = array_map('intval', (array)$attributeIds);
    //             $debugInfo['attribute_ids_parsed'] = $attributeIds;

    //             // Only proceed if we have valid attribute IDs
    //             if (!empty($attributeIds)) {
    //                 // Get attribute filters for both parent and child category products
    //                 $attributeValues = DB::table('product_attributes as pa')
    //                     ->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
    //                     ->whereIn('pa.product_id', $allCategoryProductIds)
    //                     ->whereIn('pa.attribute_id', $attributeIds)
    //                     ->orderBy('pa.attribute_value', 'asc')  // <- sort ascending
    //                     ->select('at.name as attribute_name', 'pa.attribute_value', 'at.id as attribute_id')
    //                     ->get();

                        
    //                 $debugInfo['attribute_values_count'] = $attributeValues->count();

    //                 // If we have any attribute values
    //                 if ($attributeValues->count() > 0) {
    //                     $attributeValues = $attributeValues->groupBy('attribute_name');

    //                     // Process attribute filters
    //                     foreach ($attributeValues as $attributeName => $values) {
    //                         $uniqueValues = $values->pluck('attribute_value')->unique()->filter()->values();

    //                         // Helper function to extract clean integer from various formats
    //                         $extractIntegerValue = function($value) {
    //                             // Handle fractions like "13 4/5"
    //                             if (preg_match('/^(\d+)\s+\d+\/\d+$/', $value, $matches)) {
    //                                 return (int)$matches[1];
    //                             }
    //                             // Handle decimal part like "12 4.5"
    //                             else if (preg_match('/^(\d+)\s+\d+\.\d+$/', $value, $matches)) {
    //                                 return (int)$matches[1];
    //                             }
    //                             // Handle regular decimals like "13.3"
    //                             else if (is_numeric($value)) {
    //                                 return (int)$value;
    //                             }
    //                             return $value;
    //                         };

    //                         // Check if all values are numeric-like
    //                         $numericValues = true;
    //                         $cleanedValues = $uniqueValues->map(function($val) use ($extractIntegerValue, &$numericValues) {
    //                             $cleanedVal = $extractIntegerValue($val);
    //                             if (!is_numeric($cleanedVal)) {
    //                                 $numericValues = false;
    //                             }
    //                             return $cleanedVal;
    //                         });

    //                         if ($numericValues && $cleanedValues->count() > 2) {
    //                             $sorted = $cleanedValues->filter(function($value) {
    //                                 return is_numeric($value);
    //                             })->map(function($val) {
    //                                 return (int)$val;
    //                             })->unique()->sort()->values();

    //                             // Store original mapping for debugging
    //                             $debugInfo['numeric_values_' . $attributeName] = $sorted->toArray();

    //                             // Calculate ranges based on actual data
    //                             $chunkCount = min(5, ceil($sorted->count() / 2));
    //                             $chunkSize = ceil($sorted->count() / $chunkCount);

    //                             $ranges = $sorted->chunk($chunkSize)->map(function ($chunk) {
    //                                 return [
    //                                     'min' => $chunk->first(),
    //                                     'max' => $chunk->last(),
    //                                 ];
    //                             })->toArray();

    //                             $filters[] = [
    //                                 'specification_name' => $attributeName,
    //                                 'specification_type' => 'range',
    //                                 'specification_value' => $ranges,
    //                             ];
    //                         } else {
    //                             $filters[] = [
    //                                 'specification_name' => $attributeName,
    //                                 'specification_type' => 'fixed',
    //                                 'specification_value' => $uniqueValues->values(),
    //                             ];
    //                         }
    //                     }
    //                 }
    //             }
    //         } else {
    //             $debugInfo['attributes_field_empty'] = true;
    //         }
    //     }

    //     // Get brands from all products (parent + child categories)
    //     $brandIds = Product::whereIn('id', $allCategoryProductIds)->where('status', 'published')->whereNotNull('brand_id')->pluck('brand_id')->unique();
    //     $brands = Brand::whereIn('id', $brandIds)->select('id', 'name')->get();

    //     $ratingFilter = [
    //         'filter_name' => 'Rating',
    //         'filter_type' => 'rating',
    //         'filter_values' => [5, 4, 3, 2, 1],
    //     ];

    //     $minPrice = Product::whereIn('id', $allCategoryProductIds)
    //     ->where('status', 'published')
    //     ->selectRaw('MIN(COALESCE(NULLIF(sale_price, 0), price)) as min_price')
    //     ->value('min_price');

    //     $maxPrice = Product::whereIn('id', $allCategoryProductIds)
    //     ->where('status', 'published')
    //     ->selectRaw('MAX(COALESCE(NULLIF(sale_price, 0), price)) as max_price')
    //     ->value('max_price');
    //     // Remove duplicate filter values

        
        
    //     return response()->json([
    //         'success' => true,
    //         'filters' => $filters,
    //         'products' => $paginatedProducts,
    //         'brands' => $brands,
    //         'price_min' => $minPrice,
    //         'price_max' => $maxPrice,
    //         'rating_filter' => $ratingFilter,
    //         'debug_info' => $debugInfo
    //     ]);
    // } 
    public function getSpecificationFilters(Request $request)
    {
        // Existing validation code
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer',
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
        $category = Category::find($request->category_id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category does not exist.'], 400);
        }

        // Get products from current category
        $currentCategoryProducts = $category->products()->where('status', 'published')->pluck('id')->all();
        // Get all child categories based on parent_id
        $childCategories = Category::where('parent_id', $category->id)->get();
        $childCategoryIds = $childCategories->pluck('id')->toArray();

        // Get all products from child categories
        $childProductIds = [];
        if (!empty($childCategoryIds)) {
            // Using a relationship between categories and products
            foreach ($childCategories as $childCategory) {
                $childProductIds = array_merge($childProductIds, $childCategory->products()->where('status', 'published')->pluck('id')->all());
            }
        }

        // Combine products from current category and all child categories
        $allCategoryProductIds = array_unique(array_merge($currentCategoryProducts, $childProductIds));

        // Debug info for verification
        $debugInfo = [
            'category_id' => $request->category_id,
            'current_category_product_count' => count($currentCategoryProducts),
            'child_categories' => $childCategoryIds,
            'child_categories_count' => count($childCategoryIds),
            'child_products_count' => count($childProductIds),
            'total_products' => count($allCategoryProductIds)
        ];

        if (empty($allCategoryProductIds)) {
            return response()->json([
                'success' => true,
                'filters' => [],
                'products' => [],
                'brands' => [],
                'rating_filter' => [
                    'filter_name' => 'Rating',
                    'filter_type' => 'rating',
                    'filter_values' => [5, 4, 3, 2, 1],
                ],
                'debug_info' => $debugInfo
            ]);
        }

        // Start with all category product IDs (including child categories)
        $filteredProductIds = collect($allCategoryProductIds);

        // Group filters by specification name for proper application
        $groupedFilters = [];
        $rangeFiltersByAttribute = []; // Changed: Store range filters by attribute name

        if ($request->has('filters') && is_array($request->filters)) {
            foreach ($request->filters as $filter) {
                if (!isset($filter['specification_name']) || !isset($filter['specification_value']) || empty($filter['specification_value'])) {
                    continue;
                }

                $specName = $filter['specification_name'];
                $specValues = is_array($filter['specification_value']) ? $filter['specification_value'] : [$filter['specification_value']];

                // Check if this is a range filter
                $isRangeFilter = false;
                foreach ($specValues as $value) {
                    if (is_array($value) && isset($value['min']) && isset($value['max'])) {
                        $isRangeFilter = true;

                        // Changed: Store range filters by attribute name
                        if (!isset($rangeFiltersByAttribute[$specName])) {
                            $rangeFiltersByAttribute[$specName] = [];
                        }
                        $rangeFiltersByAttribute[$specName][] = $value;
                    }
                }

                // If not a range filter, add to regular grouped filters
                if (!$isRangeFilter) {
                    if (!isset($groupedFilters[$specName])) {
                        $groupedFilters[$specName] = [];
                    }
                    $groupedFilters[$specName] = array_merge($groupedFilters[$specName], $specValues);
                }
            }
        }

        $debugInfo['grouped_filters'] = $groupedFilters;
        $debugInfo['range_filters_by_attribute'] = $rangeFiltersByAttribute; // Changed: Updated debug info

        // Apply regular attribute filters if provided, grouped by specification name
        foreach ($groupedFilters as $specName => $specValues) {
            // Find attribute ID based on name
            $attribute = Attribute::where('name', $specName)->first();
            if (!$attribute) {
                continue;
            }

            // Find product IDs that match this attribute and values
            $matchingProductIds = DB::table('product_attributes as pa')
                ->where('pa.attribute_id', $attribute->id)
                ->whereIn('pa.attribute_value', $specValues)
                ->whereIn('pa.product_id', $filteredProductIds)
                ->pluck('pa.product_id')
                ->unique();

            // Intersect with our running list of product IDs
            $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

            // If no products match these filters, return empty results early
            if ($filteredProductIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'filters' => [],
                    'products' => [],
                    'brands' => [],
                    'rating_filter' => [
                        'filter_name' => 'Rating',
                        'filter_type' => 'rating',
                        'filter_values' => [5, 4, 3, 2, 1],
                    ],
                    'debug_info' => array_merge($debugInfo, ['filter_applied' => $specName, 'empty_after' => true])
                ]);
            }
        }

        // Changed: Apply range filters by attribute
        foreach ($rangeFiltersByAttribute as $specName => $ranges) {
            // Find attribute ID based on name
            $attribute = Attribute::where('name', $specName)->first();
            if (!$attribute) {
                continue;
            }

            // Start with the base query
            $query = DB::table('product_attributes as pa')
                ->where('pa.attribute_id', $attribute->id)
                ->whereIn('pa.product_id', $filteredProductIds);

            // Build range conditions for this attribute - using OR between ranges of the same attribute
            $rangeConditions = [];
            foreach ($ranges as $range) {
                $min = $range['min'];
                $max = $range['max'];

                // For numeric attribute values, handle different formats
                $rangeConditions[] = "(CAST(pa.attribute_value AS DECIMAL(10,2)) BETWEEN $min AND $max OR
                                CAST(REGEXP_REPLACE(pa.attribute_value, '[^0-9].*', '') AS DECIMAL(10,2)) BETWEEN $min AND $max)";
            }

            // Only add WHERE condition if we have range conditions
            if (count($rangeConditions) > 0) {
                // Use OR between ranges of the same attribute
                $query->whereRaw('(' . implode(' OR ', $rangeConditions) . ')');
            }

            // Get products that match ANY of the ranges for this attribute
            $matchingProductIds = $query->pluck('pa.product_id')->unique();

            // Intersect with our running list of product IDs
            $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

            // If no products match these filters, return empty results early
            if ($filteredProductIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'filters' => [],
                    'products' => [],
                    'brands' => [],
                    'rating_filter' => [
                        'filter_name' => 'Rating',
                        'filter_type' => 'rating',
                        'filter_values' => [5, 4, 3, 2, 1],
                    ],
                    'debug_info' => array_merge($debugInfo, ['range_filter_applied' => $specName, 'empty_after_range' => true])
                ]);
            }
        }

        // If a rating filter is applied, filter the already filtered product IDs
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
                    'filters' => [],
                    'products' => [],
                    'brands' => [],
                    'rating_filter' => [
                        'filter_name' => 'Rating',
                        'filter_type' => 'rating',
                        'filter_values' => [5, 4, 3, 2, 1],
                    ],
                    'debug_info' => array_merge($debugInfo, ['empty_after_rating' => true])
                ]);
            }
        }

        // Fetching products based on filters
        $products = Product::whereIn('id', $filteredProductIds)
            ->where('status', 'published')
            ->with(['currency', 'reviews', 'brand'])
            ->when($request->has('price_min') || $request->has('price_max'), function ($query) use ($request) {
                $min = $request->input('price_min', 0);
                $max = $request->input('price_max', PHP_INT_MAX);
                return $query->whereRaw("COALESCE(sale_price, price) BETWEEN ? AND ?", [$min, $max]);
            })
            ->when($request->has('brand_id') && $request->brand_id, function ($query) use ($request) {
                return $query->whereIn('brand_id', $request->brand_id);
            });

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortByType = $request->input('sort_by_type', 'desc');
        if ($sortBy == 'price') {
            $products = $products->orderByRaw("COALESCE(sale_price, price) $sortByType");
        } else {
            $products = $products->orderBy($sortBy, $sortByType);
        }

        $paginatedProducts = $products->paginate($perPage);
        $modifiedProducts = $paginatedProducts->getCollection()->map(function ($product) {
            $product->currency_title = optional($product->currency)->title;
            $product->avg_rating = $product->reviews->count() > 0 ? round($product->reviews->avg('star')) : null;
            $product->brand_name = optional($product->brand)->name;

            $product->images = is_string($product->images)
            ? json_decode($product->images, true)
            : (array) $product->images;

            $product->video_path = is_array($product->video_path) ? $product->video_path : (json_decode($product->video_path, true) ?: []);

             // Then use safe access:
             $sellingUnit = $product->sellingUnitAttribute?->attribute_value_unit ?? 'N/A';

             // Or process the attribute value safely:
             if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
                 $fullValue = $product->sellingUnitAttribute->attribute_value;
                 if (strpos($fullValue, '/') !== false) {
                     $parts = explode('/', $fullValue);
                     $sellingUnit = trim($parts[1]);
                 } else {
                     $sellingUnit = $fullValue;
                 }
             } else {
                 $sellingUnit = null;
             }


            unset($product->currency, $product->reviews, $product->brand );
            return $product;
        });

        $paginatedProducts->setCollection($modifiedProducts);

        // Initialize filters array - will remain empty if subcategory doesn't exist
        $filters = [];

        // Get subcategory for this category
        $subCategory = DB::table('sub_categories')
            ->where('category_id', $request->category_id)
            ->first();

        $debugInfo['has_subcategory'] = $subCategory ? true : false;

        // Only process attribute filters if the subcategory exists
        if ($subCategory) {
            $attributeIdsField = null;
            $attributeIds = [];

            // Check which attribute ID field exists
            if (property_exists($subCategory, 'attributes_ids') || isset($subCategory->attributes_ids)) {
                $attributeIdsField = 'attributes_ids';
            } else if (property_exists($subCategory, 'attributes_jd') || isset($subCategory->attributes_jd)) {
                $attributeIdsField = 'attributes_jd';
            }

            $debugInfo['attribute_ids_field'] = $attributeIdsField;

            // Process attribute IDs if the field exists and has value
            if ($attributeIdsField && !empty($subCategory->$attributeIdsField)) {
                $attributeIdsValue = $subCategory->$attributeIdsField;

                // Parse attribute IDs based on data type
                if (is_string($attributeIdsValue)) {
                    $attributeIds = json_decode($attributeIdsValue, true);
                    $debugInfo['json_decode_error'] = json_last_error_msg();

                    // If it's not valid JSON, try comma-separated format
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $attributeIds = explode(',', $attributeIdsValue);
                        $debugInfo['using_comma_separated'] = true;
                    }
                    // Special case: Check if we have an array containing a single string with comma-separated values
                    else if (count($attributeIds) === 1 && is_string($attributeIds[0]) && strpos($attributeIds[0], ',') !== false) {
                        $attributeIds = explode(',', $attributeIds[0]);
                        $debugInfo['using_nested_comma_separated'] = true;
                    }
                } else {
                    $attributeIds = $attributeIdsValue;
                }

                // Ensure we have an array of integers
                $attributeIds = array_map('intval', (array)$attributeIds);
                $debugInfo['attribute_ids_parsed'] = $attributeIds;

                // Only proceed if we have valid attribute IDs
                if (!empty($attributeIds)) {
                    // Get attribute filters for both parent and child category products
                    $attributeValues = DB::table('product_attributes as pa')
                        ->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
                        ->whereIn('pa.product_id', $allCategoryProductIds)
                        ->whereIn('pa.attribute_id', $attributeIds)
                        ->orderBy('pa.attribute_value', 'asc')  // <- sort ascending
                        ->select('at.name as attribute_name', 'pa.attribute_value', 'at.id as attribute_id')
                        ->get();

                        
                    $debugInfo['attribute_values_count'] = $attributeValues->count();

                    // If we have any attribute values
                    if ($attributeValues->count() > 0) {
                        $attributeValues = $attributeValues->groupBy('attribute_name');

                        // Process attribute filters
                        foreach ($attributeValues as $attributeName => $values) {
                            $uniqueValues = $values->pluck('attribute_value')->unique()->filter()->values();

                            // Helper function to extract clean integer from various formats
                            $extractIntegerValue = function($value) {
                                // Handle fractions like "13 4/5"
                                if (preg_match('/^(\d+)\s+\d+\/\d+$/', $value, $matches)) {
                                    return (int)$matches[1];
                                }
                                // Handle decimal part like "12 4.5"
                                else if (preg_match('/^(\d+)\s+\d+\.\d+$/', $value, $matches)) {
                                    return (int)$matches[1];
                                }
                                // Handle regular decimals like "13.3"
                                else if (is_numeric($value)) {
                                    return (int)$value;
                                }
                                return $value;
                            };

                            // Check if all values are numeric-like
                            $numericValues = true;
                            $cleanedValues = $uniqueValues->map(function($val) use ($extractIntegerValue, &$numericValues) {
                                $cleanedVal = $extractIntegerValue($val);
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

                                // Store original mapping for debugging
                                $debugInfo['numeric_values_' . $attributeName] = $sorted->toArray();

                                // Calculate ranges based on actual data
                                $chunkCount = min(5, ceil($sorted->count() / 2));
                                $chunkSize = ceil($sorted->count() / $chunkCount);

                                $ranges = $sorted->chunk($chunkSize)->map(function ($chunk) {
                                    return [
                                        'min' => $chunk->first(),
                                        'max' => $chunk->last(),
                                    ];
                                })->toArray();

                                $filters[] = [
                                    'specification_name' => $attributeName,
                                    'specification_type' => 'range',
                                    'specification_value' => $ranges,
                                ];
                            } else {
                                $filters[] = [
                                    'specification_name' => $attributeName,
                                    'specification_type' => 'fixed',
                                    'specification_value' => $uniqueValues->values(),
                                ];
                            }
                        }
                    }
                }
            } else {
                $debugInfo['attributes_field_empty'] = true;
            }
        }

        // Get brands from all products (parent + child categories)
        $brandIds = Product::whereIn('id', $allCategoryProductIds)->where('status', 'published')->whereNotNull('brand_id')->pluck('brand_id')->unique();
        $brands = Brand::whereIn('id', $brandIds)->select('id', 'name')->get();

        $ratingFilter = [
            'filter_name' => 'Rating',
            'filter_type' => 'rating',
            'filter_values' => [5, 4, 3, 2, 1],
        ];

        $minPrice = Product::whereIn('id', $allCategoryProductIds)
        ->where('status', 'published')
        ->selectRaw('MIN(COALESCE(NULLIF(sale_price, 0), price)) as min_price')
        ->value('min_price');

        $maxPrice = Product::whereIn('id', $allCategoryProductIds)
        ->where('status', 'published')
        ->selectRaw('MAX(COALESCE(NULLIF(sale_price, 0), price)) as max_price')
        ->value('max_price');
        // Remove duplicate filter values

        
        
        return response()->json([
            'success' => true,
            'filters' => $filters,
            'products' => $paginatedProducts,
            'brands' => $brands,
            'price_min' => $minPrice,
            'price_max' => $maxPrice,
            'rating_filter' => $ratingFilter,
            'debug_info' => $debugInfo
        ]);
    }

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

    public function fetchCategories(Request $request)
    {
        // Limit to 14 categories
        $limit = 13;

        // Fetch parent categories
        $parentCategories = Category::where('parent_id', 0)
            ->get(['id', 'name', 'slug', 'parent_id', 'image']); // Select necessary fields

        // Fetch child categories
        $childCategories = Category::whereIn('parent_id', $parentCategories->pluck('id'))
            ->get(['id', 'name', 'slug', 'parent_id', 'image']); // Select necessary fields

        // Merge parent and child categories
        $allCategories = $parentCategories->merge($childCategories);

        // Limit the combined result to 14 categories
        $limitedCategories = $allCategories->take($limit);

        // Add product count and adjust image URLs
        foreach ($limitedCategories as $category) {
            $category->productCount = $category->products()->count(); // Count related products
            $category->image; // Adjust image URL
        }

        // Return categories with their details
        return response()->json($limitedCategories);
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
        // Fetch parent categories
        $parentCategories = Category::where('parent_id', 0)
            ->get(['id', 'name', 'slug', 'parent_id', 'image']); // Select necessary fields

        // Fetch child categories
        $childCategories = Category::whereIn('parent_id', $parentCategories->pluck('id'))
            ->get(['id', 'name', 'slug', 'parent_id', 'image']); // Select necessary fields

        // Merge parent and child categories
        $allCategories = $parentCategories->merge($childCategories);

        // Add product count and adjust image URLs
        foreach ($allCategories as $category) {
            $category->productCount = $category->products()->count(); // Count related products
            $category->image ; // Adjust image URL
        }

        // Return all categories with their details
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
     *                             @OA\Property(property="best_delivery_date", type="integer", example=3),
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
        }, '>=', 10)
        ->whereHas('parent.parent') // Ensures only third-level child categories
        ->with(['products' => function ($query) {
            $query->where('is_featured', 1)
                ->where('status', 'published')
                ->select('id', 'name', 'sku', 'price', 'currency_id', 'quantity', 'units_sold'); // Select only necessary fields
        }])
        ->take(5)
        ->get();

        // Subquery for best price and delivery days
        $subQuery = Product::select('sku')
            ->selectRaw('MIN(price) as best_price')
            ->selectRaw('MIN(delivery_days) as best_delivery_date')
            ->groupBy('sku');

        // Process categories and products
        $categories = $categories->map(function ($category) use ($subQuery, $wishlistProductIds) {
            $featuredProducts = $category->products->take(10);

            // Fetch all product details in one query
            $productDetails = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
                    $join->on('ec_products.sku', '=', 'best_products.sku')
                        ->whereColumn('ec_products.price', 'best_products.best_price');
                })
                ->whereIn('ec_products.id', $featuredProducts->pluck('id'))
                ->with(['reviews', 'currency']) // Eager load relationships
                ->get()
                ->keyBy('id'); // Use keyBy to quickly fetch by ID later

            return [
                'category_name' => $category->name,
                'featured_products' => $featuredProducts->map(function ($product) use ($productDetails, $wishlistProductIds) {
                    $details = $productDetails[$product->id] ?? null;
                    if (!$details) return null; // Skip if no details found

                    $totalReviews = $details->reviews->count();
                    $avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
                    $leftStock = ($details->quantity ?? 0) - ($details->units_sold ?? 0);
                    $currencyTitle = $details->currency->title ?? $details->price;
                    $isInWishlist = in_array($details->id, $wishlistProductIds);

                    // Process images efficiently
                    $imageUrls = is_string($details->images)
                    ? json_decode($details->images, true)
                    : (array) $details->images;
        
                
                    return [
                        'id' => $details->id,
                        'name' => $details->name,
                        'sku' => $details->sku,
                        'price' => $details->best_price ?? $details->price,
                        "sale_price" => $details->sale_price,
                        'best_delivery_date' => $details->best_delivery_date,
                        'total_reviews' => $totalReviews,
                        'avg_rating' => $avgRating,
                        'left_stock' => $leftStock,
                        'currency' => $currencyTitle,
                        'in_wishlist' => $isInWishlist,
                        'images' => $imageUrls,
                        "original_price"=> $details->price,
                        "front_sale_price"=> $details->price,
                        "best_price"=> $details->price,
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
     *                             @OA\Property(property="best_delivery_date", type="integer", example=5),
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
        }, '>=', 10)        
        ->whereHas('parent.parent') // Ensures only third-level child categories
        ->with(['products' => function ($query) {
            $query->where('is_featured', 1)
                ->where('status', 'published')
                ->select('id', 'name', 'sku', 'price', 'currency_id', 'quantity', 'units_sold'); // Select only necessary fields
        }])
        ->take(5)
        ->get();

        // Subquery for best price and delivery days
        $subQuery = Product::select('sku')
            ->selectRaw('MIN(price) as best_price')
            ->selectRaw('MIN(delivery_days) as best_delivery_date')
            ->groupBy('sku');

        // Process categories and products
        $categories = $categories->map(function ($category) use ($subQuery) {
            $featuredProducts = $category->products->take(10);

            // Fetch all product details in one query
            $productDetails = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
                    $join->on('ec_products.sku', '=', 'best_products.sku')
                        ->whereColumn('ec_products.price', 'best_products.best_price');
                })
                ->whereIn('ec_products.id', $featuredProducts->pluck('id'))
                ->with(['reviews', 'currency']) // Eager load relationships
                ->get()
                ->keyBy('id'); // Use keyBy to quickly fetch by ID later

            return [
                'category_name' => $category->name,
                'featured_products' => $featuredProducts->map(function ($product) use ($productDetails) {
                    $details = $productDetails[$product->id] ?? null;
                    if (!$details) return null; // Skip if no details found

                    $totalReviews = $details->reviews->count();
                    $avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
                    $leftStock = ($details->quantity ?? 0) - ($details->units_sold ?? 0);
                    $currencyTitle = $details->currency->symbol ?? $details->price;

                    // Process images efficiently
                    $imageUrls = is_string($details->images)
                    ? json_decode($details->images, true)
                    : (array) $details->images;


                    return [
                        'id' => $details->id,
                        'name' => $details->name,
                        'sku' => $details->sku,
                        'price' => $details->best_price ?? $details->price,
                        "sale_price" => $details->sale_price,
                        'best_delivery_date' => $details->best_delivery_date,
                        'total_reviews' => $totalReviews,
                        'avg_rating' => $avgRating,
                        'left_stock' => $leftStock,
                        'currency' => $currencyTitle,
                        'images' => $imageUrls,
                        "original_price"=> $details->price,
                        "front_sale_price"=> $details->price,
                        "best_price"=> $details->price,
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

    	// public function store(Request $request)
	// {
	// 	$validated = $request->validate([
	// 		'name' => 'required|string|max:255',
	// 		'parent_id' => 'nullable|exists:categories,id',
	// 		'description' => 'nullable|string',
	// 		'status' => 'required|boolean',
	// 		'image' => 'nullable|string',
	// 		'is_featured' => 'required|boolean',
	// 		'icon' => 'nullable|string',
	// 		'icon_image' => 'nullable|string',
	// 		'order' => 'nullable|integer',
	// 	]);

	// 	$category = Category::create($validated);
	// 	return response()->json($category, 201);
	// }

	// public function update(Request $request, $id)
	// {
	// 	$validated = $request->validate([
	// 		'name' => 'required|string|max:255',
	// 		'parent_id' => 'nullable|exists:categories,id',
	// 		'description' => 'nullable|string',
	// 		'status' => 'required|boolean',
	// 		'image' => 'nullable|string',
	// 		'is_featured' => 'required|boolean',
	// 		'icon' => 'nullable|string',
	// 		'icon_image' => 'nullable|string',
	// 		'order' => 'nullable|integer',
	// 	]);

	// 	$category = Category::findOrFail($id);
	// 	$category->update($validated);
	// 	return response()->json($category);
	// }

	// public function destroy($id)
	// {
	// 	$category = Category::findOrFail($id);
	// 	$category->delete();
	// 	return response()->json(['message' => 'Category deleted successfully']);
	// }
  

}
