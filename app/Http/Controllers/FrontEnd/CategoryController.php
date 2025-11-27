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
use App\Models\SeoManagement;
use Illuminate\Support\Facades\Auth;
// Add these imports at the top of your controller file
use PhpUnitsOfMeasure\PhysicalQuantity\Length;
use PhpUnitsOfMeasure\PhysicalQuantity\Mass;
use PhpUnitsOfMeasure\PhysicalQuantity\Volume;
use PhpUnitsOfMeasure\PhysicalQuantity\Temperature;
use PhpUnitsOfMeasure\PhysicalQuantity\Time;
use PhpUnitsOfMeasure\PhysicalQuantity\Speed;
use PhpUnitsOfMeasure\PhysicalQuantity\Area;
use PhpUnitsOfMeasure\PhysicalQuantity\Energy;
use PhpUnitsOfMeasure\PhysicalQuantity\Pressure;
use PhpUnitsOfMeasure\PhysicalQuantity\Force;
use Illuminate\Support\Facades\Schema;

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
     *     @OA\Response(response=200, description="Categories tree fetched successfully", @OA\MediaType(mediaType="application/json")),
     * )
     */

    // public function index(Request $request)
    // {
    // 	$filterId = $request->get('id'); // Optional ID filter
    // 	$limit = $request->get('limit', 12); // Default limit to 12

    // 	if ($filterId) {
    // 		// Fetch the specific category and its children (parent included)
    // 		$categories = Category::where('status', 'published')
    // 		->where(function ($query) use ($filterId) {
    // 			$query->where('id', $filterId)
    // 			->orWhere('parent_id', $filterId);
    // 		})
    // 		->get();
    // 	} else {
    // 		// Fetch all categories if no ID is provided
    // 		$categories = Category::all();
    // 	}

    // 	// Transform categories into a parent-child structure
    // 	$categoriesTree = $this->buildTree($categories, $filterId, $limit);

    // 	// // Add full URLs for images (both parent and child categories)
    // 	// foreach ($categoriesTree as $category) {
    // 	// 	// $category->image = $this->getImageUrl($category->image); // Modify image for parent category

    // 	// 	// Recursively modify images for children and children's children
    // 	// 	// $this->addImageUrlsRecursively($category);
    // 	// }

    // 	return response()->json($categoriesTree) ->header('Cache-Control', 'public, max-age=86400');
    // }
    public function index(Request $request)
    {
        $filterId = $request->get('id'); // Optional ID filter
        $limit = $request->get('limit', 12); // Default limit to 12

        if ($filterId) {
            // Fetch specific category and its children with seoUrl eager loaded
            $categories = Category::with('seoUrl')
                ->where('status', 'published')
                ->where(function ($query) use ($filterId) {
                    $query->where('id', $filterId)
                        ->orWhere('parent_id', $filterId);
                })
                ->get();
            $categories->map(function ($record) {

                $lastChildIds = !empty($record->last_child)
                        ? array_map('intval', explode(',', $record->last_child))
                        : [];

                    if (!empty($lastChildIds)) {
                        $record->last_children = Category::with('seoUrl')
                            ->whereIn('id', $lastChildIds)
                            ->get(['id', 'name', 'slug', 'parent_id', 'image', 'order'])
                            ->map(function ($child) {
                                return [
                                    'id' => $child->id,
                                    'name' => $child->name,
                                    'slug' => $child->seoUrl?->url ?? $child->slug ?? null,
                                    'parent_id' => $child->parent_id,
                                    'image' => $child->image,
                                    'order' => $child->order,
                                ];
                            });
                    } else {
                        $record->last_children = collect();
                    }
                return $record;
            });

        } else {
            // Fetch all categories with seoUrl eager loaded
            $categories = Category::with('seoUrl')->get();
            $categories->map(function ($record) {

                $lastChildIds = !empty($record->last_child)
                    ? array_map('intval', explode(',', $record->last_child))
                    : [];

                if (!empty($lastChildIds)) {
                        $record->last_children = Category::with('seoUrl')
                            ->whereIn('id', $lastChildIds)
                            ->get(['id', 'name', 'slug', 'parent_id', 'image', 'order'])
                            ->map(function ($child) {
                                return [
                                    'id' => $child->id,
                                    'name' => $child->name,
                                    'slug' => $child->seoUrl?->url ?? $child->slug ?? null,
                                    'parent_id' => $child->parent_id,
                                    'image' => $child->image,
                                    'order' => $child->order,
                                ];
                            });
                    } else {
                        $record->last_children = collect();
                    }

                return $record;
            });

        }

        // Replace slug with seoUrl->url
        foreach ($categories as $category) {
            $category->slug = $category->seoUrl ? $category->seoUrl->url : null;
            unset($category->seoUrl); // remove relation from JSON
        }

        // Transform categories into a parent-child structure
        $categoriesTree = $this->buildTree($categories, $filterId, $limit);

        return response()->json($categoriesTree)->header('Cache-Control', 'public, max-age=86400');
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
     *     @OA\Response(response=200, description="Categories tree fetched successfully", @OA\MediaType(mediaType="application/json")),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category not found")
     *         )
     *     )
     * )
     */
    // public function categoryslug(Request $request, $slug)
    // {
    // 	$limit = $request->get('limit', 12); // Default limit to 12

    // 	// Fetch the specific category by slug and its children (parent included)
    // 	$parentCategory = Category::where('slug', $slug)->first();

    // 	if (!$parentCategory) {
    // 		return response()->json(['message' => 'Category not found'], 404);
    // 	}

    // 	$categories = Category::where('id', $parentCategory->id)
    // 	->orWhere('parent_id', $parentCategory->id)
    // 	->get();

    // 	// Transform categories into a parent-child structure
    // 	$categoriesTree = $this->buildTree($categories, null, $limit);

    // 	// Add full URLs for images (both parent and child categories)
    // 	// foreach ($categoriesTree as $category) {
    // 	// 	$category->image = $this->getImageUrl($category->image); // Modify image for parent category

    // 	// 	// Recursively modify images for children and children's children
    // 	// 	$this->addImageUrlsRecursively($category);
    // 	// }

    // 	return response()->json($categoriesTree);
    // }
    public function categoryslug(Request $request, $slug)
    {
        $limit = $request->get('limit', 12);

        // Find seo record matching slug and relational_type 'Category'
        $seoRecord = SeoManagement::where('url', $slug)
            ->where('relational_type', 'Category')
            ->first();

        if (!$seoRecord) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Fetch the related category by relational_id
        $parentCategory = Category::where('id', $seoRecord->relational_id)->first();

        if (!$parentCategory) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Fetch parent category and its children with seoUrl eager loaded
        $categories = Category::with('seoUrl')
            ->where('id', $parentCategory->id)
            ->orWhere('parent_id', $parentCategory->id)
            ->get();
        $categories->map(function ($record) {
            $lastChildIds = !empty($record->last_child)
                ? array_map('intval', explode(',', $record->last_child))
                : [];

             if (!empty($lastChildIds)) {
                        $record->last_children = Category::with('seoUrl')
                            ->whereIn('id', $lastChildIds)
                            ->get(['id', 'name', 'slug', 'parent_id', 'image', 'order'])
                            ->map(function ($child) {
                                return [
                                    'id' => $child->id,
                                    'name' => $child->name,
                                    'slug' => $child->seoUrl?->url ?? $child->slug ?? null,
                                    'parent_id' => $child->parent_id,
                                    'image' => $child->image,
                                    'order' => $child->order,
                                ];
                            });
                    } else {
                        $record->last_children = collect();
                    }

            return $record;
        });


        // Add url property based on seoUrl->slug
        foreach ($categories as $category) {
            $category->url = $category->seoUrl ? $category->seoUrl->slug : null;
        }

        // Transform categories into parent-child structure
        $categoriesTree = $this->buildTree($categories, null, $limit);

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
     *     @OA\Response(response=200, description="Category details retrieved successfully", @OA\MediaType(mediaType="application/json")),
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

        //   $category->map(function ($record) {
        //         $lastChildIds = !empty($record->last_child)
        //             ? array_map('intval', explode(',', $record->last_child))
        //             : [];

        //         if (!empty($lastChildIds)) {
        //             $record->last_children = Category::whereIn('id', $lastChildIds)
        //                 ->get(['id', 'name', 'slug']);
        //         } else {
        //             $record->last_children = collect();
        //         }

        //         return $record;
        //     });
        return response()->json([
            'category' => $category,
        ])->header('Cache-Control', 'public, max-age=86400');
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
     *     @OA\Response(response=200, description="List of products for the category", @OA\MediaType(mediaType="application/json")),
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
        $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;

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
        ])->header('Cache-Control', 'public, max-age=86400');
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
     *     @OA\Response(response=200, description="List of filtered products and available filters", @OA\MediaType(mediaType="application/json")),
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

        // Find category
        $category = Category::where('id', $categoryIdentifier)
            ->orWhere('slug', $categoryIdentifier)
            ->orWhereHas('seoUrl', function ($q) use ($categoryIdentifier) {
                $q->where('url', $categoryIdentifier);
            })
            ->first();

        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category does not exist.'], 400);
        }

        // OPTIMIZATION: Single query to get all category measurement priorities with units
        $categoryMeasurementPriorities = DB::table('category_measurement_unit_priorities as cmup')
            ->join('measurement_types as mt', 'mt.id', '=', 'cmup.measurement_type_id')
            ->join('measurement_units as mu_primary', 'mu_primary.id', '=', 'cmup.measurement_unit_primary_id')
            ->leftJoin('measurement_units as mu_secondary', 'mu_secondary.id', '=', 'cmup.measurement_unit_secondary_id')
            ->where('cmup.category_id', $category->id)
            ->select(
                'mt.id as measurement_type_id',
                'mt.name as measurement_type',
                'mu_primary.id as primary_unit_id',
                'mu_primary.name as primary_unit',
                'mu_primary.symbol as primary_symbol',
                'mu_secondary.id as secondary_unit_id',
                'mu_secondary.name as secondary_unit',
                'mu_secondary.symbol as secondary_symbol'
            )
            ->get()
            ->keyBy('measurement_type');

        // OPTIMIZATION: Single query to get all products from current and child categories
        $allChildCategoryIds = $this->getAllChildCategoryIds($category->id);
        $allCategoryIds = array_merge([$category->id], $allChildCategoryIds);

        $allCategoryProductIds = DB::table('product_categories as pc')
            ->join('ec_products as p', 'p.id', '=', 'pc.product_id')
            ->whereIn('pc.category_id', $allCategoryIds)
            ->where('p.status', 'published')
            ->pluck('p.id')
            ->unique()
            ->toArray();

        if (empty($allCategoryProductIds)) {
            return $this->getEmptyResponse();
        }

        // Store original product IDs for price range calculation
        $originalProductIds = collect($allCategoryProductIds);
        $filteredProductIds = collect($allCategoryProductIds);

        // Process filters
        $selectedFilters = [];
        $filterConditions = []; // Store SQL conditions for batch processing

        if ($request->has('filters') && is_array($request->filters)) {
            foreach ($request->filters as $filter) {
                if (!isset($filter['specification_name']) || !isset($filter['specification_value']) || empty($filter['specification_value'])) {
                    continue;
                }

                $specName = $filter['specification_name'];
                $specValues = is_array($filter['specification_value']) ? $filter['specification_value'] : [$filter['specification_value']];
                $selectedFilters[$specName] = $specValues;

                // Check for range filters
                $isRangeFilter = false;
                $rangeConditions = [];

                foreach ($specValues as $value) {
                    if (is_array($value) && isset($value['min']) && isset($value['max'])) {
                        $isRangeFilter = true;
                        $rangeConditions[] = [
                            'min' => (float) $value['min'],
                            'max' => (float) $value['max']
                        ];
                    } elseif (
                        is_array($filter['specification_value']) &&
                        isset($filter['specification_value']['start']) &&
                        isset($filter['specification_value']['end'])
                    ) {
                        $isRangeFilter = true;
                        $rangeConditions[] = [
                            'min' => (float) $filter['specification_value']['start'],
                            'max' => (float) $filter['specification_value']['end']
                        ];
                    }
                }

                $filterConditions[] = [
                    'attribute_name' => $specName,
                    'is_range' => $isRangeFilter,
                    'values' => $isRangeFilter ? $rangeConditions : $specValues
                ];
            }
        }

        // OPTIMIZATION: Apply all attribute filters in single query
        if (!empty($filterConditions)) {
            $filteredProductIds = $this->applyAttributeFilters($filteredProductIds, $filterConditions, $categoryMeasurementPriorities);

            if ($filteredProductIds->isEmpty()) {
                return $this->getEmptyResponse();
            }
        }

        // Apply other filters
        if ($request->has('brand_id') && $request->brand_id) {
            $filteredProductIds = $filteredProductIds->intersect(
                DB::table('ec_products')->whereIn('brand_id', $request->brand_id)->pluck('id')
            );
        }

        if ($request->has('rating') && $request->rating) {
            $ratingFilteredIds = DB::table('ec_reviews')
                ->whereIn('product_id', $filteredProductIds)
                ->select('product_id')
                ->groupBy('product_id')
                ->havingRaw('ROUND(AVG(star)) = ?', [$request->rating])
                ->pluck('product_id');
            $filteredProductIds = $filteredProductIds->intersect($ratingFilteredIds);
        }

        // Apply price filter only to filtered products
        if ($request->has('price_min') || $request->has('price_max')) {
            $min = $request->input('price_min', 0);
            $max = $request->input('price_max', PHP_INT_MAX);

            $priceFilteredIds = DB::table('product_suppliers as ps')
                ->whereIn('ps.product_id', $filteredProductIds->toArray())
                ->whereRaw("CASE WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 THEN ps.sale_price ELSE ps.price END BETWEEN ? AND ?", [$min, $max])
                ->pluck('ps.product_id')
                ->unique();

            if ($priceFilteredIds->isEmpty()) {
                $priceFilteredIds = DB::table('ec_products as p')
                    ->whereIn('p.id', $filteredProductIds->toArray())
                    ->whereRaw("COALESCE(p.sale_price, p.price) BETWEEN ? AND ?", [$min, $max])
                    ->pluck('p.id')
                    ->unique();
            }

            $filteredProductIds = $filteredProductIds->intersect($priceFilteredIds);
        }

        if ($filteredProductIds->isEmpty()) {
            return $this->getEmptyResponse();
        }

        // Build products response
        $products = $this->buildProductsResponse($filteredProductIds, $request, $perPage);

        // Build dynamic filters - OPTIMIZED
        $filters = $this->buildOptimizedFilters($category, $allCategoryProductIds, $filteredProductIds, $selectedFilters, $categoryMeasurementPriorities);

        // Get brands - OPTIMIZED
        $brands = $this->getOptimizedBrands($allCategoryProductIds, $filteredProductIds, $request->brand_id ?? []);

        // Calculate price range from ALL category products
        $priceRange = $this->getPriceRange($originalProductIds->toArray());

        return response()->json([
            'success' => true,
            'filters' => $filters,
            'products' => $products,
            'brands' => $brands,
            'price_min' => $priceRange['min'],
            'price_max' => $priceRange['max'],
            'rating_filter' => [
                'filter_name' => 'Rating',
                'filter_type' => 'rating',
                'filter_values' => [5, 4, 3, 2, 1],
            ]
        ]);
    }

    private function applyAttributeFilters($filteredProductIds, $filterConditions, $categoryMeasurementPriorities)
    {
        foreach ($filterConditions as $condition) {
            $attribute = DB::table('attributes')->where('name', $condition['attribute_name'])->first();
            if (!$attribute) {
                continue;
            }

            if ($condition['is_range']) {
                // Handle range filters with unit conversion
                $matchingProductIds = $this->applyRangeFilter($attribute, $condition['values'], $filteredProductIds, $categoryMeasurementPriorities);
            } else {
                // Handle exact value filters
                $matchingProductIds = DB::table('product_attributes')
                    ->where('attribute_id', $attribute->id)
                    ->whereIn('attribute_value', $condition['values'])
                    ->whereIn('product_id', $filteredProductIds)
                    ->pluck('product_id')
                    ->unique();
            }

            $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

            if ($filteredProductIds->isEmpty()) {
                break;
            }
        }

        return $filteredProductIds;
    }

    private function applyRangeFilter($attribute, $ranges, $filteredProductIds, $categoryMeasurementPriorities)
    {
        $allMatchingProductIds = collect();

        $attributeValues = DB::table('product_attributes')
            ->where('attribute_id', $attribute->id)
            ->whereIn('product_id', $filteredProductIds)
            ->get(['product_id', 'attribute_value', 'measurement_unit_id']);

        foreach ($ranges as $range) {
            $min = (float) $range['min'];
            $max = (float) $range['max'];

            $rangeMatches = $attributeValues->filter(function ($item) use ($min, $max, $attribute, $categoryMeasurementPriorities) {
                $convertedValue = $this->convertValueForComparison($attribute->id, $item->attribute_value, $item->measurement_unit_id, $categoryMeasurementPriorities);

                if (is_numeric($convertedValue)) {
                    $numericValue = (float) $convertedValue;
                    return $numericValue >= $min && $numericValue <= $max;
                }

                return false;
            })->pluck('product_id');

            $allMatchingProductIds = $allMatchingProductIds->merge($rangeMatches);
        }

        return $allMatchingProductIds->unique();
    }

    private function convertValueForComparison($attributeId, $originalValue, $originalUnitId, $categoryMeasurementPriorities)
    {
        $originalValue = trim($originalValue);

        // Get attribute measurement configuration
        $attributeMeasurement = DB::table('attribute_measurements as am')
            ->join('measurement_units as mu', 'mu.id', '=', 'am.measurement_unit_id')
            ->join('measurement_types as mt', 'mt.id', '=', 'mu.measurement_type_id')
            ->where('am.attribute_id', $attributeId)
            ->select('mu.*', 'mt.name as measurement_type_name')
            ->first();

        if (!$attributeMeasurement) {
            // No measurement config - extract numeric value only
            if (is_numeric($originalValue)) {
                return (float) $originalValue;
            }
            if (preg_match('/^(\d+(?:\.\d+)?)\s*/', $originalValue, $matches)) {
                return (float) $matches[1];
            }
            return $originalValue;
        }

        // Extract numeric value
        $numericValue = null;
        $originalUnit = null;

        if (is_numeric($originalValue)) {
            $numericValue = (float) $originalValue;
            // Get unit from originalUnitId or attribute measurement
            $originalUnit = $originalUnitId ?
                DB::table('measurement_units')->where('id', $originalUnitId)->first() :
                $attributeMeasurement;
        } elseif (preg_match('/^(\d+(?:\.\d+)?)\s*([a-zA-Z°]+)?$/', $originalValue, $matches)) {
            $numericValue = (float) $matches[1];
            $unitSymbol = $matches[2] ?? '';

            if ($unitSymbol && !$originalUnitId) {
                // Try to find unit by symbol
                $originalUnit = DB::table('measurement_units as mu')
                    ->join('measurement_types as mt', 'mt.id', '=', 'mu.measurement_type_id')
                    ->where('mu.symbol', $unitSymbol)
                    ->where('mt.name', $attributeMeasurement->measurement_type_name)
                    ->select('mu.*')
                    ->first();
            } else {
                $originalUnit = $originalUnitId ?
                    DB::table('measurement_units')->where('id', $originalUnitId)->first() :
                    $attributeMeasurement;
            }
        }

        if (!$numericValue || !$originalUnit) {
            return $originalValue;
        }

        // Get category priority for this measurement type
        $measurementTypeName = strtolower($attributeMeasurement->measurement_type_name);
        $categoryPriority = $categoryMeasurementPriorities->get($measurementTypeName);

        if (!$categoryPriority || $originalUnit->id == $categoryPriority->primary_unit_id) {
            return $numericValue; // No conversion needed
        }

        // Perform actual conversion
        try {
            if (function_exists('convert_unit')) {
                $convertedValue = convert_unit(
                    $measurementTypeName,
                    $numericValue,
                    $originalUnit->symbol,
                    $categoryPriority->primary_symbol
                );

                if (is_numeric($convertedValue) && $convertedValue !== false) {
                    return $this->roundByMeasurementType($measurementTypeName, $convertedValue);
                }
            }
        } catch (Exception $e) {
            \Log::warning("Unit conversion failed for attribute {$attributeId}: {$originalValue}. Error: " . $e->getMessage());
        }

        return $numericValue; // Fallback to original numeric value
    }

    private function buildOptimizedFilters($category, $allCategoryProductIds, $filteredProductIds, $selectedFilters, $categoryMeasurementPriorities)
    {
        // Get configured attributes for this category
        $attributeIds = $this->getCategoryAttributeIds($category->id);

        if (empty($attributeIds)) {
            return [];
        }

        // DEBUG: Log category measurement priorities
        \Log::info("Category Measurement Priorities:", $categoryMeasurementPriorities->toArray());

        // OPTIMIZATION: Single query to get all attribute values with details
        $allAttributeData = DB::table('product_attributes as pa')
            ->join('attributes as a', 'a.id', '=', 'pa.attribute_id')
            ->leftJoin('attribute_measurements as am', 'am.attribute_id', '=', 'a.id')
            ->leftJoin('measurement_units as mu', 'mu.id', '=', 'pa.measurement_unit_id')
            ->leftJoin('measurement_types as mt', 'mt.id', '=', 'mu.measurement_type_id')
            ->whereIn('pa.attribute_id', $attributeIds)
            ->whereIn('pa.product_id', $allCategoryProductIds)
            ->select([
                'a.id as attribute_id',
                'a.name as attribute_name',
                'a.type as attribute_type',
                'pa.product_id',
                'pa.attribute_value',
                'pa.measurement_unit_id',
                'mu.symbol as unit_symbol',
                'mt.name as measurement_type',
                DB::raw('CASE WHEN am.attribute_id IS NOT NULL THEN 1 ELSE 0 END as has_measurement')
            ])
            ->orderBy('a.name')
            ->orderBy('pa.attribute_value')
            ->get();

        // DEBUG: Log raw data for capacity attribute
        $capacityData = $allAttributeData->where('attribute_name', 'Capacity');
        if ($capacityData->count() > 0) {
            \Log::info("RAW CAPACITY DATA:", $capacityData->take(10)->toArray());
        }

        // Group by attribute
        $attributeGroups = $allAttributeData->groupBy('attribute_name');

        $filters = [];

        foreach ($attributeGroups as $attributeName => $attributeValues) {
            $firstValue = $attributeValues->first();
            $hasMeasurementConfig = (bool) $firstValue->has_measurement;

            // DEBUG: Log processing for Capacity
            if ($attributeName === 'Capacity') {
                \Log::info("Processing Capacity - Has measurement config: " . ($hasMeasurementConfig ? 'YES' : 'NO'));
                \Log::info("Sample values:", $attributeValues->take(5)->toArray());
            }

            // Convert and analyze values
            $processedValues = $this->processAttributeValues($attributeValues, $filteredProductIds, $categoryMeasurementPriorities);

            // DEBUG: Log processed values for Capacity
            if ($attributeName === 'Capacity') {
                \Log::info("PROCESSED CAPACITY VALUES:", [
                    'unique_values_count' => count($processedValues['unique_values']),
                    'sample_unique_values' => array_slice($processedValues['unique_values'], 0, 5, true),
                    'unique_numeric_values' => array_slice($processedValues['unique_numeric_values'], 0, 10, true)
                ]);
            }

            if (empty($processedValues['unique_values'])) {
                continue;
            }

            // Decide between range and fixed filters
            $shouldCreateRange = $this->shouldCreateRangeFilter($processedValues, $hasMeasurementConfig);

            if ($shouldCreateRange) {
                $ranges = $this->generateOptimizedRanges($processedValues, $filteredProductIds, $attributeName, $selectedFilters);
                if (!empty($ranges)) {
                    // DEBUG: Log ranges for Capacity
                    if ($attributeName === 'Capacity') {
                        \Log::info("CAPACITY RANGES GENERATED:", $ranges);
                    }

                    $filters[] = [
                        'specification_name' => $attributeName,
                        'specification_type' => 'range',
                        'specification_value' => $ranges,
                    ];
                    continue;
                }
            }

            // Create fixed value filter
            $valueMap = $this->createFixedValueFilter($processedValues, $filteredProductIds, $selectedFilters[$attributeName] ?? []);

            if (!empty($valueMap)) {
                $filters[] = [
                    'specification_name' => $attributeName,
                    'specification_type' => 'fixed',
                    'specification_value' => $valueMap,
                ];
            }
        }

        return $filters;
    }

    private function shouldCreateRangeFilter($processedValues, $hasMeasurementConfig)
    {
        // Only create ranges for measurement attributes with numeric values
        if (!$hasMeasurementConfig || !$processedValues['all_numeric']) {
            return false;
        }

        $uniqueCount = count($processedValues['unique_numeric_values']);

        // Don't create ranges for very few unique values (like the voltage example)
        if ($uniqueCount < 4) {
            return false;
        }

        // Check if values are too similar (like 115, 115, 115, 120)
        $values = array_values($processedValues['unique_numeric_values']);
        sort($values);

        $range = max($values) - min($values);
        $avgValue = array_sum($values) / count($values);

        // If range is less than 20% of average value and we have few unique values, don't create ranges
        if ($range < ($avgValue * 0.2) && $uniqueCount < 6) {
            return false;
        }

        return true;
    }

    private function processAttributeValues($attributeValues, $filteredProductIds, $categoryMeasurementPriorities)
    {
        $uniqueDisplayValues = [];
        $uniqueNumericValues = [];
        $allNumeric = true;

        // Get attribute measurement info once
        $firstItem = $attributeValues->first();
        $attributeId = $firstItem->attribute_id;

        $attributeMeasurement = DB::table('attribute_measurements as am')
            ->join('measurement_units as mu', 'mu.id', '=', 'am.measurement_unit_id')
            ->join('measurement_types as mt', 'mt.id', '=', 'mu.measurement_type_id')
            ->where('am.attribute_id', $attributeId)
            ->select('mu.*', 'mt.name as measurement_type_name')
            ->first();

        $categoryPriority = null;
        if ($attributeMeasurement) {
            $measurementTypeName = strtolower($attributeMeasurement->measurement_type_name);
            $categoryPriority = $categoryMeasurementPriorities->get($measurementTypeName);
        }

        // If no category priority is set, just process values as-is
        if (!$categoryPriority) {
            foreach ($attributeValues as $item) {
                $originalValue = trim($item->attribute_value);
                $displayValue = $originalValue;
                $numericValue = null;

                if ($item->unit_symbol) {
                    $displayValue = $originalValue . ' ' . $item->unit_symbol;
                }

                if (is_numeric($originalValue)) {
                    $numericValue = (float) $originalValue;
                } elseif (preg_match('/^(\d+(?:\.\d+)?)\s*/', $originalValue, $matches)) {
                    $numericValue = (float) $matches[1];
                } else {
                    $allNumeric = false;
                }

                if (!isset($uniqueDisplayValues[$displayValue])) {
                    $uniqueDisplayValues[$displayValue] = [
                        'original_value' => $originalValue,
                        'display_value' => $displayValue,
                        'numeric_value' => $numericValue,
                        'unit_symbol' => $item->unit_symbol ?? '',
                        'product_ids' => []
                    ];
                }

                $uniqueDisplayValues[$displayValue]['product_ids'][] = $item->product_id;

                if ($numericValue !== null) {
                    $uniqueNumericValues[$numericValue] = $numericValue;
                }
            }

            return [
                'unique_values' => $uniqueDisplayValues,
                'unique_numeric_values' => $uniqueNumericValues,
                'all_numeric' => $allNumeric
            ];
        }

        // FORCE conversion to primary unit for ALL values
        $targetUnitSymbol = $categoryPriority->primary_symbol;
        $measurementTypeName = strtolower($attributeMeasurement->measurement_type_name);

        foreach ($attributeValues as $item) {
            $originalValue = trim($item->attribute_value);

            // Extract numeric value and original unit
            $numericValue = null;
            $originalUnitSymbol = null;

            if (is_numeric($originalValue)) {
                $numericValue = (float) $originalValue;
                // Get unit from measurement_unit_id or unit_symbol
                if ($item->measurement_unit_id) {
                    $unitDetails = DB::table('measurement_units')->where('id', $item->measurement_unit_id)->first();
                    $originalUnitSymbol = $unitDetails ? $unitDetails->symbol : null;
                } else {
                    $originalUnitSymbol = $item->unit_symbol;
                }
            } elseif (preg_match('/^(\d+(?:\.\d+)?)\s*([a-zA-Z°]+)?\s*$/', $originalValue, $matches)) {
                $numericValue = (float) $matches[1];
                $originalUnitSymbol = $matches[2] ?? $item->unit_symbol ?? null;
            } else {
                $allNumeric = false;
                // Non-numeric value - keep as is
                $displayValue = $originalValue;
                if (!isset($uniqueDisplayValues[$displayValue])) {
                    $uniqueDisplayValues[$displayValue] = [
                        'original_value' => $originalValue,
                        'display_value' => $displayValue,
                        'numeric_value' => $originalValue,
                        'unit_symbol' => '',
                        'product_ids' => []
                    ];
                }
                $uniqueDisplayValues[$displayValue]['product_ids'][] = $item->product_id;
                continue;
            }

            // Convert to target unit
            $convertedValue = $numericValue;

            if ($originalUnitSymbol && $originalUnitSymbol !== $targetUnitSymbol) {
                try {
                    if (function_exists('convert_unit')) {
                        $converted = convert_unit($measurementTypeName, $numericValue, $originalUnitSymbol, $targetUnitSymbol);

                        if (is_numeric($converted) && $converted !== false && $converted > 0) {
                            $convertedValue = $this->roundByMeasurementType($measurementTypeName, $converted);
                            \Log::info("CONVERTED: {$numericValue} {$originalUnitSymbol} → {$convertedValue} {$targetUnitSymbol}");
                        } else {
                            \Log::warning("CONVERSION FAILED: {$numericValue} {$originalUnitSymbol} to {$targetUnitSymbol} returned: " . var_export($converted, true));
                        }
                    } else {
                        \Log::error("convert_unit function not found!");
                    }
                } catch (Exception $e) {
                    \Log::error("CONVERSION ERROR: {$e->getMessage()}");
                }
            }

            // Always use target unit symbol in display
            $displayValue = $convertedValue . ' ' . $targetUnitSymbol;

            if (!isset($uniqueDisplayValues[$displayValue])) {
                $uniqueDisplayValues[$displayValue] = [
                    'original_value' => $originalValue,
                    'display_value' => $displayValue,
                    'numeric_value' => $convertedValue,
                    'unit_symbol' => $targetUnitSymbol,
                    'product_ids' => []
                ];
            }

            $uniqueDisplayValues[$displayValue]['product_ids'][] = $item->product_id;
            $uniqueNumericValues[$convertedValue] = $convertedValue;
        }

        return [
            'unique_values' => $uniqueDisplayValues,
            'unique_numeric_values' => $uniqueNumericValues,
            'all_numeric' => $allNumeric
        ];
    }

    private function createFixedValueFilter($processedValues, $filteredProductIds, $selectedValues)
    {
        $valueMap = [];

        foreach ($processedValues['unique_values'] as $displayValue => $data) {
            $productCount = count(array_intersect($data['product_ids'], $filteredProductIds->toArray()));

            if ($productCount > 0) {
                $valueMap[] = [
                    'value' => $data['original_value'],
                    'display_value' => $displayValue,
                    'converted_value' => $data['numeric_value'] ?? $data['original_value'],
                    'unit_symbol' => $data['unit_symbol'],
                    'product_count' => $productCount,
                    'display_with_count' => $displayValue . ' (' . $productCount . ')',
                ];
            }
        }

        // Add selected values that might not be visible
        foreach ($selectedValues as $selectedValue) {
            $exists = false;
            foreach ($valueMap as $item) {
                if ($item['value'] == $selectedValue) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $valueMap[] = [
                    'value' => $selectedValue,
                    'display_value' => $selectedValue,
                    'converted_value' => $selectedValue,
                    'unit_symbol' => '',
                    'product_count' => 0,
                    'display_with_count' => $selectedValue . ' (0)',
                    'selected' => true,
                ];
            }
        }

        // FIXED: Sort numerically for numeric values, alphabetically for text
        usort($valueMap, function ($a, $b) {
            // Extract numeric values for proper sorting
            $aNum = is_numeric($a['converted_value']) ? (float) $a['converted_value'] : null;
            $bNum = is_numeric($b['converted_value']) ? (float) $b['converted_value'] : null;

            if ($aNum !== null && $bNum !== null) {
                return $aNum - $bNum; // Ascending numeric sort
            }

            // For non-numeric, sort alphabetically
            return strcmp($a['display_value'], $b['display_value']);
        });

        return $valueMap;
    }

    private function generateOptimizedRanges($processedValues, $filteredProductIds, $attributeName, $selectedFilters)
    {
        $numericValues = array_values($processedValues['unique_numeric_values']);
        sort($numericValues);

        // Convert to integers for cleaner ranges
        $integerValues = array_map(function ($val) {
            return (int) round($val);
        }, $numericValues);

        $integerValues = array_unique($integerValues);
        sort($integerValues);

        // Create fewer, more meaningful ranges
        $rangeCount = min(4, ceil(count($integerValues) / 3));
        $chunkSize = ceil(count($integerValues) / $rangeCount);

        $ranges = [];
        $chunks = array_chunk($integerValues, $chunkSize);

        foreach ($chunks as $chunk) {
            $min = (int) $chunk[0];
            $max = (int) end($chunk);

            // Skip single-value ranges that are identical
            if ($min == $max && count($chunk) == 1 && count($integerValues) > 3) {
                continue;
            }

            $productCount = 0;
            $unitSymbol = '';

            // Count products that fall in this range
            foreach ($processedValues['unique_values'] as $data) {
                if ($data['numeric_value'] !== null) {
                    $roundedValue = (int) round($data['numeric_value']);
                    if ($roundedValue >= $min && $roundedValue <= $max) {
                        $productCount += count(array_intersect($data['product_ids'], $filteredProductIds->toArray()));
                        if (empty($unitSymbol) && !empty($data['unit_symbol'])) {
                            $unitSymbol = $data['unit_symbol'];
                        }
                    }
                }
            }

            if ($productCount > 0) {
                $displayValue = $min == $max ?
                    $min . ($unitSymbol ? ' ' . $unitSymbol : '') :
                    $min . ' - ' . $max . ($unitSymbol ? ' ' . $unitSymbol : '');

                $ranges[] = [
                    'min' => $min,
                    'max' => $max,
                    'product_count' => $productCount,
                    'display_value' => $displayValue,
                    'symbol' => $unitSymbol
                ];
            }
        }

        // Add selected ranges if they don't exist
        $selectedRanges = $selectedFilters[$attributeName] ?? [];
        foreach ($selectedRanges as $selectedRange) {
            if (is_array($selectedRange) && isset($selectedRange['min']) && isset($selectedRange['max'])) {
                $selectedMin = (int) $selectedRange['min'];
                $selectedMax = (int) $selectedRange['max'];

                $rangeExists = false;
                foreach ($ranges as $range) {
                    if ($range['min'] == $selectedMin && $range['max'] == $selectedMax) {
                        $rangeExists = true;
                        break;
                    }
                }

                if (!$rangeExists) {
                    $productCount = 0;
                    $unitSymbol = '';

                    foreach ($processedValues['unique_values'] as $data) {
                        if ($data['numeric_value'] !== null) {
                            $roundedValue = (int) round($data['numeric_value']);
                            if ($roundedValue >= $selectedMin && $roundedValue <= $selectedMax) {
                                $productCount += count(array_intersect($data['product_ids'], $filteredProductIds->toArray()));
                                if (empty($unitSymbol) && !empty($data['unit_symbol'])) {
                                    $unitSymbol = $data['unit_symbol'];
                                }
                            }
                        }
                    }

                    $displayValue = $selectedMin == $selectedMax ?
                        $selectedMin . ($unitSymbol ? ' ' . $unitSymbol : '') :
                        $selectedMin . ' - ' . $selectedMax . ($unitSymbol ? ' ' . $unitSymbol : '');

                    $ranges[] = [
                        'min' => $selectedMin,
                        'max' => $selectedMax,
                        'product_count' => $productCount,
                        'display_value' => $displayValue,
                        'selected' => true,
                        'symbol' => $unitSymbol
                    ];
                }
            }
        }

        // Sort by min value
        usort($ranges, function ($a, $b) {
            return $a['min'] - $b['min'];
        });

        return $ranges;
    }

    private function getCategoryAttributeIds($categoryId)
    {
        $subCategory = DB::table('sub_categories')
            ->where('category_id', $categoryId)
            ->value('attributes_ids');

        if (empty($subCategory)) {
            return [];
        }

        $attributeIds = [];

        if (is_string($subCategory)) {
            $decoded = json_decode($subCategory, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && strpos($item, ',') !== false) {
                        $attributeIds = array_merge($attributeIds, explode(',', $item));
                    } else {
                        $attributeIds[] = $item;
                    }
                }
            } else {
                $attributeIds = explode(',', $subCategory);
            }
        } elseif (is_array($subCategory)) {
            $attributeIds = $subCategory;
        }

        return array_filter(array_map(function ($id) {
            return is_numeric(trim($id)) ? intval(trim($id)) : null;
        }, $attributeIds));
    }

    private function getOptimizedBrands($allCategoryProductIds, $filteredProductIds, $selectedBrandIds)
    {
        return DB::table('ec_products as p')
            ->join('ec_brands as b', 'b.id', '=', 'p.brand_id')
            ->whereIn('p.id', $allCategoryProductIds)
            ->where('p.status', 'published')
            ->select('b.id', 'b.name', DB::raw('COUNT(CASE WHEN p.id IN (' . implode(',', $filteredProductIds->toArray()) . ') THEN 1 END) as product_count'))
            ->groupBy('b.id', 'b.name')
            ->orderBy('b.name')
            ->get()
            ->map(function ($brand) use ($selectedBrandIds) {
                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'product_count' => (int) $brand->product_count,
                    'display_name' => $brand->name . ' (' . $brand->product_count . ')',
                    'is_selected' => in_array($brand->id, $selectedBrandIds)
                ];
            })
            ->toArray();
    }

    private function getPriceRange($productIds)
    {
        $supplierPriceRange = DB::table('product_suppliers')
            ->whereIn('product_id', $productIds)
            ->selectRaw('MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as min_price,
                    MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as max_price')
            ->first();

        if ($supplierPriceRange && $supplierPriceRange->min_price > 0) {
            return [
                'min' => (float) $supplierPriceRange->min_price,
                'max' => (float) $supplierPriceRange->max_price,
            ];
        }

        $productPriceRange = DB::table('ec_products')
            ->whereIn('id', $productIds)
            ->where('status', 'published')
            ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
            ->first();

        return [
            'min' => $productPriceRange ? (float) $productPriceRange->min_price : 0,
            'max' => $productPriceRange ? (float) $productPriceRange->max_price : 0,
        ];
    }

    private function buildProductsResponse($filteredProductIds, $request, $perPage)
    {
        $products = Product::whereIn('ec_products.id', $filteredProductIds)
            ->where('ec_products.status', 'published')
            ->with([
                'currency',
                'reviews',
                'productSuppliers',
                'brand',
                'seoUrl',
                'productAttributes' => function ($query) {
                    $query->whereHas('attributeDetails', function ($q) {
                        $q->whereIn('name', ['Units per Case', 'Pack Type']);
                    });
                }
            ]);


        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortByType = $request->input('sort_by_type', 'desc');

        if ($request->has('price_order')) {
            $priceOrder = $request->input('price_order');
            $sortByType = $priceOrder === 'high_to_low' ? 'desc' : 'asc';
            $sortBy = 'price';
        }

        if ($sortBy == 'price') {
            $products = $products
                ->leftJoin('product_suppliers as ps', 'ec_products.id', '=', 'ps.product_id')
                ->select('ec_products.*', DB::raw('MIN(CASE WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 THEN ps.sale_price ELSE ps.price END) as best_price'))
                ->groupBy('ec_products.id')
                ->orderBy('best_price', $sortByType);
        } else {
            $orderColumn = in_array($sortBy, ['created_at', 'updated_at', 'name', 'status']) ? "ec_products.{$sortBy}" : $sortBy;
            $products = $products->orderBy($orderColumn, $sortByType);
        }

        $paginatedProducts = $products->paginate($perPage);

        // Product formatting (keeping your existing logic)
        $wishlistProductIds = auth()->check() ?
            \App\Models\Wishlist::where('user_id', auth()->id())->pluck('product_id')->toArray() : [];

        $modifiedProducts = $paginatedProducts->getCollection()->map(function ($product) use ($wishlistProductIds) {
            // Your existing product formatting logic here - keeping it the same
            $totalReviews = $product->reviews->count();
            $avgRating = $totalReviews > 0 ? round($product->reviews->avg('star')) : null;
            $cleanedImages = is_string($product->images) ? json_decode($product->images, true) : (array) $product->images;
            $cleanedAlt = is_string($product->alt_tags) ? json_decode($product->alt_tags, true) : (array) $product->alt_tags;
            $firstSupplier = $product->productSuppliers->first();
            $leftStock = $firstSupplier?->inventory ?? 0;

            $sellingType = null;
            if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
                $fullValue = $product->sellingUnitAttribute->attribute_value;
                $attributeUnit = strpos($fullValue, '/') !== false ? trim(explode('/', $fullValue)[1]) : $fullValue;
                $sellingType = [
                    'attribute_value' => $product->sellingUnitAttribute->attribute_value,
                    'attribute_value_unit' => $attributeUnit,
                ];
            }

            $unitsPerCase = null;
            $packType = null;
            if (!empty($product->productAttributes)) {
                $unitsPerCase = $product->productAttributes->first(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
                $packType = $product->productAttributes->first(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');
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
                'category_url' => $product->category_url(),
                'parent_category_url' => $product->parent_category_url(),
                'alt_tags' => $cleanedAlt,
                'url' => $product->seoUrl?->url ?? null,
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
                'min_quantity' => $firstSupplier->min_quantity ?? 0,
                'is_fixed' => $firstSupplier->is_fixed ?? 0,
                'quote_available' => $product->quote_available ?? null,
                 'isRequired' => $product->isRequired,
            ];
        });

        $paginatedProducts->setCollection($modifiedProducts);
        return $paginatedProducts;
    }

    private function roundByMeasurementType($measurementType, $value)
    {
        switch (strtolower($measurementType)) {
            case 'length':
            case 'mass':
            case 'weight':
            case 'volume':
                return $value < 10 ? round($value, 2) : round($value);
            case 'voltage':
            case 'current':
            case 'power':
            case 'frequency':
                return $value < 100 ? round($value, 1) : round($value);
            case 'temperature':
                return round($value, 1);
            case 'pressure':
            case 'speed':
            case 'velocity':
                return round($value);
            default:
                return round($value, 2);
        }
    }

    // Helper method to get all child category IDs recursively - CACHED
    private function getAllChildCategoryIds($categoryId)
    {
        static $cache = [];

        if (isset($cache[$categoryId])) {
            return $cache[$categoryId];
        }

        $childIds = DB::table('categories')
            ->where('parent_id', $categoryId)
            ->pluck('id')
            ->toArray();

        $allChildIds = $childIds;

        foreach ($childIds as $childId) {
            $grandChildIds = $this->getAllChildCategoryIds($childId);
            $allChildIds = array_merge($allChildIds, $grandChildIds);
        }

        $cache[$categoryId] = array_unique($allChildIds);
        return $cache[$categoryId];
    }

    // Helper method for empty response
    private function getEmptyResponse()
    {
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
        ])->header('Cache-Control', 'public, max-age=86400');
    }

    // public function getSpecificationFilters1(Request $request)
