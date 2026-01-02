<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

use App\Models\Category;
use App\Models\Product;
use App\Models\SeoManagement;

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
	public function index(Request $request)
	{
		$filterId = $request->get('id');
		$limit = $request->get('limit', 12); // Default limit to 12
		$categories = Category::with(['translation', 'seoUrl'])->where('status', 'published');
		if ($filterId) {
			$categories = $categories->where(function ($query) use ($filterId) {
				$query->where('id', $filterId)
				->orWhere('parent_id', $filterId);
			});
		}
		$categories = $categories->get();

		$categories->map(function ($record) {
			$lastChildIds = !empty($record->last_child) ? array_map('intval', explode(',', $record->last_child)) : [];
			if (!empty($lastChildIds)) {
				$record->last_children = Category::with(['translation', 'seoUrl'])
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
	 *     @OA\Response(response=200, description="Categories tree fetched successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
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

	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/categories/{ashar}",
	//  *     tags={"Frontend-Categories"},
	//  *     summary="Get a category by ID",
	//  *     description="Retrieve the details of a single category using its ID.",
	//  *     @OA\Parameter(
	//  *         name="id",
	//  *         in="path",
	//  *         required=true,
	//  *         description="The ID of the category to retrieve",
	//  *         @OA\Schema(type="integer", example=1)
	//  *     ),
	//  *     @OA\Response(response=200, description="Category details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	//  * )
	//  */
	// public function show($id)
	// {
	// 	// Validate that the ID is numeric
	// 	if (!is_numeric($id)) {
	// 		return response()->json([
	// 			'message' => "Invalid category ID format."
	// 		], 400);
	// 	}

	// 	$category = Category::with('translation')->find($id);

	// 	if (!$category) {
	// 		return response()->json([
	// 			'message' => "Category with ID $id not found."
	// 		], 404);
	// 	}
	// 	return response()->json([
	// 		'category' => $category,
	// 	])->header('Cache-Control', 'public, max-age=86400');
	// }

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
	 *     @OA\Response(response=200, description="List of products for the category", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	//product name categories name brand name translation
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

			return $product;
		});

		return response()->json([
			'category' => $category,
			'products' => $products
		])->header('Cache-Control', 'public, max-age=86400');
	}


	/**
	 * @OA\Get(
	 *     path="/api/frontend/home-categories",
	 *     tags={"Frontend-Categories"},
	 *     summary="Fetch a limited set of parent and child categories",
	 *     description="Returns up to 14 categories including parent and child, with product count and image URL.",
	 *     @OA\Response(response=200, description="Categories fetched successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function fetchCategories(Request $request)
	{
		$limit = 15;

		// Load allowed names based on environment variable
		$website = env('APP_WEBSITE', 'US'); // default US if not set

		$allowedNames = match ($website) {
			'US', 'US_T' => [
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
			'UAE', 'UAE_T' => [
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
		->with(['translations', 'seoUrl:id,relational_id,url'])
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

	//categories name translation
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
	//product name categories name translation
	public function getAllFeaturedProductsByCategory(Request $request)
	{
		$userId = auth()->id();
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
						->select('id', 'name', 'sku', 'currency_id'); // Select only necessary fields
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
			];//
		});

		return response()->json([//
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
	//product name categories name translation
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
						->select('id', 'name', 'sku', 'currency_id'); // Select only necessary fields
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
			];//
		});

		return response()->json([//
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

	//category name
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
	//product name categories name translation
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

		->select('*')
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