//     {
//         // Validation
//         $validator = Validator::make($request->all(), [
//             'category_id' => 'required|string',
//             'filters' => 'nullable|array',
//             'price_min' => 'nullable|numeric|min:0',
//             'price_max' => 'nullable|numeric|min:0',
//             'price_order' => 'nullable|in:high_to_low,low_to_high',
//             'brand_id' => 'nullable|array',
//             'brand_id.*' => 'integer',
//             'rating' => 'nullable|numeric|min:1|max:5',
//         ]);

    //         if ($validator->fails()) {
//             return response()->json(['success' => false, 'message' => $validator->errors()], 400);
//         }

    //         $perPage = $request->get('per_page', 10);
//         $categoryIdentifier = $request->input('category_id');

    //         // Use the same logic as your working function - check seoUrl relationship
//         $category = Category::where('id', $categoryIdentifier)
//             ->orWhere('slug', $categoryIdentifier)
//             ->orWhereHas('seoUrl', function($q) use ($categoryIdentifier) {
//                 $q->where('url', $categoryIdentifier);
//             })
//             ->first();

    //         if (!$category) {
//             return response()->json(['success' => false, 'message' => 'Category does not exist.'], 400);
//         }

    //         // Get category measurement unit priorities
//         $categoryMeasurementPriorities = DB::table('category_measurement_unit_priorities as cmup')
//             ->join('measurement_types as mt', 'mt.id', '=', 'cmup.measurement_type_id')
//             ->join('measurement_units as mu_primary', 'mu_primary.id', '=', 'cmup.measurement_unit_primary_id')
//             ->where('cmup.category_id', $category->id)
//             ->select('mt.name as measurement_type', 'mu_primary.name as primary_unit', 'mu_primary.symbol as primary_symbol')
//             ->get()
//             ->keyBy('measurement_type');

    //         // Helper function to round values appropriately by measurement type - MOVED UP
//         $roundByMeasurementType = function($measurementType, $value) {
//             switch (strtolower($measurementType)) {
//                 case 'length':
//                 case 'mass':
//                 case 'weight':
//                 case 'volume':
//                     return $value < 10 ? round($value, 2) : round($value);
//                 case 'voltage':
//                 case 'current':
//                 case 'power':
//                 case 'frequency':
//                     return $value < 100 ? round($value, 1) : round($value);
//                 case 'temperature':
//                     return round($value, 1);
//                 case 'pressure':
//                 case 'speed':
//                 case 'velocity':
//                     return round($value);
//                 default:
//                     return round($value, 2);
//             }
//         };

    //         $convertAttributeValue = function($attributeName, $originalValue, $attributeType = null) use ($categoryMeasurementPriorities, $roundByMeasurementType) {
//             $originalValue = trim($originalValue);

    //             // ONLY apply conversion logic if attribute type is 'measurement'
//             if ($attributeType !== 'measurement') {
//                 return [
//                     'converted_value' => $originalValue,
//                     'unit' => null,
//                     'symbol' => '',
//                     'display_value' => $originalValue,
//                     'original_value' => $originalValue,
//                     'conversion_applied' => false
//                 ];
//             }

    //             // For measurement type attributes, find matching measurement type
//             $matchedMeasurementType = null;
//             $matchedPriority = null;

    //             foreach ($categoryMeasurementPriorities as $measurementType => $priority) {
//                 $shouldConvert = false;

    //                 switch (strtolower($measurementType)) {
//                     case 'length':
//                         $shouldConvert = (
//                             stripos($attributeName, 'length') !== false ||
//                             stripos($attributeName, 'height') !== false ||
//                             stripos($attributeName, 'width') !== false ||
//                             stripos($attributeName, 'depth') !== false ||
//                             stripos($attributeName, 'diameter') !== false ||
//                             stripos($attributeName, 'dimension') !== false ||
//                             stripos($attributeName, 'size') !== false
//                         );
//                         break;
//                     case 'mass':
//                     case 'weight':
//                         $shouldConvert = (
//                             stripos($attributeName, 'weight') !== false ||
//                             stripos($attributeName, 'mass') !== false
//                         );
//                         break;
//                     case 'volume':
//                         $shouldConvert = (
//                             stripos($attributeName, 'volume') !== false ||
//                             stripos($attributeName, 'capacity') !== false
//                         );
//                         break;
//                     case 'voltage':
//                     case 'electric_potential':
//                         $shouldConvert = (
//                             stripos($attributeName, 'voltage') !== false ||
//                             stripos($attributeName, 'volt') !== false
//                         );
//                         break;
//                     case 'current':
//                     case 'electric_current':
//                         $shouldConvert = (
//                             stripos($attributeName, 'current') !== false ||
//                             stripos($attributeName, 'ampere') !== false ||
//                             stripos($attributeName, 'amp') !== false
//                         );
//                         break;
//                     case 'power':
//                         $shouldConvert = (
//                             stripos($attributeName, 'power') !== false ||
//                             stripos($attributeName, 'watt') !== false ||
//                             stripos($attributeName, 'horsepower') !== false
//                         );
//                         break;
//                     case 'frequency':
//                         $shouldConvert = (
//                             stripos($attributeName, 'frequency') !== false ||
//                             stripos($attributeName, 'freq') !== false ||
//                             stripos($attributeName, 'hz') !== false ||
//                             stripos($attributeName, 'hertz') !== false
//                         );
//                         break;
//                     case 'temperature':
//                         $shouldConvert = (
//                             stripos($attributeName, 'temperature') !== false ||
//                             stripos($attributeName, 'temp') !== false
//                         );
//                         break;
//                     case 'pressure':
//                         $shouldConvert = (
//                             stripos($attributeName, 'pressure') !== false ||
//                             stripos($attributeName, 'psi') !== false ||
//                             stripos($attributeName, 'bar') !== false
//                         );
//                         break;
//                     case 'speed':
//                     case 'velocity':
//                         $shouldConvert = (
//                             stripos($attributeName, 'speed') !== false ||
//                             stripos($attributeName, 'velocity') !== false ||
//                             stripos($attributeName, 'rpm') !== false
//                         );
//                         break;
//                     default:
//                         $shouldConvert = stripos($attributeName, $measurementType) !== false;
//                         break;
//                 }

    //                 if ($shouldConvert) {
//                     $matchedMeasurementType = $measurementType;
//                     $matchedPriority = $priority;
//                     break;
//                 }
//             }

    //             // If no measurement type matched, but still try to extract unit from original value
//             if (!$matchedMeasurementType || !$matchedPriority) {
//                 // Try to extract unit from original value even if no measurement type matched
//                 $hasUnit = preg_match('/^(\d+(?:\/\d+)?(?:\.\d+)?)\s*([a-zA-Z°]+)$/', $originalValue, $matches);

    //                 if ($hasUnit) {
//                     $numericValue = $matches[1];
//                     $originalUnit = $matches[2];

    //                     return [
//                         'converted_value' => $numericValue,
//                         'unit' => $originalUnit,
//                         'symbol' => $originalUnit,
//                         'display_value' => $numericValue . ' ' . $originalUnit,
//                         'original_value' => $originalValue,
//                         'conversion_applied' => false
//                     ];
//                 }

    //                 // No unit found in original value - for measurement type attributes, return as-is
//                 // Don't add units if we don't know what measurement type it should be
//                 return [
//                     'converted_value' => $originalValue,
//                     'unit' => null,
//                     'symbol' => '',
//                     'display_value' => $originalValue,
//                     'original_value' => $originalValue,
//                     'conversion_applied' => false
//                 ];
//             }

    //             // Parse the value to extract numeric part and unit
//             $hasUnit = preg_match('/^(\d+(?:\/\d+)?(?:\.\d+)?)\s*([a-zA-Z°]+)$/', $originalValue, $matches);

    //             if ($hasUnit) {
//                 $numericValue = $matches[1];
//                 $originalUnit = $matches[2];
//                 $targetUnit = $matchedPriority->primary_unit;
//                 $targetSymbol = $matchedPriority->primary_symbol;

    //                 // For values with slashes, preserve the format but standardize unit
//                 if (strpos($numericValue, '/') !== false) {
//                     return [
//                         'converted_value' => $numericValue,
//                         'unit' => $targetUnit,
//                         'symbol' => $targetSymbol,
//                         'display_value' => $numericValue . ' ' . $targetSymbol,
//                         'original_value' => $originalValue,
//                         'conversion_applied' => false
//                     ];
//                 } else {
//                     // Try actual conversion
//                     try {
//                         if (function_exists('convert_unit')) {
//                             $convertedValue = convert_unit($matchedMeasurementType, (float)$numericValue, $originalUnit, $targetUnit);

    //                             if (is_numeric($convertedValue) && $convertedValue !== false) {
//                                 $roundedValue = $roundByMeasurementType($matchedMeasurementType, $convertedValue);

    //                                 return [
//                                     'converted_value' => $roundedValue,
//                                     'unit' => $targetUnit,
//                                     'symbol' => $targetSymbol,
//                                     'display_value' => $roundedValue . ' ' . $targetSymbol,
//                                     'original_value' => $originalValue,
//                                     'conversion_applied' => true
//                                 ];
//                             }
//                         }

    //                         // Conversion failed, keep original unit and value EXACTLY as stored
//                         return [
//                             'converted_value' => $originalValue, // Keep full original like "1 gal"
//                             'unit' => $originalUnit,
//                             'symbol' => $originalUnit,
//                             'display_value' => $originalValue, // Keep full original like "1 gal"
//                             'original_value' => $originalValue,
//                             'conversion_applied' => false
//                         ];
//                     } catch (Exception $e) {
//                         \Log::warning("Unit conversion failed for {$attributeName}: {$originalValue}. Error: " . $e->getMessage());

    //                         // Return original value EXACTLY as stored
//                         return [
//                             'converted_value' => $originalValue, // Keep full original like "1 gal"
//                             'unit' => $originalUnit,
//                             'symbol' => $originalUnit,
//                             'display_value' => $originalValue, // Keep full original like "1 gal"
//                             'original_value' => $originalValue,
//                             'conversion_applied' => false
//                         ];
//                     }
//                 }
//             } else {
//                 // No unit detected in the original value
//                 // For measurement attributes, try to add the primary unit if configured
//                 if ($matchedMeasurementType && $matchedPriority) {
//                     return [
//                         'converted_value' => $originalValue,
//                         'unit' => $matchedPriority->primary_unit,
//                         'symbol' => $matchedPriority->primary_symbol,
//                         'display_value' => $originalValue . ' ' . $matchedPriority->primary_symbol,
//                         'original_value' => $originalValue,
//                         'conversion_applied' => false
//                     ];
//                 }

    //                 return [
//                     'converted_value' => $originalValue,
//                     'unit' => null,
//                     'symbol' => '',
//                     'display_value' => $originalValue,
//                     'original_value' => $originalValue,
//                     'conversion_applied' => false
//                 ];
//             }
//         };

    //         // FIXED: Get products from current category using proper relationship
//         $currentCategoryProducts = DB::table('product_categories as pc')
//             ->join('ec_products as p', 'p.id', '=', 'pc.product_id')
//             ->where('pc.category_id', $category->id)
//             ->where('p.status', 'published')
//             ->pluck('p.id')
//             ->toArray();

    //         // FIXED: Get all child categories recursively
//         $allChildCategoryIds = $this->getAllChildCategoryIds($category->id);

    //         // Get products from all child categories
//         $childProductIds = [];
//         if (!empty($allChildCategoryIds)) {
//             $childProductIds = DB::table('product_categories as pc')
//                 ->join('ec_products as p', 'p.id', '=', 'pc.product_id')
//                 ->whereIn('pc.category_id', $allChildCategoryIds)
//                 ->where('p.status', 'published')
//                 ->pluck('p.id')
//                 ->toArray();
//         }

    //         // Combine products from current category and all child categories
//         $allCategoryProductIds = array_unique(array_merge($currentCategoryProducts, $childProductIds));

    //         if (empty($allCategoryProductIds)) {
//             return $this->getEmptyResponse();
//         }

    //         // Start with all category product IDs
//         $filteredProductIds = collect($allCategoryProductIds);

    //         // Group filters by specification name
//         $groupedFilters = [];
//         $rangeFiltersByAttribute = [];
//         $selectedFilters = [];

    //         if ($request->has('filters') && is_array($request->filters)) {
//             foreach ($request->filters as $filter) {
//                 if (!isset($filter['specification_name']) || !isset($filter['specification_value']) || empty($filter['specification_value'])) {
//                     continue;
//                 }

    //                 $specName = $filter['specification_name'];
//                 $specValues = is_array($filter['specification_value']) ? $filter['specification_value'] : [$filter['specification_value']];

    //                 $selectedFilters[$specName] = $specValues;

    //                 $isRangeFilter = false;

    //                 if (is_array($filter['specification_value']) &&
//                     isset($filter['specification_value']['start']) &&
//                     isset($filter['specification_value']['end'])) {

    //                     $isRangeFilter = true;
//                     if (!isset($rangeFiltersByAttribute[$specName])) {
//                         $rangeFiltersByAttribute[$specName] = [];
//                     }
//                     $rangeFiltersByAttribute[$specName][] = [
//                         'min' => (int)$filter['specification_value']['start'],
//                         'max' => (int)$filter['specification_value']['end']
//                     ];

    //                     $selectedFilters[$specName] = [[
//                         'min' => (int)$filter['specification_value']['start'],
//                         'max' => (int)$filter['specification_value']['end']
//                     ]];
//                 } else {
//                     foreach ($specValues as $value) {
//                         if (is_array($value) && isset($value['min']) && isset($value['max'])) {
//                             $isRangeFilter = true;
//                             if (!isset($rangeFiltersByAttribute[$specName])) {
//                                 $rangeFiltersByAttribute[$specName] = [];
//                             }
//                             $rangeFiltersByAttribute[$specName][] = $value;
//                         }
//                     }
//                 }

    //                 if (!$isRangeFilter) {
//                     if (!isset($groupedFilters[$specName])) {
//                         $groupedFilters[$specName] = [];
//                     }
//                     $groupedFilters[$specName] = array_merge($groupedFilters[$specName], $specValues);
//                 }
//             }
//         }

    //         // Apply regular attribute filters
//         foreach ($groupedFilters as $specName => $specValues) {
//             $attribute = Attribute::where('name', $specName)->first();
//             if (!$attribute) {
//                 continue;
//             }

    //             $convertedSpecValues = [];
//             foreach ($specValues as $specValue) {
//                 $conversionResult = $convertAttributeValue($specName, $specValue, $attribute->type);
//                 $convertedSpecValues[] = $conversionResult['original_value'];
//             }

    //             $matchingProductIds = DB::table('product_attributes as pa')
//                 ->where('pa.attribute_id', $attribute->id)
//                 ->whereIn('pa.attribute_value', $convertedSpecValues)
//                 ->whereIn('pa.product_id', $filteredProductIds)
//                 ->pluck('pa.product_id')
//                 ->unique();

    //             $filteredProductIds = $filteredProductIds->intersect($matchingProductIds);

    //             if ($filteredProductIds->isEmpty()) {
//                 return $this->getEmptyResponse();
//             }
//         }

    //         // Apply range filters
//         foreach ($rangeFiltersByAttribute as $specName => $ranges) {
//             $attribute = Attribute::where('name', $specName)->first();
//             if (!$attribute) {
//                 continue;
//             }

    //             $productsWithAttribute = DB::table('product_attributes as pa')
//                 ->where('pa.attribute_id', $attribute->id)
//                 ->whereIn('pa.product_id', $filteredProductIds)
//                 ->get(['pa.product_id', 'pa.attribute_value']);

    //             if ($productsWithAttribute->isEmpty()) {
//                 continue;
//             }

    //             $allMatchingProductIds = collect();

    //             foreach ($ranges as $range) {
//                 $min = (int)$range['min'];
//                 $max = (int)$range['max'];

    //                 $rangeMatches = $productsWithAttribute->filter(function($item) use ($min, $max) {
//                     $value = trim($item->attribute_value);

    //                     if (is_numeric($value)) {
//                         $numericValue = (int)round((float)$value);
//                         return $numericValue >= $min && $numericValue <= $max;
//                     }

    //                     if (preg_match('/^(\d+(?:\.\d+)?)\s*[a-zA-Z]*$/', $value, $matches)) {
//                         $numericValue = (int)round((float)$matches[1]);
//                         return $numericValue >= $min && $numericValue <= $max;
//                     }

    //                     return false;
//                 })->pluck('product_id');

    //                 $allMatchingProductIds = $allMatchingProductIds->merge($rangeMatches);
//             }

    //             $filteredProductIds = $filteredProductIds->intersect($allMatchingProductIds->unique());

    //             if ($filteredProductIds->isEmpty()) {
//                 \Log::info("No products match range filter for: $specName");
//                 continue;
//             }
//         }

    //         // Apply brand filter
//         if ($request->has('brand_id') && $request->brand_id) {
//             $brandFilteredIds = Product::whereIn('id', $filteredProductIds)
//                 ->whereIn('brand_id', $request->brand_id)
//                 ->pluck('id');

    //             $filteredProductIds = $filteredProductIds->intersect($brandFilteredIds);

    //             if ($filteredProductIds->isEmpty()) {
//                 return $this->getEmptyResponse();
//             }
//         }

    //         // Apply price filter
//         if ($request->has('price_min') || $request->has('price_max')) {
//             $min = $request->input('price_min', 0);
//             $max = $request->input('price_max', PHP_INT_MAX);

    //             $priceFilteredIds = DB::table('product_suppliers as ps')
//                 ->whereIn('ps.product_id', $filteredProductIds->toArray())
//                 ->where(function($query) use ($min, $max) {
//                     $query->whereRaw("CASE WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0 THEN ps.sale_price ELSE ps.price END BETWEEN ? AND ?", [$min, $max]);
//                 })
//                 ->pluck('ps.product_id')
//                 ->unique();

    //             if ($priceFilteredIds->isEmpty()) {
//                 $priceFilteredIds = DB::table('product_suppliers as ps')
//                     ->whereIn('ps.product_id', $filteredProductIds->toArray())
//                     ->whereRaw("COALESCE(ps.sale_price, ps.price) BETWEEN ? AND ?", [$min, $max])
//                     ->pluck('ps.product_id')
//                     ->unique();

    //                 if ($priceFilteredIds->isEmpty()) {
//                     $priceFilteredIds = DB::table('ec_products as p')
//                         ->whereIn('p.id', $filteredProductIds->toArray())
//                         ->whereRaw("COALESCE(p.sale_price, p.price) BETWEEN ? AND ?", [$min, $max])
//                         ->pluck('p.id')
//                         ->unique();
//                 }
//             }

    //             $filteredProductIds = $filteredProductIds->intersect($priceFilteredIds);

    //             if ($filteredProductIds->isEmpty()) {
//                 return $this->getEmptyResponse();
//             }
//         }

    //         // Apply rating filter
//         if ($request->has('rating') && $request->rating) {
//             $ratingFilteredIds = DB::table('ec_reviews')
//                 ->whereIn('product_id', $filteredProductIds)
//                 ->select('product_id')
//                 ->groupBy('product_id')
//                 ->havingRaw('ROUND(AVG(star)) = ?', [$request->rating])
//                 ->pluck('product_id');

    //             $filteredProductIds = $filteredProductIds->intersect($ratingFilteredIds);

    //             if ($filteredProductIds->isEmpty()) {
//                 return response()->json([
//                     'success' => true,
//                     'message' => 'No products found with ' . $request->rating . ' star' . ($request->rating == 1 ? '' : 's') . ' rating.',
//                     'filters' => [],
//                     'products' => [],
//                     'brands' => [],
//                     'price_min' => 0,
//                     'price_max' => 0,
//                     'rating_filter' => [
//                         'filter_name' => 'Rating',
//                         'filter_type' => 'rating',
//                         'filter_values' => [5, 4, 3, 2, 1],
//                     ]
//                 ]);
//             }
//         }

    //         // Fetch products with proper sorting
//         $products = Product::whereIn('id', $filteredProductIds)
//             ->where('status', 'published')
//             ->with(['currency', 'reviews', 'productSuppliers', 'brand', 'seoUrl', 'productAttributes' => function ($query) {
//                 $query->whereHas('attributeDetails', function ($q) {
//                     $q->whereIn('name', ['Units per Case', 'Pack Type']);
//                 });
//             }]);

    //         // Apply sorting
//         $sortBy = $request->input('sort_by', 'created_at');
//         $sortByType = $request->input('sort_by_type', 'desc');

    //         if ($request->has('price_order')) {
//             $priceOrder = $request->input('price_order');
//             $sortByType = $priceOrder === 'high_to_low' ? 'desc' : 'asc';
//             $sortBy = 'price';
//         }

    //         if ($sortBy == 'price') {
//             $productIds = $filteredProductIds->toArray();

    //             $products = Product::with(['currency', 'reviews', 'productSuppliers', 'brand', 'productAttributes' => function ($query) {
//                     $query->whereHas('attributeDetails', function ($q) {
//                         $q->whereIn('name', ['Units per Case', 'Pack Type']);
//                     });
//                 }])
//                 ->leftJoin('product_suppliers as ps', 'ec_products.id', '=', 'ps.product_id')
//                 ->select('ec_products.*',
//                     DB::raw('MIN(CASE
//                 WHEN ps.sale_price IS NOT NULL AND ps.sale_price > 0
//                 THEN ps.sale_price
//                 ELSE ps.price
//             END) as best_price')
//         )
//         ->whereIn('ec_products.id', $productIds)
//         ->where('ec_products.status', 'published')
//         ->groupBy('ec_products.id')
//         ->orderBy('best_price', $sortByType);
//         } else {
//             $orderColumn = in_array($sortBy, ['created_at', 'updated_at', 'name', 'status'])
//                 ? "ec_products.{$sortBy}"
//                 : $sortBy;

    //             $products = $products->orderBy($orderColumn, $sortByType);
//         }

    //         $paginatedProducts = $products->paginate($perPage);

    //         // Get wishlist product IDs
//         $wishlistProductIds = auth()->check() ?
//             \App\Models\Wishlist::where('user_id', auth()->id())->pluck('product_id')->toArray() :
//             [];

    //         $modifiedProducts = $paginatedProducts->getCollection()->map(function ($product) use ($wishlistProductIds) {
//             $totalReviews = $product->reviews->count();
//             $avgRating = $totalReviews > 0 ? round($product->reviews->avg('star')) : null;

    //             $cleanedImages = is_string($product->images)
//                 ? json_decode($product->images, true)
//                 : (array) $product->images;

    //             $cleanedAlt= is_string($product->alt_tags)
//                 ? json_decode($product->alt_tags, true)
//                 : (array) $product->alt_tags;

    //             $firstSupplier = $product->productSuppliers->first();
//             $leftStock = $firstSupplier?->inventory ?? 0;

    //             $sellingType = null;
//             if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
//                 $fullValue = $product->sellingUnitAttribute->attribute_value;

    //                 $attributeUnit = strpos($fullValue, '/') !== false
//                     ? trim(explode('/', $fullValue)[1])
//                     : $fullValue;

    //                 $sellingType = [
//                     'attribute_value' => $product->sellingUnitAttribute->attribute_value,
//                     'attribute_value_unit' => $attributeUnit,
//                 ];
//             }

    //             $unitsPerCase = null;
//             $packType = null;

    //             if (!empty($product->productAttributes)) {
//                 $unitsPerCase = $product->productAttributes
//                     ->first(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
//                 $packType = $product->productAttributes
//                     ->first(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');
//             }

    //             $basePrice = null;
//             if ($firstSupplier) {
//                 $basePrice = ($firstSupplier->sale_price > 0) ? $firstSupplier->sale_price : $firstSupplier->price;
//             }
//             $perUnitPrice = null;

    //             if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
//                 $unitValue = (float) $unitsPerCase->attribute_value;
//                 if ($unitValue > 0) {
//                     $calculated = round($basePrice / $unitValue, 2);
//                     $perUnitPrice = $calculated . ' ' . '/' . ($packType?->attribute_value ?? '');
//                 }
//             }

    //             return [
//                 'id' => $product->id,
//                 'name' => $product->name,
//                 'images' => $cleanedImages,
//                 'category_url' => $product->category_url(),
//                 'parent_category_url' => $product->parent_category_url(),
//                 'alt_tags' => $cleanedAlt,
//                 'url' => $product->seoUrl?->url ?? null,
//                 'video_url' => $product->video_url,
//                 'video_path' => is_array($product->video_path) ? $product->video_path : (json_decode($product->video_path, true) ?: []),
//                 'sku' => $product->sku,
//                 'start_date' => $product->start_date,
//                 'end_date' => $product->end_date,
//                 'currency' => $product->currency?->symbol,
//                 'total_reviews' => $totalReviews,
//                 'avg_rating' => $avgRating,
//                 'leftStock' => $leftStock,
//                 'currency_title' => $product->currency
//                     ? ($product->currency->is_prefix_symbol
//                         ? $product->currency->symbol
//                         : ($product->price . ' ' . $product->currency->symbol))
//                     : $product->price,
//                 'in_wishlist' => in_array($product->id, $wishlistProductIds),
//                 'selling_type' => $sellingType,
//                 'per_unit_price' => $perUnitPrice,
//                 'vendor_sku' => $firstSupplier?->vendor_sku ?? null,
//                 'price' => (float) ($firstSupplier?->price ?? 0),
//                 'sale_price' => (float) ($firstSupplier?->sale_price ?? 0),
//                 'original_price' => (float) ($firstSupplier?->price ?? 0),
//                 'front_sale_price' => (float) ($firstSupplier?->sale_price ?? $firstSupplier?->price ?? 0),
//                 'best_price' => (float) ($firstSupplier?->price ?? 0),
//                 'vendor_id' => $firstSupplier?->vendor_id ?? null,
//                 'map' => $firstSupplier ? (float) $firstSupplier->map : null,
//                 'inventory' => $firstSupplier?->inventory ?? null,
//                 'in_stock' => $firstSupplier?->in_stock ?? null,
//                 'delivery_days' => $firstSupplier?->delivery_days ?? null,
//                 'return_policy' => $firstSupplier?->return_policy ?? null,
//                 'free_shipping' => $firstSupplier?->free_shipping ?? null,
//                 'warranty_information' => $firstSupplier?->warranty_information ?? null,
//             ];
//         });

    //         $paginatedProducts->setCollection($modifiedProducts);

    //         // Build filters - ONLY get attributes specifically configured for this category
//         $filters = [];
//         $allAttributeIds = collect();

    //         // ONLY get from sub_categories table - the specific attributes configured for this category
//         $subCategory = DB::table('sub_categories')
//             ->where('category_id', $category->id)
//             ->first();

    //         if ($subCategory) {
//             $attributeIdsField = null;
//             $attributeIds = [];

    //             if (property_exists($subCategory, 'attributes_ids') || isset($subCategory->attributes_ids)) {
//                 $attributeIdsField = 'attributes_ids';
//             } else if (property_exists($subCategory, 'attributes_jd') || isset($subCategory->attributes_jd)) {
//                 $attributeIdsField = 'attributes_jd';
//             }

    //             if ($attributeIdsField && !empty($subCategory->$attributeIdsField)) {
//                 $attributeIdsValue = $subCategory->$attributeIdsField;

    //                 if (is_string($attributeIdsValue)) {
//                     // First try JSON decode
//                     $attributeIds = json_decode($attributeIdsValue, true);

    //                     // If JSON decode failed, try comma separation
//                     if (json_last_error() !== JSON_ERROR_NONE) {
//                         $attributeIds = explode(',', $attributeIdsValue);
//                     } else if (is_array($attributeIds)) {
//                         // Handle nested arrays or comma-separated strings within JSON
//                         $flattenedIds = [];
//                         foreach ($attributeIds as $id) {
//                             if (is_string($id) && strpos($id, ',') !== false) {
//                                 $flattenedIds = array_merge($flattenedIds, explode(',', $id));
//                             } else {
//                                 $flattenedIds[] = $id;
//                             }
//                         }
//                         $attributeIds = $flattenedIds;
//                     }
//                 } else if (is_array($attributeIdsValue)) {
//                     $attributeIds = $attributeIdsValue;
//                 } else {
//                     $attributeIds = [$attributeIdsValue];
//                 }

    //                 // Clean up and convert to integers
//                 $attributeIds = array_filter(array_map(function($id) {
//                     return is_numeric($id) ? intval($id) : null;
//                 }, (array)$attributeIds));

    //                 // Remove duplicates
//                 $allAttributeIds = collect(array_unique($attributeIds));
//             }
//         }

    //         // ONLY process attributes if they are specifically configured in sub_categories
//         if ($allAttributeIds->isNotEmpty()) {
//             foreach ($allAttributeIds as $attributeId) {
//                 $attribute = Attribute::find($attributeId);
//                 if (!$attribute) {
//                     continue;
//                 }

    //                 $attributeName = $attribute->name;
//                 $attributeType = $attribute->type ?? null; // Get attribute type
//                 $isFilterSelected = isset($selectedFilters[$attributeName]);

    //                 // ONLY get attribute values for THIS specific attribute ID and products in this category
//                 $attributeValues = DB::table('product_attributes as pa')
//                     ->join('attributes as at', 'at.id', '=', 'pa.attribute_id')
//                     ->whereIn('pa.product_id', $allCategoryProductIds)  // Only products in this category
//                     ->where('pa.attribute_id', $attributeId)  // Only THIS specific attribute
//                     ->orderBy('pa.attribute_value', 'asc')
//                     ->select('at.name as attribute_name', 'pa.attribute_value', 'at.id as attribute_id', 'pa.product_id', 'at.type as attribute_type')
//                     ->get();

    //                 if ($attributeValues->count() > 0) {
//                     $convertedAttributeValues = $attributeValues->map(function($item) use ($convertAttributeValue, $attributeName) {
//                         $conversionResult = $convertAttributeValue($attributeName, $item->attribute_value, $item->attribute_type);
//                         return (object)[
//                             'attribute_name' => $item->attribute_name,
//                             'attribute_value' => $item->attribute_value,
//                             'converted_value' => $conversionResult['converted_value'],
//                             'display_value' => $conversionResult['display_value'],
//                             'unit' => $conversionResult['unit'],
//                             'symbol' => $conversionResult['symbol'],
//                             'conversion_applied' => $conversionResult['conversion_applied'],
//                             'attribute_id' => $item->attribute_id,
//                             'product_id' => $item->product_id,
//                             'attribute_type' => $item->attribute_type
//                         ];
//                     });

    //                     $uniqueValues = $convertedAttributeValues->pluck('display_value')->unique()->filter()->values();

    //                     $extractNumericValue = function($value) {
//                         if (preg_match('/^(\d+(?:\.\d+)?)\s*[a-zA-Z]*$/', $value, $matches)) {
//                             return (int)round((float)$matches[1]);
//                         } else if (is_numeric($value)) {
//                             return (int)round((float)$value);
//                         }
//                         return $value;
//                     };

    //                     $numericValues = true;
//                     $cleanedValues = $uniqueValues->map(function($val) use ($extractNumericValue, &$numericValues) {
//                         $cleanedVal = $extractNumericValue($val);
//                         if (!is_numeric($cleanedVal)) {
//                             $numericValues = false;
//                         }
//                         return $cleanedVal;
//                     });

    //                     // Check if it's count-based capacity attributes - REMOVED
//                     // $isCountBasedCapacity = false; // No longer needed since we check attribute type

    //                     // Only apply range logic for measurement-type attributes that are numeric
//                     $isMeasurementAttribute = strtolower($attributeType ?? '') === 'measurement';

    //                     // Generate range filters for numeric values with more than 2 unique values (only for measurement attributes)
//                     if ($numericValues && $cleanedValues->count() > 2 && $isMeasurementAttribute) {
//                         $sorted = $cleanedValues->filter(function($value) {
//                             return is_numeric($value);
//                         })->map(function($val) {
//                             return (int)$val;
//                         })->unique()->sort()->values();

    //                         if ($sorted->count() > 2) {
//                             $chunkCount = min(5, ceil($sorted->count() / 2));
//                             $chunkSize = ceil($sorted->count() / $chunkCount);

    //                             $selectedRanges = isset($selectedFilters[$attributeName]) ? $selectedFilters[$attributeName] : [];

    //                             $ranges = $sorted->chunk($chunkSize)->map(function ($chunk) use ($attributeName, $filteredProductIds, $isFilterSelected, $convertedAttributeValues) {
//                                 $min = (int)$chunk->first();
//                                 $max = (int)$chunk->last();

    //                                 if ($min == $max && $chunk->count() == 1) {
//                                     return null;
//                                 }

    //                                 $matchingConvertedValues = $convertedAttributeValues->filter(function($item) use ($min, $max) {
//                                     $numericValue = is_numeric($item->converted_value) ? (int)round((float)$item->converted_value) : null;
//                                     return $numericValue !== null && $numericValue >= $min && $numericValue <= $max;
//                                 });

    //                                 $productCount = $matchingConvertedValues->whereIn('product_id', $filteredProductIds)->pluck('product_id')->unique()->count();

    //                                 // Get the unit from any matching converted value that has a symbol
//                                 $sampleConvertedValue = $matchingConvertedValues->first(function($item) {
//                                     return !empty($item->symbol);
//                                 });

    //                                 // If no item with symbol found, try to get unit from any matching value
//                                 if (!$sampleConvertedValue) {
//                                     $sampleConvertedValue = $matchingConvertedValues->first();
//                                 }

    //                                 $unit = $sampleConvertedValue ? $sampleConvertedValue->symbol : '';

    //                                 // If still no unit, try to extract from any converted value in this attribute
//                                 if (empty($unit)) {
//                                     $anyValueWithUnit = $convertedAttributeValues->first(function($item) {
//                                         return !empty($item->symbol);
//                                     });
//                                     $unit = $anyValueWithUnit ? $anyValueWithUnit->symbol : '';
//                                 }

    //                                 // Debug what unit we found
//                                 \Log::info("Range {$min}-{$max}: Found unit '{$unit}'");

    //                                 // Include unit in display value for ranges
//                                 $displayValue = $min == $max ? $min . ($unit ? ' ' . $unit : '') : $min . ' - ' . $max . ($unit ? ' ' . $unit : '');

    //                                 return [
//                                     'min' => $min,
//                                     'max' => $max,
//                                     'product_count' => $productCount,
//                                     'display_value' => trim($displayValue),
//                                     'symbol' => $unit
//                                 ];
//                             })->filter(function($range) {
//                                 return $range !== null;
//                             })->values()->toArray();

    //                             // Add selected ranges
//                             foreach ($selectedRanges as $selectedRange) {
//                                 if (is_array($selectedRange) && isset($selectedRange['min']) && isset($selectedRange['max'])) {
//                                     $selectedMin = (int)$selectedRange['min'];
//                                     $selectedMax = (int)$selectedRange['max'];

    //                                     $rangeExists = false;
//                                     foreach ($ranges as $range) {
//                                         if ($range['min'] == $selectedMin && $range['max'] == $selectedMax) {
//                                             $rangeExists = true;
//                                             break;
//                                         }
//                                     }

    //                                     if (!$rangeExists) {
//                                         $matchingConvertedValues = $convertedAttributeValues->filter(function($item) use ($selectedMin, $selectedMax) {
//                                             $numericValue = is_numeric($item->converted_value) ? (int)round((float)$item->converted_value) : null;
//                                             return $numericValue !== null && $numericValue >= $selectedMin && $numericValue <= $selectedMax;
//                                         });

    //                                         $productCount = $matchingConvertedValues->whereIn('product_id', $filteredProductIds)->pluck('product_id')->unique()->count();

    //                                         $sampleConvertedValue = $matchingConvertedValues->first();
//                                         $unit = $sampleConvertedValue ? $sampleConvertedValue->symbol : '';

    //                                         $displayValue = $selectedMin == $selectedMax ? $selectedMin . ($unit ? ' ' . $unit : '') : $selectedMin . ' - ' . $selectedMax . ($unit ? ' ' . $unit : '');

    //                                         $ranges[] = [
//                                             'min' => $selectedMin,
//                                             'max' => $selectedMax,
//                                             'product_count' => $productCount,
//                                             'display_value' => trim($displayValue),
//                                             'selected' => true,
//                                             'symbol' => $unit
//                                         ];
//                                     }
//                                 }
//                             }

    //                             usort($ranges, function($a, $b) {
//                                 return $a['min'] - $b['min'];
//                             });

    //                             if (count($ranges) > 1) {
//                                 $filters[] = [
//                                     'specification_name' => $attributeName,
//                                     'specification_type' => 'range',
//                                     'specification_value' => $ranges,
//                                 ];
//                             }
//                         }
//                     } else {
//                         // For fixed values - show all values (for all attribute types)
//                         $valueCountMap = [];
//                         $selectedValues = isset($selectedFilters[$attributeName]) ? $selectedFilters[$attributeName] : [];

    //                         foreach ($uniqueValues as $displayValue) {
//                             $correspondingItem = $convertedAttributeValues->firstWhere('display_value', $displayValue);

    //                             if (!$correspondingItem) continue;

    //                             $productCount = $convertedAttributeValues
//                                 ->where('display_value', $displayValue)
//                                 ->whereIn('product_id', $filteredProductIds)
//                                 ->pluck('product_id')
//                                 ->unique()
//                                 ->count();

    //                             $valueCountMap[] = [
//                                 'value' => $correspondingItem->attribute_value,
//                                 'display_value' => $displayValue,
//                                 'converted_value' => $correspondingItem->converted_value,
//                                 'unit' => $correspondingItem->unit,
//                                 'symbol' => $correspondingItem->symbol,
//                                 'product_count' => $productCount,
//                                 'display_with_count' => $correspondingItem->display_value . ' (' . $productCount . ')',
//                                 'conversion_applied' => $correspondingItem->conversion_applied,
//                                 'attribute_type' => $correspondingItem->attribute_type
//                             ];
//                         }

    //                         foreach ($selectedValues as $selectedValue) {
//                             $valueExists = false;
//                             foreach ($valueCountMap as $valueCount) {
//                                 if ($valueCount['value'] == $selectedValue) {
//                                     $valueExists = true;
//                                     break;
//                                 }
//                             }

    //                             if (!$valueExists) {
//                                 $conversionResult = $convertAttributeValue($attributeName, $selectedValue, $attributeType);

    //                                 $productCount = $convertedAttributeValues
//                                     ->where('attribute_value', $selectedValue)
//                                     ->whereIn('product_id', $filteredProductIds)
//                                     ->pluck('product_id')
//                                     ->unique()
//                                     ->count();

    //                                 $valueCountMap[] = [
//                                     'value' => $selectedValue,
//                                     'display_value' => $conversionResult['display_value'],
//                                     'converted_value' => $conversionResult['converted_value'],
//                                     'unit' => $conversionResult['unit'],
//                                     'symbol' => $conversionResult['symbol'],
//                                     'product_count' => $productCount,
//                                     'display_with_count' => ($conversionResult['symbol'] ?
//                                     $conversionResult['converted_value'] . ' ' . $conversionResult['symbol'] :
//                                     $conversionResult['display_value']) . ' (' . $productCount . ')',
//                                     'selected' => true,
//                                     'conversion_applied' => $conversionResult['conversion_applied'],
//                                     'attribute_type' => $attributeType
//                                 ];
//                             }
//                         }

    //                         // Sort based on attribute type
//                         if ($isMeasurementAttribute && $numericValues) {
//                             // Sort numerically for measurement attributes
//                             usort($valueCountMap, function($a, $b) {
//                                 $aNumeric = null;
//                                 $bNumeric = null;

    //                                 if (preg_match('/^(\d+(?:\.\d+)?)\s*/', $a['display_value'], $matches)) {
//                                     $aNumeric = (float)$matches[1];
//                                 }
//                                 if (preg_match('/^(\d+(?:\.\d+)?)\s*/', $b['display_value'], $matches)) {
//                                     $bNumeric = (float)$matches[1];
//                                 }

    //                                 if ($aNumeric !== null && $bNumeric !== null) {
//                                     return $aNumeric - $bNumeric;
//                                 }

    //                                 if (is_numeric($a['converted_value']) && is_numeric($b['converted_value'])) {
//                                     return (int)round((float)$a['converted_value']) - (int)round((float)$b['converted_value']);
//                                 }

    //                                 return strcmp($a['display_value'], $b['display_value']);
//                             });
//                         } else {
//                             // Sort numerically if all values are numeric, otherwise alphabetically
//                             usort($valueCountMap, function($a, $b) {
//                                 // Extract numeric values from display_value for sorting
//                                 $aNumeric = null;
//                                 $bNumeric = null;

    //                                 if (preg_match('/^(\d+(?:\.\d+)?)\s*/', $a['display_value'], $matches)) {
//                                     $aNumeric = (float)$matches[1];
//                                 }
//                                 if (preg_match('/^(\d+(?:\.\d+)?)\s*/', $b['display_value'], $matches)) {
//                                     $bNumeric = (float)$matches[1];
//                                 }

    //                                 // If both are numeric, sort numerically
//                                 if ($aNumeric !== null && $bNumeric !== null) {
//                                     return $aNumeric - $bNumeric;
//                                 }

    //                                 // Otherwise sort alphabetically
//                                 return strcmp($a['display_value'], $b['display_value']);
//                             });
//                         }

    //                         // Always add filters if they have values
//                         if (count($valueCountMap) > 0) {
//                             $filters[] = [
//                                 'specification_name' => $attributeName,
//                                 'specification_type' => 'fixed',
//                                 'specification_value' => $valueCountMap,
//                                 'attribute_type' => $attributeType,
//                                 'is_measurement' => $isMeasurementAttribute
//                             ];
//                         }
//                     }
//                 }
//             }
//         }

    //         // Get brands
//         $selectedBrandIds = $request->brand_id ?? [];

    //         $brands = DB::table('ec_products as p')
//             ->join('ec_brands as b', 'b.id', '=', 'p.brand_id')
//             ->whereIn('p.id', $allCategoryProductIds)
//             ->where('p.status', 'published')
//             ->select('b.id', 'b.name')
//             ->groupBy('b.id', 'b.name')
//             ->orderBy('b.name')
//             ->get()
//             ->map(function($brand) use ($filteredProductIds, $selectedBrandIds) {
//                 $productCount = DB::table('ec_products')
//                 ->where('brand_id', $brand->id)
//                 ->whereIn('id', $filteredProductIds->toArray())
//                 ->where('status', 'published')
//                 ->count();

    //                 $isSelected = in_array($brand->id, $selectedBrandIds);

    //                 return [
//                     'id' => $brand->id,
//                     'name' => $brand->name,
//                     'product_count' => $productCount,
//                     'display_name' => $brand->name . ' (' . $productCount . ')',
//                     'is_selected' => $isSelected
//                 ];
//             })
//             ->toArray();

    //         // Get price range
//         $productIdsArray = $filteredProductIds->toArray();

    //         $supplierExists = DB::table('product_suppliers')
//             ->whereIn('product_id', $productIdsArray)
//             ->exists();

    //         if ($supplierExists) {
//             $priceRange = DB::table('product_suppliers')
//                 ->whereIn('product_id', $productIdsArray)
//                 ->where(function($query) {
//                     $query->where('price', '>', 0)
//                         ->orWhere('sale_price', '>', 0);
//                 })
//                 ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
//                 ->first();

    //             if (!$priceRange || ($priceRange->min_price <= 0 && $priceRange->max_price <= 0)) {
//                 $priceRange = DB::table('product_suppliers')
//                     ->whereIn('product_id', $productIdsArray)
//                     ->selectRaw('MIN(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as min_price,
//                                 MAX(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE price END) as max_price')
//                     ->first();
//             }
//         } else {
//             $priceRange = DB::table('ec_products')
//                 ->whereIn('id', $filteredProductIds)
//                 ->where('status', 'published')
//                 ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
//                 ->first();
//         }

    //         $priceMin = $priceRange ? (float)$priceRange->min_price : 0;
//         $priceMax = $priceRange ? (float)$priceRange->max_price : 0;

    //         // Rating filter
//         $ratingFilter = [
//             'filter_name' => 'Rating',
//             'filter_type' => 'rating',
//             'filter_values' => [5, 4, 3, 2, 1],
//         ];

    //         return response()->json([
//             'success' => true,
//             'filters' => $filters,
//             'products' => $paginatedProducts,
//             'brands' => $brands,
//             'price_min' => $priceMin,
//             'price_max' => $priceMax,
//             'rating_filter' => $ratingFilter,
//             'category_measurement_priorities' => $categoryMeasurementPriorities->toArray(),
//             'debug_info' => [
//                 'total_category_products' => count($allCategoryProductIds),
//                 'filtered_products_count' => $filteredProductIds->count(),
//                 'child_categories_count' => count($allChildCategoryIds),
//                 'total_attributes_found' => $allAttributeIds->count()
//             ]
//         ]);
//     }

    // // Helper method to get all child category IDs recursively
// private function getAllChildCategoryIds($categoryId)
//     {
//         $childIds = DB::table('categories')
//             ->where('parent_id', $categoryId)
//             ->pluck('id')
//             ->toArray();

    //         $allChildIds = $childIds;

    //         foreach ($childIds as $childId) {
//             $grandChildIds = $this->getAllChildCategoryIds($childId);
//             $allChildIds = array_merge($allChildIds, $grandChildIds);
//         }

    //         return array_unique($allChildIds);
//     }

    // private function getEmptyResponse()
//     {
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
//         ])->header('Cache-Control', 'public, max-age=86400');
//     }



    /**
     * @OA\Get(
     *     path="/api/frontend/home-categories",
     *     tags={"Frontend-Categories"},
     *     summary="Fetch a limited set of parent and child categories",
     *     description="Returns up to 14 categories including parent and child, with product count and image URL.",
     *     @OA\Response(response=200, description="Categories fetched successfully", @OA\MediaType(mediaType="application/json")),
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
// public function fetchCategories(Request $request)
// {
// 	$limit = 15;

    // 	$allowedNames = [
// 		'Reach-In Refrigerators',
// 		'Pizza Prep Tables',
// 		'Work Top Refrigerators',
// 		'Commercial Chef Base',
// 		'Undercounter Refrigerators',
// 		'Beer Dispensers',
// 		'Back Bar Coolers',
// 		'Glass Chillers and Frosters',
// 		'Milk Cooler',
// 		'Commercial Grills & Griddles',
// 		'Commercial Gas Fryers',
// 		'Commercial Gas And Electric Range',
// 		'Deck Ovens',
// 		'Commercial Food Processors',
// 		'Planetary Mixer',
// 		'Commercial Espresso Machines',
// 	];

    // 	// Fetch matching leaf categories
// 	$leafCategories = Category::where('status', 'published')
// 		->whereDoesntHave('children')
// 		->whereIn('name', $allowedNames)
// 		->with(['seoUrl:id,relational_id,url'])
// 		->get(['id', 'name', 'parent_id', 'image']);

    // 	// Sort categories in the same order as in $allowedNames
// 	$sortedCategories = collect($allowedNames)->map(function ($name) use ($leafCategories) {
// 		return $leafCategories->firstWhere('name', $name);
// 	})->filter(); // remove any nulls in case some names aren't found

    // 	// Limit results
// 	$limitedCategories = $sortedCategories->take($limit);

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

    // 	return response()->json($limitedCategories->values());
// }
    public function fetchCategories(Request $request)
    {
        $limit = 15;

        // Load allowed names based on environment variable
        $website = env('APP_WEBSITE', 'US'); // default US if not set

        $allowedNames = match ($website) {
            'US' => [
                "Reach In Refrigerator",
                "Pizza Prep Table",
                "Worktop Refrigerator",
                "Chef Base Refrigerator",
                "Undercounter Refrigerator",
                "Beer Dispenser",
                "Back Bar Cooler",
                "Glass Chillers and Frosters",
                "Commercial Grill & Griddle",
                "Commercial Gas Fryer",
                "Deck Oven",
                "Commercial Espresso Machine",
                "Milk Cooler",
                "Commercial Food Processors",
                "Planetary Mixer",

            ],
            'US_T' => [
                "Reach In Refrigerator",
                "Pizza Prep Table",
                "Worktop Refrigerator",
                "Chef Base Refrigerator",
                "Undercounter Refrigerator",
                "Beer Dispenser",
                "Back Bar Cooler",
                "Glass Chillers and Frosters",
                "Commercial Grill & Griddle",
                "Commercial Gas Fryer",
                "Deck Oven",
                "Commercial Espresso Machine",
                "Milk Cooler",
                "Commercial Food Processors",
                "Planetary Mixer",

            ],
            'UAE' => [
                "Work Top Refrigerators",
                "Commercial Fryers",
                "Combi Ovens",
                "Commercial Blenders",
                "Commercial Gas And Electric Cookers",
                "Upright Freezers",
                "Espresso Machines",
                "Commercial Grills And Griddles",
                "Commercial Toasters",
                "Upright Chillers",
                "White Dinnerware",
                "Cheese",
                "Food Processors",
                "Salamanders",
                "Salad Chillers"

            ],
            'UAE_T' => [
                "Work Top Refrigerators",
                "Commercial Fryers",
                "Combi Ovens",
                "Commercial Blenders",
                "Commercial Gas And Electric Cookers",
                "Upright Freezers",
                "Espresso Machines",
                "Commercial Grills And Griddles",
                "Commercial Toasters",
                "Upright Chillers",
                "White Dinnerware",
                "Cheese",
                "Food Processors",
                "Salamanders",
                "Salad Chillers"
            ],

            default => [], // fallback if APP_WEBSITE is not set properly
        };

        // Fetch matching leaf categories
        $leafCategories = Category::where('status', 'published')
            ->whereDoesntHave('children')
            ->whereIn('name', $allowedNames)
            ->with(['seoUrl:id,relational_id,url'])
            ->get(['id', 'name', 'parent_id', 'image']);

        // Sort categories in the same order as in $allowedNames
        $sortedCategories = collect($allowedNames)->map(function ($name) use ($leafCategories) {
            return $leafCategories->firstWhere('name', $name);
        })->filter();

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

        return response()->json($limitedCategories->values())
            ->header('Cache-Control', 'public, max-age=86400');

    }




    /**
     * @OA\Get(
     *     path="/api/frontend/all-categories",
     *     tags={"Frontend-Categories"},
     *     summary="Fetch all parent and child categories",
     *     description="Returns all parent and child categories with product count and image URL.",
     *     @OA\Response(response=200, description="All categories fetched successfully", @OA\MediaType(mediaType="application/json")),
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

        return response()->json($allCategories)->header('Cache-Control', 'public, max-age=86400');
    }



    /**
     * @OA\Get(
     *     path="/api/frontend/categoryproducts",
     *     tags={"Frontend-Categories"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get all featured products grouped by third-level categories",
     *     description="Returns featured products grouped under third-level categories. Includes wishlist status, best price, delivery date, reviews, stock, and images.",
     *     @OA\Response(response=200, description="Featured products grouped by category fetched successfully", @OA\MediaType(mediaType="application/json")),
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
            ->with([
                'products' => function ($query) {
                    $query->where('is_featured', 1)
                        ->where('status', 'published')
                        ->select('id', 'name', 'sku', 'currency_id', 'units_sold'); // Select only necessary fields
                }
            ])
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
                    if (!$details)
                        return null; // Skip if no details found

                    $totalReviews = $details->reviews->count();
                    $avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;

                    $currencyTitle = $details->currency->symbol;
                    $isInWishlist = in_array($details->id, $wishlistProductIds);

                    // Process images efficiently
                    $imageUrls = is_string($details->images)
                        ? json_decode($details->images, true)
                        : (array) $details->images;

                    $cleanedAlt = is_string($details->alt_tags)
                        ? json_decode($details->alt_tags, true)
                        : (array) $details->alt_tags;


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
                        'category_url' => $details->category_url(),
                        'parent_category_url' => $details->parent_category_url(),
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
                        'alt_tags' => $cleanedAlt,
                        "original_price" => $firstSupplier ? (float) $firstSupplier->price : null,
                        'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
                        "best_price" => $firstSupplier ? (float) $firstSupplier->price : null,
                        "selling_type" => $sellingType,
                        "per_unit_price" => $details->per_unit_price,
                        'vendor_id' => $firstSupplier->vendor_id ?? null,
                        'map' => $firstSupplier ? (float) $firstSupplier->map : null,
                        'inventory' => $firstSupplier->inventory ?? null,
                        'in_stock' => $firstSupplier->in_stock ?? null,
                        'delivery_days' => $firstSupplier->delivery_days ?? null,
                        'return_policy' => $firstSupplier->return_policy ?? null,
                        'free_shipping' => $firstSupplier->free_shipping ?? null,
                        'warranty_information' => $firstSupplier->warranty_information ?? null,
                        'min_quantity' => $firstSupplier->min_quantity ?? 0,
                        'is_fixed' => $firstSupplier->is_fixed ?? 0,
                        'quote_available' => $details->quote_available ?? null,
                         'isRequired' => $details->is_required,
                    ];
                })->filter()->values(), // Remove null values and reset array keys
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ])->header('Cache-Control', 'public, max-age=86400');
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/categoryguestproducts",
     *     tags={"Frontend-Categories"},
     *     summary="Get all featured products by category for guest users",
     *     description="Returns featured products grouped under third-level categories for guest users. Includes best price, delivery days, stock, reviews, and images.",
     *     @OA\Response(response=200, description="Featured products grouped by category fetched successfully for guests", @OA\MediaType(mediaType="application/json")),
     * )
     */

    public function getAllGuestFeaturedProductsByCategory(Request $request)
    {
        $categories = Category::whereHas('products', function ($query) {
            $query->where('is_featured', 1)
                ->where('status', 'published');
        }, '>=', 5)
            ->whereHas('parent.parent') // Ensures only third-level child categories
            ->with([
                'products' => function ($query) {
                    $query->where('is_featured', 1)
                        ->where('status', 'published')
                        ->select('id', 'name', 'sku', 'currency_id', 'units_sold'); // Select only necessary fields
                }
            ])
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
                    if (!$details)
                        return null; // Skip if no details found

                    $totalReviews = $details->reviews->count();
                    $avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
                    $currencyTitle = $details->currency->symbol;

                    // Process images efficiently
                    $imageUrls = is_string($details->images)
                        ? json_decode($details->images, true)
                        : (array) $details->images;
                    $cleanedAlt = is_string($details->alt_tags)
                        ? json_decode($details->alt_tags, true)
                        : (array) $details->alt_tags;

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
                        'category_url' => $details->category_url(),
                        'parent_category_url' => $details->parent_category_url(),
                        'url' => $details->seoUrl->url ?? null,
                        'vendor_sku' => $firstSupplier->vendor_sku ?? null,
                        'price' => $firstSupplier?->price ? (float) $firstSupplier->price : (float) $details->price,
                        "sale_price" => $firstSupplier?->sale_price ? (float) $firstSupplier->sale_price : null,
                        'total_reviews' => $totalReviews,
                        'avg_rating' => $avgRating,
                        'left_stock' => $leftStock,
                        'currency' => $currencyTitle,
                        'images' => $imageUrls,
                        'alt_tags' => $cleanedAlt,
                        "original_price" => $firstSupplier?->price ? (float) $firstSupplier->price : (float) $details->price,
                        'front_sale_price' => $firstSupplier?->sale_price ? (float) $firstSupplier->sale_price : (float) $details->price,
                        "best_price" => $firstSupplier?->price ? (float) $firstSupplier->price : (float) $details->price,
                        "selling_type" => $sellingType,
                        "per_unit_price" => $details->per_unit_price,
                        'vendor_id' => $firstSupplier->vendor_id ?? null,
                        'map' => $firstSupplier ? (float) $firstSupplier->map : null,
                        'inventory' => $firstSupplier->inventory ?? null,
                        'in_stock' => $firstSupplier->in_stock ?? null,
                        'delivery_days' => $firstSupplier->delivery_days ?? null,
                        'return_policy' => $firstSupplier->return_policy ?? null,
                        'free_shipping' => $firstSupplier->free_shipping ?? null,
                        'warranty_information' => $firstSupplier->warranty_information ?? null,
                        'min_quantity' => $firstSupplier->min_quantity ?? 0,
                        'is_fixed' => $firstSupplier->is_fixed ?? 0,
                        'quote_available' => $details->quote_available ?? null,
                         'isRequired' => $details->is_required,
                    ];
                })->filter()->values(), // Remove null values and reset array keys
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ])->header('Cache-Control', 'public, max-age=86400');
    }


    // Recursive function to modify images for children and all sub-level categories
    private function addImageUrlsRecursively($category)
    {
        // If the category has children, modify their images as well
        if (isset($category->children) && !empty($category->children)) {
            foreach ($category->children as $childCategory) {
                $childCategory->image; // Modify image for child category
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


    /**
     * @OA\Get(
     *     path="/api/frontend/categories/sale",
     *     summary="Get all last-child categories with sale products",
     *     description="Returns all categories that are directly assigned to products having a sale price. Only last-child categories (categories with no children) are returned. Slug is excluded.",
     *     tags={"Categories"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=12),
     *                     @OA\Property(property="name", type="string", example="Kids Shoes"),
     *                     @OA\Property(property="parent_id", type="integer", example=3),
     *                     @OA\Property(property="image", type="string", example="kids-shoes.png"),
     *                     @OA\Property(property="order", type="integer", example=1),
     *                     @OA\Property(property="status", type="string", example="published"),
     *                     @OA\Property(property="created_at", type="string", example="2024-02-10 12:00:00"),
     *                     @OA\Property(property="updated_at", type="string", example="2024-02-12 09:22:00")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */

    public function saleCategories(Request $request)
    {
        // Fetch all last-child categories that have sale products
        $categories = Category::query()

            // Only categories that are directly assigned to products having sale price
            ->whereHas('products', function ($query) {
                $query->whereHas('productSuppliers', function ($q) {
                    $q->whereNotNull('sale_price')
                    ->where('sale_price', '>', 0);
                });
            })

            // Ensure category is LAST CHILD (has no children)
            ->whereDoesntHave('children')

            ->select('*') // return everything
            ->distinct()
            ->get();

        // Remove slug from response
        $categories->makeHidden(['slug']);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }



}
