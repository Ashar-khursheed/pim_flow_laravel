<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GroupedProduct;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Attribute;
use App\Models\SeoManagement;
use App\Models\ProductAttribute;
use App\Models\Review;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Add this line
use App\Models\FrontEnd\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class ProductController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/products",
	 *     summary="Get all products with filters and pagination (for authenticated and guest userss)",
	 *     tags={"Frontend-Product"},
	 *     security={{"bearerAuth": {}}},
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         description="Sort products by 'created_at', 'price', or 'name'",
	 *         required=false,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination",
	 *         required=false,
	 *         @OA\Schema(type="integer", default=1)
	 *     ),
	 *      @OA\Parameter(
	 *            name="product_id",
	 *           in="query",
	 *           description="Get details of a specific product by ID",
	 *           required=false,
	 *            @OA\Schema(type="integer")
	 *       ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of products with filters, brand/category info, wishlist status, and pagination details",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean"),
	 *             @OA\Property(property="data", type="object"),
	 *             @OA\Property(property="pagination", type="object"),
	 *             @OA\Property(property="brands", type="array", @OA\Items(
	 *                 @OA\Property(property="id", type="integer"),
	 *                 @OA\Property(property="name", type="string")
	 *             )),
	 *             @OA\Property(property="categories", type="array", @OA\Items(
	 *                 @OA\Property(property="id", type="integer"),
	 *                 @OA\Property(property="name", type="string")
	 *             )),
	 *             @OA\Property(property="price_min", type="number", format="float"),
	 *             @OA\Property(property="price_max", type="number", format="float")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthenticated"
	 *     )
	 * )
	 */
	public function getAllProducts(Request $request)
	{
		$userId = Auth::id();
		$isUserLoggedIn = $userId !== null;

		Log::info('User logged in:', ['user_id' => $userId]);

		$productId = $request->input('product_id');
		$slug = $request->input('slug');

		if (!$productId && !$slug) {
			return response()->json([
				'success' => false,
				'message' => 'Product ID or slug is required'
			], 400);
		}

		// ✅ OPTIMIZED: Single eager-loaded query with all necessary relationships
		$product = Product::with([
			'categories.seoUrl',
			'brand.seoUrl',
			'productSuppliers',
			'seoUrl',
			'accessories.items',
			'productVariants',
			'reviews:id,product_id,star',
			'currency',
			'productAttributes.attributeDetails',
		])
		->where('status', 'published')
		->when($productId, fn($q) => $q->where('id', $productId))
		->when($slug, fn($q) => $q->whereHas('seoUrl', fn($sq) => $sq->where('url', $slug)))
		->first();

		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found'
			], 404);
		}

		// ✅ OPTIMIZED: Check wishlist ONLY for this product
		$isInWishlist = false;
		if ($isUserLoggedIn) {
			$isInWishlist = DB::table('ec_wish_lists')
			->where('customer_id', $userId)
			->where('product_id', $product->id)
			->exists();
		} else {
			$wishlist = session()->get('guest_wishlist', []);
			$isInWishlist = in_array($product->id, $wishlist);
		}

		// ========================================
		// PRODUCT TRANSFORMATION STARTS HERE
		// ========================================

		$product->benefits_features = json_decode($product->benefits_features, true);
		$product->url = $product->seoUrl->url ?? null;
		unset($product->seoUrl);

		// Description cleanup
		if (is_string($product->description)) {
			$decoded = json_decode($product->description, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$product->description = array_values(array_filter(array_map(function ($item) {
					if (is_null($item) || strtolower($item) === 'null') return null;
					$item = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $item);
					$item = preg_replace('/\s+/', ' ', trim($item));
					$lowerItem = strtolower($item);
					if (stripos($lowerItem, '<p>null') === 0 || $lowerItem === '<p>null</p>') {
						return null;
					}
					return $item !== '' ? $item : null;
				}, $decoded)));
			} else {
				$product->description = [$product->description];
			}
		}

		// Brand info
		if ($product->brand) {
			$product->brand_id = $product->brand->id;
			$product->brand_name = $product->brand->name;
			$product->brand_logo = $product->brand->logo;
			$product->brand_url = $product->brand->seoUrl->url ?? null;

		// ✅ OPTIMIZED: Single aggregated query for brand stats
			$brandStats = DB::table('ec_reviews')
			->join('ec_products', 'ec_reviews.product_id', '=', 'ec_products.id')
			->where('ec_products.brand_id', $product->brand->id)
			->selectRaw('COUNT(*) as review_count, AVG(star) as avg_rating')
			->first();

			$product->brand_avg_rating = $brandStats->review_count > 0
			? round($brandStats->avg_rating, 1)
			: null;
			$product->brand_review_count = $brandStats->review_count;
		}

		// Images & videos
		$product->images = collect(json_decode($product->images, true));
		$product->alt_tags = collect(json_decode($product->alt_tags, true));

		// Documents
		$documents = is_string($product->documents)
		? json_decode($product->documents, true)
		: $product->documents;

		if (is_array($documents)) {
			$desiredOrder = [
				'Technical Specification Sheet',
				'Warranty Information',
				'Horeca Buying Guide',
				'Setup & Usage Instructions',
				'Product Installation Guide',
				'Installation & Elevation Diagram',
				'Spare Parts List',
				'Product Brochure',
			];

			foreach ($documents as &$doc) {
				if (isset($doc['title'])) {
					$doc['title'] = preg_replace('/\.pdf$/i', '', $doc['title']);
				}
				if (isset($doc['path'])) {
					$doc['path'] = url('/media/' . basename($doc['path']));
				}
			}

			usort($documents, function ($a, $b) use ($desiredOrder) {
				$posA = array_search($a['title'] ?? '', $desiredOrder);
				$posB = array_search($b['title'] ?? '', $desiredOrder);
				return ($posA === false ? PHP_INT_MAX : $posA) <=> ($posB === false ? PHP_INT_MAX : $posB);
			});

			$product->documents = $documents;
		} else {
			$product->documents = [];
		}

		$product->video_path = collect(json_decode($product->video_path, true));

		// Selling unit attribute
		if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
			$fullValue = $product->sellingUnitAttribute->attribute_value;
			if (strpos($fullValue, '/') !== false) {
				$parts = explode('/', $fullValue);
				$product->sellingUnitAttribute->attribute_value_unit = trim($parts[1]);
			} else {
				$product->sellingUnitAttribute->attribute_value_unit = $fullValue;
			}
		}

		// Per unit price calculation
		$unitsPerCase = null;
		$packType = null;

		if ($product->productAttributes) {
			foreach ($product->productAttributes as $attr) {
				if ($attr->attributeDetails && $attr->attributeDetails->name === 'Units per Case') {
					$unitsPerCase = $attr;
				}
				if ($attr->attributeDetails && $attr->attributeDetails->name === 'Pack Type') {
					$packType = $attr;
				}
			}
		}

		$basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
		$product->per_unit_price = null;

		if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
			$unitValue = (float) $unitsPerCase->attribute_value;
			if ($unitValue > 0) {
				$calculated = round($basePrice / $unitValue, 2);
				$product->per_unit_price = $calculated . '/' . ($packType?->attribute_value ?? '');
			}
		}

		// Reviews & stock
		$product->total_reviews = $product->reviews->count();
		$product->avg_rating = $product->total_reviews > 0 ? $product->reviews->avg('star') : null;
		$product->leftStock = ($product->quantity ?? 0) - ($product->units_sold ?? 0);
		$product->in_wishlist = $isInWishlist;

		// Supplier info
		$firstSupplier = $product->productSuppliers()
		->with([
			'vendor.country:id,name',
			'vendor.city:id,name'
		])
		->first();

		if ($firstSupplier) {
			$product->vendor_sku = $firstSupplier->vendor_sku;

			$product->vendor_country = $firstSupplier->vendor->country->name ?? null;
			$product->vendor_city = $firstSupplier->vendor->city->name ?? null;
			$product->vendor_address = $firstSupplier->vendor->address ?? null;
			$product->vendor_zipcode = $firstSupplier->vendor->zipcode ?? null;

			$product->price = (float) $firstSupplier->price;
			$product->sale_price = (float) $firstSupplier->sale_price;
			$product->original_price = (float) $firstSupplier->price;
			$product->front_sale_price = (float) ($firstSupplier->sale_price ?? $firstSupplier->price);
			$product->best_price = (float) $firstSupplier->price;
			$product->vendor_id = $firstSupplier->vendor_id;
			$product->map = (float) $firstSupplier->map;
			$product->inventory = $firstSupplier->inventory;
			$product->in_stock = $firstSupplier->in_stock;
			$product->delivery_days = $firstSupplier->delivery_days;
			$product->return_policy = $firstSupplier->return_policy;
			$product->free_shipping = $firstSupplier->free_shipping;
			$product->min_quantity = $firstSupplier->min_quantity;
			$product->is_fixed = $firstSupplier->is_fixed;
		} else {
			$product->vendor_sku = null;
			$product->price = 0;
			$product->sale_price = 0;
			$product->original_price = 0;
			$product->front_sale_price = 0;
			$product->best_price = 0;
			$product->vendor_id = null;
			$product->map = 0;
			$product->inventory = null;
			$product->in_stock = null;
			$product->delivery_days = null;
			$product->return_policy = null;
			$product->free_shipping = null;
			$product->min_quantity = 0;
			$product->is_fixed = 0;
		}

		// Currency
		$product->currency_title = $product->currency
		? ($product->currency->is_prefix_symbol ? $product->currency->symbol : $product->price . ' ' . $product->currency->symbol)
		: $product->price;

		// ✅ OPTIMIZED: Category hierarchy - build it iteratively
		$allCategories = collect();
		foreach ($product->categories as $category) {
		// Build hierarchy for this category
			$hierarchy = collect([$category]);
			$current = $category;

			while ($current->parent_id) {
				$parent = Category::with('seoUrl')->find($current->parent_id);
				if (!$parent) break;
				$hierarchy->prepend($parent);
				$current = $parent;
			}

			$allCategories = $allCategories->merge($hierarchy);
		}

		$product->category_list = $allCategories->unique('id')->map(function ($category) {
			return [
				'id' => $category->id,
				'name' => $category->name,
				'slug' => $category->seoUrl->url ?? $category->slug,
			];
		})->values();

		$product->sold = $basePrice < 1000 ? rand(10, 20) : rand(5, 10);

		// Accessories
		$product->accessories = $product->accessories->map(function ($accessory) {
			return [
				'id' => $accessory->id,
				'name' => $accessory->name,
				'isapproved' => $accessory->isapproved,
				'isRequired' => $accessory->isRequired,
				'items' => $accessory->items->map(fn($item) => [
					'id' => $item->id,
					'name' => $item->name,
					'sku' => $item->sku ?? null,
				]),
			];
		})->values();

		if ($product->accessories->isEmpty()) {
			$product->accessories = [];
		}

		// ✅ OPTIMIZED: Product Variants - All in one place
		if ($product->productVariants->isNotEmpty()) {
			$allVariantResults = [];

			foreach ($product->productVariants as $variant) {
				$childIds = json_decode($variant->child_ids, true) ?? [];
				$variants = json_decode($variant->variants, true) ?? [];

			// Merge current product ID with child IDs
				$childIds = collect($childIds)->push($product->id)->unique()->values()->toArray();

				if (empty($childIds) || empty($variants)) {
					continue;
				}

			// Get current product attributes for comparison
				$currentProductAttributes = $product->productAttributes
				->pluck('attribute_value', 'attribute_id')
				->toArray();

			// ✅ BATCH LOAD all data at once
				$children = Product::whereIn('id', $childIds)
				->with(['productSuppliers' => function($q) {
					$q->select('product_id', 'price', 'sale_price');
				}])
				->select('id', 'sku', 'images')
				->get()
				->keyBy('id');

				$attributeIds = array_column($variants, 'attribute_id');
				$attributes = Attribute::whereIn('id', $attributeIds)->pluck('name', 'id');

				$productAttributes = ProductAttribute::whereIn('product_id', $childIds)
				->whereIn('attribute_id', $attributeIds)
				->get()
				->groupBy('product_id');

				$seoUrls = SeoManagement::whereIn('relational_id', $childIds)
				->pluck('url', 'relational_id');

			// ✅ Pre-fetch category URLs for ALL children at once (avoiding N+1)
				$childProducts = Product::whereIn('id', $childIds)->get();
				$categoryUrlsMap = [];

				foreach ($childProducts as $childProduct) {
					try {
						$categoryUrlsMap[$childProduct->id] = [
							'parent' => method_exists($childProduct, 'parent_category_url') ? $childProduct->parent_category_url() : '',
							'child' => method_exists($childProduct, 'category_url') ? $childProduct->category_url() : '',
						];
					} catch (\Exception $e) {
						\Log::error('Error getting category URLs for product ' . $childProduct->id . ': ' . $e->getMessage());
						$categoryUrlsMap[$childProduct->id] = ['parent' => '', 'child' => ''];
					}
				}

			// Process variants
				foreach ($variants as $v) {
					$attributeId = $v['attribute_id'];
					$attributeName = $attributes[$attributeId] ?? null;

					if (!$attributeName) continue;

					$seenAttributeValues = [];

					foreach ($children as $childId => $child) {
						$attrValue = $productAttributes->get($childId)
						?->firstWhere('attribute_id', $attributeId)
						?->attribute_value ?? null;

						if (empty($attrValue) || isset($seenAttributeValues[$attrValue])) {
							continue;
						}

						$seenAttributeValues[$attrValue] = true;

						$isSelected = isset($currentProductAttributes[$attributeId])
						&& $currentProductAttributes[$attributeId] == $attrValue;

						$firstSupplier = $child->productSuppliers->first();

						$slug = $seoUrls[$childId] ?? null;
						$urls = $categoryUrlsMap[$childId] ?? ['parent' => '', 'child' => ''];

						$full_slug = trim($urls['parent'] . '/' . $urls['child'] . '/' . $slug, '/');

						$allVariantResults[] = [
							'product_id' => $childId,
							'sku' => $child->sku,
							'attribute_id' => $attributeId,
							'attribute_value' => $attrValue,
							'attribute_name' => $attributeName,
							'type' => $v['type'] ?? 'dropdown',
							'label' => $v['labels'] ?? $attributeName,
							'selected' => $isSelected,
							'price' => $firstSupplier ? (float) $firstSupplier->price : 0,
							'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : 0,
							'images' => json_decode($child->images, true) ?? [],
							'slug' => $slug,
							'parent_slug' => $urls['parent'],
							'child_slug' => $urls['child'],
							'full_slug' => $full_slug,
						];
					}
				}
			}

			$product->productVariants = collect($allVariantResults);
		} else {
			$product->productVariants = [];
		}

		// Category URLs
		try {
			$product->category_url = method_exists($product, 'category_url') ? $product->category_url() : null;
			$product->parent_category_url = method_exists($product, 'parent_category_url') ? $product->parent_category_url() : null;
		} catch (\Throwable $e) {
			\Log::error('Error fetching category URLs for product ID ' . $product->id . ': ' . $e->getMessage());
			$product->category_url = null;
			$product->parent_category_url = null;
		}

		// ========================================
		// RETURN RESPONSE
		// ========================================

		return response()->json([
			'success' => true,
			'data' => [
				'current_page' => 1,
				'data' => [$product],
				'first_page_url' => url()->current() . '?page=1',
				'from' => 1,
				'last_page' => 1,
				'last_page_url' => url()->current() . '?page=1',
				'next_page_url' => null,
				'path' => url()->current(),
				'per_page' => 1,
				'prev_page_url' => null,
				'to' => 1,
				'total' => 1,
			]
		])->header('Cache-Control', 'public, max-age=3600');
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/products-guest",
	 *     summary="Get all public products with filters and price range (for guests)",
	 *     tags={"Frontend-Product"},
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         description="Sort products by 'created_at', 'price', or 'name'",
	 *         required=false,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination",
	 *         required=false,
	 *         @OA\Schema(type="integer", default=1)
	 *     ),
	 *     @OA\Parameter(
	 *            name="product_id",
	 *           in="query",
	 *           description="Get details of a specific product by ID",
	 *           required=false,
	 *            @OA\Schema(type="integer")
	 *       ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of public products with min/max price and delivery details",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean"),
	 *             @OA\Property(property="data", type="object"),
	 *             @OA\Property(property="price_min", type="number", format="float"),
	 *             @OA\Property(property="price_max", type="number", format="float"),
	 *             @OA\Property(property="delivery_min", type="integer"),
	 *             @OA\Property(property="delivery_max", type="integer")
	 *         )
	 *     )
	 * )
	 */
	public function getAllPublicProducts(Request $request)
	{
		// Start building the base query
		$query = Product::with(['categories', 'brand', 'productSuppliers', 'brand.products.reviews', 'seoUrl', 'accessories.items', 'productVariants'])->where('status', 'published');

		$productId = $request->input('product_id'); // numeric ID
		$slug = $request->input('slug');           // string slug

		if ($productId) {
			$query->where('id', $productId);
		} elseif ($slug) {
			$query->whereHas('seoUrl', function ($q) use ($slug) {
				$q->where('url', $slug);
			});
		}

		$this->applyFilters($query, $request);

		// Log query for debugging
		\Log::info($query->toSql());
		\Log::info($query->getBindings());

		// Get filtered IDs efficiently
		$filteredProductIds = $query->pluck('id');

		// Get sort parameter
		$validSortOptions = ['created_at', 'price', 'name'];
		$sortBy = $request->input('sort_by', 'created_at');

		// Subquery for best price and delivery date
		$subQuery = Product::select('sku')
		->whereIn('id', $filteredProductIds)
		->groupBy('sku');

		// Paginate efficiently - only get the required number of products
		$perPage = 30;
		$page = $request->input('page', 1);

		$products = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
			$join->on('ec_products.sku', '=', 'best_products.sku');
		})
		->whereIn('id', $filteredProductIds)
		->select('ec_products.*')
		->with([
			'reviews' => function ($query) {
				$query->select('id', 'product_id', 'star');
			},
			'currency',
			'categories',
			'productSuppliers',
			'seoUrl',
			'productAttributes' => function ($query) {
				$query->whereHas('attributeDetails', function ($q) {
					$q->whereIn('name', ['Units per Case', 'Pack Type']);
				});
			},
			'accessories.items',
			'productVariants'
		])
		->orderBy($sortBy, 'desc')
		->paginate($perPage);

		// Add query parameters to pagination
		$products->appends($request->all());

		// Calculate pagination details
		$currentPage = $products->currentPage();
		$lastPage = $products->lastPage();
		$startPage = max($currentPage - 2, 1);
		$endPage = min($startPage + 4, $lastPage);

		if ($endPage - $startPage < 4) {
			$startPage = max($endPage - 4, 1);
		}

		$pagination = [
			'current_page' => $currentPage,
			'last_page' => $lastPage,
			'per_page' => $perPage,
			'total' => $products->total(),
			'has_more_pages' => $products->hasMorePages(),
			'visible_pages' => range($startPage, $endPage),
			'has_previous' => $currentPage > 1,
			'has_next' => $currentPage < $lastPage,
			'previous_page' => $currentPage - 1,
			'next_page' => $currentPage + 1,
		];

		// Transform the products collection
		$products->getCollection()->transform(function ($product) {

			$product->benefits_features = json_decode($product->benefits_features, true);

			if ($product->seoUrl) {
				$product->url = $product->seoUrl->url;
				unset($product->seoUrl);
			} else {
				$product->url = null;
			}

			if (is_string($product->description)) {
				$decoded = json_decode($product->description, true);

				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					$product->description = array_values(array_filter(array_map(function ($item) {
						if (is_null($item) || strtolower($item) === 'null') {
							return null;
						}

						// Clean up spaces and HTML &nbsp;
						$item = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $item);
						$item = preg_replace('/\s+/', ' ', $item);
						$item = trim($item);

						// Skip if it starts with "<p>null" OR equals "<p>null</p>"
						$lowerItem = strtolower($item);
						if (
							stripos($lowerItem, '<p>null') === 0 ||
							$lowerItem === '<p>null</p>'
						) {
							return null;
						}

						return $item !== '' ? $item : null;
					}, $decoded)));
				} else {
					$product->description = [$product->description];
				}
			}

			if ($product->brand) {
				$product->brand_id = $product->brand->id;
				$product->brand_name = $product->brand->name;
				$product->brand_logo = $product->brand->logo;

				if ($product->brand->seoUrl) {
					$product->brand_url = $product->brand->seoUrl->url;
				} else {
					$product->brand_url = null;
				}

				// Get review stats directly from the database
				$brandProductIds = \DB::table('ec_products')
				->where('brand_id', $product->brand->id)
				->pluck('id');

				$brandReviewsQuery = \DB::table('ec_reviews')
				->whereIn('product_id', $brandProductIds);

				$brandReviewCount = $brandReviewsQuery->count();
				$brandAvgRating = $brandReviewCount > 0
				? round($brandReviewsQuery->avg('star'), 1)
				: null;

				$product->brand_avg_rating = $brandAvgRating;
				$product->brand_review_count = $brandReviewCount;
			}

			$product->images = collect(json_decode($product->images, true))->map(function ($image) {
				return $image;
			});

			$product->alt_tags = collect(json_decode($product->alt_tags, true))->map(function ($alt_tags) {
				return $alt_tags;
			});

			// Custom sorting for documents
			$desiredOrder = [
				'Technical Specification Sheet',
				'Warranty Information',
				'Horeca Buying Guide',
				'Setup & Usage Instructions',
				'Product Installation Guide',
				'Installation & Elevation Diagram',
				'Spare Parts List',
				'Product Brochure',
			];

			$documents = json_decode($product->documents, true);
			if (is_array($documents)) {
				// Remove .pdf extension from titles and modify path
				foreach ($documents as &$doc) {
					if (isset($doc['title'])) {
						$doc['title'] = preg_replace('/\.pdf$/i', '', $doc['title']);
					}

					// Modify the 'path' key
					if (isset($doc['path'])) {
						$filename = basename($doc['path']);
						$doc['path'] = url('/media/' . $filename);
					}
				}

				// Sort documents by desired order
				usort($documents, function ($a, $b) use ($desiredOrder) {
					$posA = isset($a['title']) ? array_search($a['title'], $desiredOrder) : PHP_INT_MAX;
					$posB = isset($b['title']) ? array_search($b['title'], $desiredOrder) : PHP_INT_MAX;
					$posA = $posA === false ? PHP_INT_MAX : $posA;
					$posB = $posB === false ? PHP_INT_MAX : $posB;
					return $posA <=> $posB;
				});

				$product->documents = $documents;
			} else {
				$product->documents = [];
			}

			$videoPaths = json_decode($product->video_path, true);
			$product->video_path = collect($videoPaths)->map(function ($video) {
				return $video;
			});

			$sellingType = null;
			if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
				$fullValue = $product->sellingUnitAttribute->attribute_value;
				if (strpos($fullValue, '/') !== false) {
					$parts = explode('/', $fullValue);
					$product->sellingUnitAttribute->attribute_value_unit = trim($parts[1]);
				} else {
					$product->sellingUnitAttribute->attribute_value_unit = $fullValue;
				}
			}

			if ($product->ingredientsAttribute && $product->ingredientsAttribute->attribute_value) {
				$fullValue = $product->ingredientsAttribute->attribute_value;
			}

			// Calculate per unit price
			$unitsPerCase = null;
			$packType = null;

			if (!empty($product->per_unit_price_attributes)) {
				$unitsPerCase = collect($product->per_unit_price_attributes)
				->first(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
				$packType = collect($product->per_unit_price_attributes)
				->first(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');
			}

			$basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
			$perUnitPrice = null;

			if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
				$unitValue = (float) $unitsPerCase->attribute_value;
				if ($unitValue > 0) {
					$calculated = round($basePrice / $unitValue, 2);
					$perUnitPrice = $calculated . '/' . ($packType?->attribute_value ?? '');
				}
			}

			$product->per_unit_price = $perUnitPrice;

			// Reviews and stock
			$totalReviews = $product->reviews->count();
			$avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
			$quantity = $product->quantity ?? 0;
			$unitsSold = $product->units_sold ?? 0;
			$leftStock = $quantity - $unitsSold;

			$product->total_reviews = $totalReviews;
			$product->avg_rating = $avgRating;
			$product->leftStock = $leftStock;

			$firstSupplier = $product->productSuppliers()
			->with([
				'vendor.country:id,name',
				'vendor.city:id,name'
			])
			->first();

			if ($firstSupplier) {
				$product->vendor_sku = $firstSupplier->vendor_sku;

				$product->vendor_country = $firstSupplier->vendor->country->name ?? null;
				$product->vendor_city = $firstSupplier->vendor->city->name ?? null;
				$product->vendor_address = $firstSupplier->vendor->address ?? null;
				$product->vendor_zipcode = $firstSupplier->vendor->zipcode ?? null;

				$product->price = (float) $firstSupplier->price;
				$product->sale_price = (float) $firstSupplier->sale_price;
				$product->original_price = (float) $firstSupplier->price;
				$product->front_sale_price = (float) ($firstSupplier->sale_price ?? $firstSupplier->price);
				$product->best_price = (float) $firstSupplier->price;
				$product->vendor_id = $firstSupplier->vendor_id;
				$product->map = (float) $firstSupplier->map;
				$product->inventory = $firstSupplier->inventory;
				$product->in_stock = $firstSupplier->in_stock;
				$product->delivery_days = $firstSupplier->delivery_days;
				$product->return_policy = $firstSupplier->return_policy;
				$product->free_shipping = $firstSupplier->free_shipping;
				$product->warranty_information = $firstSupplier->warranty_information;
				$product->min_quantity = $firstSupplier->min_quantity;
				$product->is_fixed = $firstSupplier->is_fixed;
			} else {
				// Defaults if no supplier exists
				$product->vendor_sku = null;
				$product->price = 0;
				$product->sale_price = 0;
				$product->original_price = 0;
				$product->front_sale_price = 0;
				$product->best_price = 0;
				$product->vendor_id = null;
				$product->map = 0;
				$product->inventory = null;
				$product->in_stock = null;
				$product->delivery_days = null;
				$product->return_policy = null;
				$product->free_shipping = null;
				$product->warranty_information = null;
				$product->min_quantity = 0;
				$product->is_fixed = 0;
			}

			// Currency
			if ($product->currency) {
				$product->currency_title = $product->currency->is_prefix_symbol
				? $product->currency->symbol
				: $product->price . ' ' . $product->currency->symbol;
			} else {
				$product->currency_title = $product->price;
			}

			// Get all categories including parent hierarchies
			$allCategories = collect();

			$product->categories->each(function ($category) use ($allCategories) {
				// Recursive closure to get parent hierarchy
				$getParentHierarchy = function ($cat) use (&$getParentHierarchy) {
					$parents = collect();
					if ($cat->parent_id) {
						// Eager load seoUrl for parent
						$parent = Category::with('seoUrl')->find($cat->parent_id);
						if ($parent) {
							// Recursively get parent's hierarchy
							$parents = $parents->merge($getParentHierarchy($parent));
							$parents->push($parent);
						}
					}
					return $parents;
				};

				// Get all parent categories
				$parentHierarchy = $getParentHierarchy($category);

				// Add parents to collection
				$allCategories->push(...$parentHierarchy);

				// Add current category
				$allCategories->push($category);
			});

			// Remove duplicates and map to desired structure
			$product->category_list = $allCategories->unique('id')->map(function ($category) {
				return [
					'id' => $category->id,
					'name' => $category->name,
					'slug' => optional($category->seoUrl)->url ?? $category->slug,
				];
			})->values();

			$basePrice = $product->sale_price > 0 ? $product->sale_price : $product->price;
			$product->sold = $basePrice < 1000 ? rand(10, 20) : rand(5, 10);

			// Transform accessories
			$product->accessories = $product->accessories->map(function ($accessory) {
				return [
					'id' => $accessory->id,
					'name' => $accessory->name,
					'isapproved' => $accessory->isapproved,
					'isRequired' => $accessory->isRequired,
					'items' => $accessory->items->map(function ($item) {
						return [
							'id' => $item->id,
							'name' => $item->name,
							'sku' => $item->sku ?? null,
						];
					}),
				];
			})->values();

			if ($product->accessories->isEmpty()) {
				$product->accessories = [];
			}

			// ✅ OPTIMIZED PRODUCT VARIANTS
			// ✅ CORRECTED PRODUCT VARIANTS - Same logic as index function
			// ✅ CORRECT PRODUCT VARIANTS - Show ALL child products for EACH attribute WITH SELECTED
			// ✅ CORRECT PRODUCT VARIANTS - Unique attribute values with selected flag
			// ✅ CORRECT PRODUCT VARIANTS - Same logic as index function
			// ✅ CORRECT PRODUCT VARIANTS - Same logic as index function WITH PROPER LOADING
			$product->productVariants = $product->productVariants->map(function ($variant) use ($product) {
				$childIds = json_decode($variant->child_ids, true) ?? [];
				$variants = json_decode($variant->variants, true) ?? [];

				// ✅ Merge current product ID with child IDs (SAME AS INDEX)
				$childIds = collect($childIds)->merge([$product->id])->unique()->values()->toArray();

				// Early return if no children or variants
				if (empty($childIds) || empty($variants)) {
					return [];
				}

				// ✅ LOAD current product's attributes if not already loaded
				if (!$product->relationLoaded('productAttributes')) {
					$product->load('productAttributes');
				}

				// ✅ Get CURRENT product's attributes for comparison
				$currentProductAttributes = ProductAttribute::where('product_id', $product->id)
				->pluck('attribute_value', 'attribute_id')
				->toArray();

				// 🔍 DEBUG - Log to see what we got
				\Log::info('Current Product ID: ' . $product->id);
				\Log::info('Current Product Attributes:', $currentProductAttributes);

				// Fetch all child products at once
				$children = Product::whereIn('id', $childIds)
				->with(['productSuppliers' => function($q) {
					$q->select('product_id', 'price', 'sale_price');
				}])
				->select('id', 'sku', 'images')
				->get();

				// Fetch all attribute names at once
				$attributeIds = array_column($variants, 'attribute_id');
				$attributes = Attribute::whereIn('id', $attributeIds)
				->pluck('name', 'id');

				// Fetch all product attributes at once
				$productAttributes = ProductAttribute::whereIn('product_id', $childIds)
				->whereIn('attribute_id', $attributeIds)
				->get()
				->groupBy('product_id');

				// Fetch all SEO URLs at once
				$seoUrls = SeoManagement::whereIn('relational_id', $childIds)
				->pluck('url', 'relational_id');

				$result = [];

				foreach ($variants as $v) {
					$attributeId = $v['attribute_id'];
					$attributeName = $attributes[$attributeId] ?? null;

					if (!$attributeName) {
						continue;
					}

					// Track unique attribute values for this specific attribute
					$seenAttributeValues = [];

					foreach ($children as $child) {
					// Get attribute value for this child and attribute
						$attrValue = $productAttributes->get($child->id)
						?->firstWhere('attribute_id', $attributeId)
						?->attribute_value ?? null;

					// Skip if no attribute value or if we've already seen this value
						if (empty($attrValue) || isset($seenAttributeValues[$attrValue])) {
							continue;
						}

					// Mark this attribute value as seen
						$seenAttributeValues[$attrValue] = true;

					// ✅ Check if matches CURRENT product's attribute
						$isSelected = isset($currentProductAttributes[$attributeId])
						&& $currentProductAttributes[$attributeId] == $attrValue;

					// 🔍 DEBUG - Log selection check

					// Get pricing from first supplier
						$firstSupplier = $child->productSuppliers->first();
						$price = $firstSupplier ? (float) $firstSupplier->price : 0;
						$salePrice = $firstSupplier ? (float) $firstSupplier->sale_price : 0;

					// Decode images
						$images = json_decode($child->images, true) ?? [];

					// Get slug
						$slug = $seoUrls[$child->id] ?? null;

					// Build full slug
						$parentCategoryUrl = '';
						$categoryUrl = '';

						try {
							$tempProduct = Product::find($child->id);
							if ($tempProduct) {
								$parentCategoryUrl = method_exists($tempProduct, 'parent_category_url')
								? $tempProduct->parent_category_url()
								: '';
								$categoryUrl = method_exists($tempProduct, 'category_url')
								? $tempProduct->category_url()
								: '';
							}
						} catch (\Exception $e) {
							\Log::error('Error getting category URLs for product ' . $child->id . ': ' . $e->getMessage());
						}

						$full_slug = $parentCategoryUrl . '/' . $categoryUrl . '/' . ($slug ?? '');
						$full_slug = trim($full_slug, '/');

						$result[] = [
							'product_id' => $child->id,
							'sku' => $child->sku,
							'attribute_id' => $attributeId,
							'attribute_value' => $attrValue,
							'attribute_name' => $attributeName,
							'type' => $v['type'] ?? 'dropdown',
							'label' => $v['labels'] ?? $attributeName,
							'selected' => $isSelected,
							'price' => $price,
							'sale_price' => $salePrice,
							'images' => $images,
							'slug' => $slug,
							'parent_slug' => $parentCategoryUrl,
							'child_slug' => $categoryUrl,
							'full_slug' => $full_slug,
						];
					}
				}

				return $result;
			})->flatten(1)->values();

			if ($product->productVariants->isEmpty()) {//
				$product->productVariants = [];
			}
					// Get category URLs
			try {
				if (method_exists($product, 'category_url')) {
					$product->category_url = $product->category_url();
				} else {
					$product->category_url = null;
				}

				if (method_exists($product, 'parent_category_url')) {
					$product->parent_category_url = $product->parent_category_url();
				} else {
					$product->parent_category_url = null;
				}
			} catch (\Throwable $e) {
				\Log::error('Error fetching category URLs for product ID ' . $product->id . ': ' . $e->getMessage());
				$product->category_url = null;
				$product->parent_category_url = null;
			}

			return $product;
		});

		return response()->json([//
			'success' => true,
			'data' => $products,
			'pagination' => $pagination,
		])->header('Cache-Control', 'public, max-age=86400');
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/products/{id}/related",
	 *     tags={"Frontend-Product"},
	 *      security={{"bearerAuth": {}}},
	 *     summary="Get related products by category",
	 *     description="Returns a list of related products based on the same categories as the given product.",
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the product to find related items",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of related products",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="data", type="array",
	 *                 @OA\Items(ref="#/components/schemas/Product")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found or no related categories found"
	 *     )
	 * )
	 */
	public function relatedProducts($id)
	{
		$product = Product::find($id);

		if (!$product) {
			return response()->json(['message' => 'Product not found'], 404);
		}

		// Auth and wishlist logic
		$userId = Auth::id();
		$wishlistProductIds = [];

		if ($userId) {
			$wishlistProductIds = DB::table('ec_wish_lists')
			->where('customer_id', $userId)
			->pluck('product_id')
			->map(fn($id) => (int) $id)
			->toArray();
		} else {
			$wishlistProductIds = session()->get('guest_wishlist', []);
		}

		// Get related categories
		$categoryIds = $product->categories->pluck('id');

		if ($categoryIds->isEmpty()) {
			return response()->json(['message' => 'No related categories found'], 404);
		}

		$relatedProducts = Product::whereHas('categories', function ($query) use ($categoryIds) {
			$query->whereIn('categories.id', $categoryIds);
		})
		->where('id', '!=', $id)
		->where('status', 'published')
		->inRandomOrder()
		->limit(20)
		->with([
			'reviews:id,product_id,star',
			'currency',
			'productSuppliers',
			'seoUrl'
		])
		->get();

		$transformed = $relatedProducts->map(function ($product) use ($wishlistProductIds) {
			// $product->images = collect($product->images)->map(function ($image) {
			//     return filter_var($image, FILTER_VALIDATE_URL) ? $image : url('storage/' . ltrim($image, '/'));
			// });

			// $videoPaths = json_decode($product->video_path, true) ?? [];
			// $product->video_path = collect($videoPaths)->map(function ($video) {
			//     return filter_var($video, FILTER_VALIDATE_URL) ? $video : url('storage/' . ltrim($video, '/'));
			// });
			// $product->images = collect($product->images)->map(function ($image) {
			//             return $image;
			//         });

			$imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
			$cleanedImages = collect($imageArray)->map(function ($item) {
				if (is_string($item) && str_starts_with($item, '[')) {
					$decoded = json_decode($item, true);
					return is_array($decoded) ? $decoded : [$item];
				}
				return [$item];
			})->flatten()->filter()->values();

			$AltArray = is_array($product->alt_tags) ? $product->alt_tags : json_decode($product->alt_tags, true);
			$cleanedAlt = collect($imageArray)->map(function ($item) {
				if (is_string($item) && str_starts_with($item, '[')) {
					$decoded = json_decode($item, true);
					return is_array($decoded) ? $decoded : [$item];
				}
				return [$item];
			})->flatten()->filter()->values();

			$videoPaths = json_decode($product->video_path, true);
			$product->video_path = collect($videoPaths)->map(function ($video) {
				return $video;
			});


			$totalReviews = $product->reviews->count();
			$avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
			$quantity = $product->quantity ?? 0;
			$unitsSold = $product->units_sold ?? 0;
			$leftStock = $quantity - $unitsSold;

			$firstSupplier = $product->productSuppliers()
			->with([
				'vendor.country:id,name',
				'vendor.city:id,name'
			])
			->first();

			return [
				'id' => $product->id,
				'name' => $product->name,
				'category_url' => $product->category_url(),
				'parent_category_url' => $product->parent_category_url(),
				'images' => $cleanedImages,
				'alt_tags' => $cleanedAlt,
				"url" => $product->seoUrl->url ?? null,
				'video_url' => $product->video_url,
				'video_path' => $product->video_path,
				'sku' => $product->sku,
				'start_date' => $product->start_date,
				'end_date' => $product->end_date,
				'currency' => $product->currency?->title,
				'total_reviews' => $totalReviews,
				'avg_rating' => $avgRating,
				'isRequired' => $product->is_required,
				'leftStock' => $leftStock,
				'currency_title' => $product->currency
				? ($product->currency->is_prefix_symbol
					? $product->currency->symbol
					: ($product->price . ' ' . $product->currency->symbol))
				: $product->price,
				'in_wishlist' => in_array($product->id, $wishlistProductIds),

				'vendor_sku' => $firstSupplier->vendor_sku ?? null,

				'vendor_country' => $firstSupplier->vendor->country->name ?? null,
				'vendor_city' => $firstSupplier->vendor->city->name ?? null,
				'vendor_address' => $firstSupplier->vendor->address ?? null,
				'vendor_zipcode' => $firstSupplier->vendor->zipcode ?? null,

				'price' => (float) $firstSupplier->price,
				"sale_price" => (float) $firstSupplier->sale_price,
				"original_price" => (float) $firstSupplier->price,
				'front_sale_price' => (float) $firstSupplier->sale_price,
				"best_price" => (float) $firstSupplier->price,
				"selling_type" => $sellingType ?? null,
				"per_unit_price" => $details->per_unit_price ?? null,
				'vendor_id' => $firstSupplier->vendor_id ?? null,
				'map' => (float) $firstSupplier->map ?? null,
				'inventory' => $firstSupplier->inventory ?? null,
				'in_stock' => $firstSupplier->in_stock ?? null,
				'delivery_days' => $firstSupplier->delivery_days ?? null,
				'return_policy' => $firstSupplier->return_policy ?? null,
				'free_shipping' => $firstSupplier->free_shipping ?? null,
				'warranty_information' => $firstSupplier->warranty_information ?? null,
				'min_quantity' => $firstSupplier->min_quantity ?? 0,
				'is_fixed' => $firstSupplier->is_fixed ?? 0,
				'quote_available' => $product->quote_available ?? null,
				'isRequired' => $product->isRequired,
			];
		});

		return response()->json([//
			'success' => true,
			'data' => $transformed
		])->header('Cache-Control', 'public, max-age=86400');
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/brands/{id}/products",
	 *     tags={"Frontend-Product"},
	 *     security={{"bearerAuth": {}}},
	 *     summary="Get products by brand",
	 *     description="Returns paginated list of products for the specified brand.",
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Brand ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         required=false,
	 *         description="Number of items per page",
	 *         @OA\Schema(type="integer", default=10)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Paginated products list",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Products fetched successfully"),
	 *             @OA\Property(property="current_page", type="integer", example=1),
	 *             @OA\Property(property="last_page", type="integer", example=5),
	 *             @OA\Property(property="total", type="integer", example=50),
	 *             @OA\Property(property="per_page", type="integer", example=10),
	 *             @OA\Property(property="data", type="array",
	 *                 @OA\Items(ref="#/components/schemas/Product")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Brand not found"
	 *     )
	 * )
	 */
	public function productsByBrand($id, Request $request)
	{
		$brand = Brand::find($id);

		if (!$brand) {
			return response()->json([
				'success' => false,
				'message' => 'Brand not found',
			], 404);
		}

		$perPage = $request->get('per_page', 10);

		// Auth and wishlist logic
		$userId = Auth::id();
		$wishlistProductIds = [];

		if ($userId) {
			$wishlistProductIds = DB::table('ec_wish_lists')
			->where('customer_id', $userId)
			->pluck('product_id')
			->map(fn($id) => (int) $id)
			->toArray();
		} else {
			$wishlistProductIds = session()->get('guest_wishlist', []);
		}

		// Get paginated products with relationships
		$products = $brand->products()
		->where('status', 'published')
		->with(['reviews:id,product_id,star', 'currency', 'productSuppliers', 'seoUrl'])
		->paginate($perPage);

		// Transform each product
		$transformed = collect($products->items())->map(function ($product) use ($wishlistProductIds) {
			// $product->images = collect($product->images)->map(function ($image) {
			//     return filter_var($image, FILTER_VALIDATE_URL) ? $image : url('storage/' . ltrim($image, '/'));
			// });

			// $videoPaths = json_decode($product->video_path, true) ?? [];
			// $product->video_path = collect($videoPaths)->map(function ($video) {
			//     return filter_var($video, FILTER_VALIDATE_URL) ? $video : url('storage/' . ltrim($video, '/'));
			// });
			$product->images = collect($product->images)->map(function ($image) {
				return $image;
			});

			$product->alt_tags = collect($product->alt_tags)->map(function ($alt_tags) {
				return $alt_tags;
			});

			$videoPaths = json_decode($product->video_path, true);
			$product->video_path = collect($videoPaths)->map(function ($video) {
				return $video;
			});


			$totalReviews = $product->reviews->count();
			$avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
			$quantity = $product->quantity ?? 0;
			$unitsSold = $product->units_sold ?? 0;
			$leftStock = $quantity - $unitsSold;

			$firstSupplier = $product->productSuppliers()
			->with([
				'vendor.country:id,name',
				'vendor.city:id,name'
			])
			->first();

			return [
				'id' => $product->id,
				'name' => $product->name,
				'category_url' => $product->category_url(),
				'parent_category_url' => $product->parent_category_url(),
				'images' => $product->images,
				'alt_tags' => $product->alt_tags,
				"url" => $product->seoUrl->url ?? null,
				'video_url' => $product->video_url,
				'video_path' => $product->video_path,
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

				'vendor_sku' => $firstSupplier->vendor_sku ?? null,

				'vendor_country' => $firstSupplier->vendor->country->name ?? null,
				'vendor_city' => $firstSupplier->vendor->city->name ?? null,
				'vendor_address' => $firstSupplier->vendor->address ?? null,
				'vendor_zipcode' => $firstSupplier->vendor->zipcode ?? null,

				'price' => (float) $firstSupplier->price,
				"sale_price" => (float) $firstSupplier->sale_price,
				"original_price" => (float) $firstSupplier->price,
				'front_sale_price' => (float) $firstSupplier->sale_price,
				"best_price" => (float) $firstSupplier->price,
				"selling_type" => $sellingType ?? null,
				"per_unit_price" => $details->per_unit_price ?? null,
				'vendor_id' => $firstSupplier->vendor_id ?? null,
				'map' => (float) $firstSupplier->map ?? null,
				'inventory' => $firstSupplier->inventory ?? null,
				'in_stock' => $firstSupplier->in_stock ?? null,
				'delivery_days' => $firstSupplier->delivery_days ?? null,
				'return_policy' => $firstSupplier->return_policy ?? null,
				'free_shipping' => $firstSupplier->free_shipping ?? null,
				'warranty_information' => $firstSupplier->warranty_information ?? null,
				'min_quantity' => $firstSupplier->min_quantity ?? 0,
				'is_fixed' => $firstSupplier->is_fixed ?? 0,
				'quote_available' => $product->quote_available ?? null,
				'isRequired' => $product->isRequired,
			];
		});

		return response()->json([
			'success' => true,
			'message' => 'Products fetched successfully',
			'current_page' => $products->currentPage(),
			'last_page' => $products->lastPage(),
			'total' => $products->total(),
			'per_page' => $products->perPage(),
			'data' => $transformed,
		])->header('Cache-Control', 'public, max-age=86400');
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/brands/{id}/sale-products",
	 *     tags={"Frontend-Product"},
	 *     security={{"bearerAuth": {}}},
	 *     summary="Get sale products by brand",
	 *     description="Returns paginated list of products on sale for a specific brand.",
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Brand ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         required=false,
	 *         description="Number of items per page",
	 *         @OA\Schema(type="integer", default=10)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Paginated list of sale products",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Products fetched successfully"),
	 *             @OA\Property(property="current_page", type="integer", example=1),
	 *             @OA\Property(property="last_page", type="integer", example=5),
	 *             @OA\Property(property="total", type="integer", example=20),
	 *             @OA\Property(property="per_page", type="integer", example=10),
	 *             @OA\Property(property="data", type="array",
	 *                 @OA\Items(ref="#/components/schemas/Product")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Brand not found"
	 *     )
	 * )
	 */
	public function saleProductsByBrand($id, Request $request)
	{
		$brand = Brand::find($id);

		if (!$brand) {
			return response()->json([
				'success' => false,
				'message' => 'Brand not found',
			], 404);
		}

		$perPage = $request->get('per_page', 10);

		// Wishlist logic
		$userId = Auth::id();
		$wishlistProductIds = [];

		if ($userId) {
			$wishlistProductIds = DB::table('ec_wish_lists')
			->where('customer_id', $userId)
			->pluck('product_id')
			->map(fn($id) => (int) $id)
			->toArray();
		} else {
			$wishlistProductIds = session()->get('guest_wishlist', []);
		}

		// Fetch products with non-empty sale_price
		$products = $brand->products()
		->where('status', 'published')
		->whereNotNull('sale_price')
		->where('sale_price', '>', 0)
		->with(['reviews:id,product_id,star', 'currency', 'productSuppliers', 'seoUrl'])
		->paginate($perPage);

		$transformed = collect($products->items())->map(function ($product) use ($wishlistProductIds) {
			$product->images = collect($product->images)->map(function ($image) {
				return $image;
			});

			$videoPaths = json_decode($product->video_path, true);
			$product->video_path = collect($videoPaths)->map(function ($video) {
				return $video;
			});
			$totalReviews = $product->reviews->count();
			$avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
			$quantity = $product->quantity ?? 0;
			$unitsSold = $product->units_sold ?? 0;
			$leftStock = $quantity - $unitsSold;

			$firstSupplier = $product->productSuppliers()
			->with([
				'vendor.country:id,name',
				'vendor.city:id,name'
			])
			->first();

			return [
				'id' => $product->id,
				'name' => $product->name,
				'category_url' => $product->category_url(),
				'parent_category_url' => $product->parent_category_url(),
				'images' => $product->images,
				'alt_tags' => $product->alt_tags,
				"url" => $product->seoUrl->url ?? null,
				'video_url' => $product->video_url,
				'video_path' => $product->video_path,
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
				'vendor_sku' => $firstSupplier->vendor_sku ?? null,

				'vendor_country' => $firstSupplier->vendor->country->name ?? null,
				'vendor_city' => $firstSupplier->vendor->city->name ?? null,
				'vendor_address' => $firstSupplier->vendor->address ?? null,
				'vendor_zipcode' => $firstSupplier->vendor->zipcode ?? null,

				'price' => (float) $firstSupplier->price,
				"sale_price" => (float) $firstSupplier->sale_price,
				"original_price" => (float) $firstSupplier->price,
				'front_sale_price' => (float) $firstSupplier->sale_price,
				"best_price" => (float) $firstSupplier->price,
				"selling_type" => $sellingType ?? null,
				"per_unit_price" => $details->per_unit_price ?? null,
				'vendor_id' => $firstSupplier->vendor_id ?? null,
				'map' => (float) $firstSupplier->map ?? null,
				'inventory' => $firstSupplier->inventory ?? null,
				'in_stock' => $firstSupplier->in_stock ?? null,
				'delivery_days' => $firstSupplier->delivery_days ?? null,
				'return_policy' => $firstSupplier->return_policy ?? null,
				'free_shipping' => $firstSupplier->free_shipping ?? null,
				'warranty_information' => $firstSupplier->warranty_information ?? null,
				'min_quantity' => $firstSupplier->min_quantity ?? 0,
				'is_fixed' => $firstSupplier->is_fixed ?? 0,
				'quote_available' => $product->quote_available ?? null,
				'isRequired' => $product->isRequired,
			];
		});

		return response()->json([
			'success' => true,
			'message' => 'Sale products fetched successfully',
			'current_page' => $products->currentPage(),
			'last_page' => $products->lastPage(),
			'total' => $products->total(),
			'per_page' => $products->perPage(),
			'data' => $transformed,
		])->header('Cache-Control', 'public, max-age=86400');
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/brands/{id}/summary",
	 *     summary="Get brand summary statistics",
	 *     description="Returns summary stats like total units sold and total reviews for a given brand.",
	 *     operationId="getBrandSummaryStats",
	 *     tags={"Frontend-Product"},
	 *     security={{"bearerAuth": {}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the brand",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Brand summary fetched successfully"),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="object",
	 *                 @OA\Property(property="brand_id", type="integer", example=1),
	 *                 @OA\Property(property="brand_name", type="string", example="Apple"),
	 *                 @OA\Property(property="total_units_sold", type="integer", example=2500),
	 *                 @OA\Property(property="total_reviews", type="integer", example=320)
	 *             )
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=404,
	 *         description="Brand not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Brand not found")
	 *         )
	 *     )
	 * )
	 */
	public function brandSummaryStats($id)
	{
		// Try to find SEO entry first (slug-style URL)
		$seoEntry = DB::table('seo_management')
		->where('url', $id)
		->where('relational_type', 'Brand')
		->first();

		if ($seoEntry) {
			// Use relational_id from SEO table
			$brand = Brand::find($seoEntry->relational_id);
		} else {
			// Fallback: try to find brand by numeric ID
			$brand = Brand::find($id);
		}

		if (!$brand) {
			return response()->json([
				'success' => false,
				'message' => 'Brand not found',
			], 404);
		}

		// Get product IDs related to the brand
		$productIds = Product::where('brand_id', $brand->id)->pluck('id');

		// Calculate total units sold
		// $totalUnitsSold = Product::whereIn('id', $productIds)->sum('units_sold');
		$totalUnitsSold = 0;

		// Count total reviews
		$totalReviews = DB::table('ec_reviews')
		->whereIn('product_id', $productIds)
		->count();

		return response()->json([
			'success' => true,
			'message' => 'Brand summary fetched successfully',
			'data' => [
				'brand_id' => $brand->id,
				'brand_name' => $brand->name,
				'total_units_sold' => $totalUnitsSold,
				'total_reviews' => $totalReviews,
			]
		])->header('Cache-Control', 'public, max-age=86400');
	}

	/**
	 * @OA\Get(
	 *     path="/api/category-random-products/{categoryId}",
	 *     operationId="getCategoryWiseRandomProducts",
	 *     tags={"Frontend-Product"},
	 *     summary="Get 15 random products by category ID (including child categories)",
	 *     description="Returns up to 15 random products from the specified category. If not enough products are found, it searches in child and descendant categories recursively.",
	 *
	 *     @OA\Parameter(
	 *         name="categoryId",
	 *         in="path",
	 *         required=true,
	 *         description="The ID of the category to fetch products from",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success response with product data",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=123),
	 *                     @OA\Property(property="name", type="string", example="Product Name"),
	 *                     @OA\Property(property="sku", type="string", example="SKU123"),
	 *                     @OA\Property(property="price", type="number", format="float", example=100.00),
	 *                     @OA\Property(property="sale_price", type="number", format="float", example=90.00),
	 *                     @OA\Property(property="delivery_days", type="string", format="date", example="2024-06-20"),
	 *                     @OA\Property(property="total_reviews", type="integer", example=12),
	 *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
	 *                     @OA\Property(property="left_stock", type="integer", example=20),
	 *                     @OA\Property(property="currency", type="string", example="USD"),
	 *                     @OA\Property(
	 *                         property="images",
	 *                         type="array",
	 *                         @OA\Items(type="string", example="https://example.com/image.jpg")
	 *                     ),
	 *                     @OA\Property(property="original_price", type="number", example=100.00),
	 *                     @OA\Property(property="front_sale_price", type="number", example=90.00),
	 *                     @OA\Property(property="best_price", type="number", example=90.00)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=400,
	 *         description="Bad Request - category ID not provided",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="error", type="string", example="category_id is required")
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=404,
	 *         description="Not Found - No products found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="No products found in this category or its children")
	 *         )
	 *     )
	 * )
	 */
	public function getCategoryWiseRandomProducts(Request $request, $category)
	{
		$categoryModel = Category::where('id', $category)
		->orWhere('slug', $category)
		->orWhereHas('seoUrl', function ($q) use ($category) {
			$q->where('url', $category);
		})
		->first();

		if (!$categoryModel) {
			return response()->json(['error' => 'Category not found'], 404);
		}

		$categoryId = $categoryModel->id;
		$allCategoryIds = $this->getAllChildCategoryIds($categoryId);
		$allCategoryIds[] = $categoryId; // <-- include parent category

		$products = Product::with([
			'reviews',
			'currency',
			'productSuppliers',
			'sellingUnitAttribute',
			'ingredientsAttribute',
			'seoUrl'
		])
		->where('status', 'published')
		->whereHas('categories', function ($query) use ($allCategoryIds) {
			$query->whereIn('categories.id', $allCategoryIds);
		})
		->inRandomOrder()
		->take(15)
		->get();

		if ($products->isEmpty()) {
			return response()->json(['message' => 'No products found in this category or its children'], 404);
		}

		$data = $products->map(function ($product) {
			// Process images
			$imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
			$cleanedImages = collect($imageArray)->map(function ($item) {
				if (is_string($item) && str_starts_with($item, '[')) {
					$decoded = json_decode($item, true);
					return is_array($decoded) ? $decoded : [$item];
				}
				return [$item];
			})->flatten()->filter()->values();

			// Process alt tags
			$AltArray = is_array($product->alt_tags) ? $product->alt_tags : json_decode($product->alt_tags, true);
			$cleanedAlt = collect($AltArray)->map(function ($item) {
				if (is_string($item) && str_starts_with($item, '[')) {
					$decoded = json_decode($item, true);
					return is_array($decoded) ? $decoded : [$item];
				}
				return [$item];
			})->flatten()->filter()->values();

			// Selling type
			$sellingType = null;
			if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
				$fullValue = $product->sellingUnitAttribute->attribute_value;
				if (strpos($fullValue, '/') !== false) {
					$parts = explode('/', $fullValue);
					$product->sellingUnitAttribute->attribute_value_unit = trim($parts[1]);
				} else {
					$product->sellingUnitAttribute->attribute_value_unit = $fullValue;
				}
			}

			// Calculate per unit price
			$unitsPerCase = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
			$packType = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');

			$basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
			$perUnitPrice = null;
			if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
				$unitValue = (float) $unitsPerCase->attribute_value;
				if ($unitValue > 0) {
					$calculated = round($basePrice / $unitValue, 2);
					$perUnitPrice = $calculated . '/' . ($packType?->attribute_value ?? '');
				}
			}
			$product->per_unit_price = $perUnitPrice;

			$firstSupplier = $product->productSuppliers()
			->with([
				'vendor.country:id,name',
				'vendor.city:id,name'
			])
			->first();

			$price = $firstSupplier ? (float) $firstSupplier->price : null;
			$salePrice = $firstSupplier ? (float) $firstSupplier->sale_price : null;
			$vendorSku = $firstSupplier?->vendor_sku;
			$vendorId = $firstSupplier?->vendor_id;

			return [
				"id" => $product->id,
				"name" => $product->name,
				'category_url' => $product->category_url(),
				'parent_category_url' => $product->parent_category_url(),
				"sku" => $product->sku,
				"url" => $product->seoUrl->url ?? null,
				"total_reviews" => $product->reviews->count(),
				"avg_rating" => $product->reviews->count() > 0 ? $product->reviews->avg('star') : null,
				"left_stock" => $product->left_stock ?? 0,
				"currency" => $product->currency->symbol ?? '$',
				"images" => $cleanedImages,
				"alt_tags" => $cleanedAlt,
				"vendor_sku" => $vendorSku,

				'vendor_country' => $firstSupplier->vendor->country->name ?? null,
				'vendor_city' => $firstSupplier->vendor->city->name ?? null,
				'vendor_address' => $firstSupplier->vendor->address ?? null,
				'vendor_zipcode' => $firstSupplier->vendor->zipcode ?? null,

				"price" => $price ?? 0,
				"sale_price" => $salePrice ?? 0,
				"original_price" => $price ?? 0,
				"front_sale_price" => $salePrice ?: $price ?? 0,
				"best_price" => $price ?? 0,
				"selling_type" => $sellingType,
				"per_unit_price" => $product->per_unit_price,
				"vendor_id" => $vendorId,
				"map" => $firstSupplier ? (float) $firstSupplier->map : 0,
				"inventory" => $firstSupplier->inventory ?? null,
				"in_stock" => $firstSupplier->in_stock ?? null,
				"delivery_days" => $firstSupplier->delivery_days ?? null,
				"return_policy" => $firstSupplier->return_policy ?? null,
				"free_shipping" => $firstSupplier->free_shipping ?? null,
				"warranty_information" => $firstSupplier->warranty_information ?? null,
				'min_quantity' => $firstSupplier->min_quantity ?? 0,
				'is_fixed' => $firstSupplier->is_fixed ?? 0,
				'quote_available' => $product->quote_available ?? null,
				'isRequired' => $product->isRequired,
			];

		});

		return response()->json([//
			'success' => true,
			'data' => $data,
		])->header('Cache-Control', 'public, max-age=86400');
	}

	/**
	 * @OA\Get(
	 *     path="/api/category-random-products-guest/{categoryId}",
	 *     operationId="getCategoryWiseRandomProductsForUser",
	 *     tags={"Frontend-Product"},
	 *     summary="Get 15 random products by category ID for logged-in users (with wishlist info)",
	 *     description="Returns up to 15 random products from the specified category and child categories, along with wishlist info for logged-in users.",
	 *
	 *     @OA\Parameter(
	 *         name="categoryId",
	 *         in="path",
	 *         required=true,
	 *         description="The ID of the category to fetch products from",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success response with product and wishlist data",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=123),
	 *                     @OA\Property(property="name", type="string", example="Product Name"),
	 *                     @OA\Property(property="sku", type="string", example="SKU123"),
	 *                     @OA\Property(property="price", type="number", example=100.00),
	 *                     @OA\Property(property="sale_price", type="number", example=90.00),
	 *                     @OA\Property(property="delivery_days", type="string", format="date", example="2024-06-20"),
	 *                     @OA\Property(property="total_reviews", type="integer", example=12),
	 *                     @OA\Property(property="avg_rating", type="number", example=4.5),
	 *                     @OA\Property(property="left_stock", type="integer", example=20),
	 *                     @OA\Property(property="currency", type="string", example="USD"),
	 *                     @OA\Property(property="images", type="array", @OA\Items(type="string")),
	 *                     @OA\Property(property="original_price", type="number", example=100.00),
	 *                     @OA\Property(property="front_sale_price", type="number", example=90.00),
	 *                     @OA\Property(property="best_price", type="number", example=90.00),
	 *                     @OA\Property(property="is_in_wishlist", type="boolean", example=true)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Login required"
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=404,
	 *         description="Not Found - No products found"
	 *     )
	 * )
	 */
	public function getCategoryWiseRandomProductsForUser(Request $request, $category)
	{
		// Auth and wishlist logic
		$userId = Auth::id();
		$wishlistProductIds = [];

		if ($userId) {
			$wishlistProductIds = DB::table('ec_wish_lists')
			->where('customer_id', $userId)
			->pluck('product_id')
			->map(fn($id) => (int) $id)
			->toArray();
		} else {
			$wishlistProductIds = session()->get('guest_wishlist', []);
		}

		$categoryModel = Category::where('id', $category)
		->orWhere('slug', $category)
		->first();

		if (!$categoryModel) {
			return response()->json(['error' => 'Category not found'], 404);
		}

		$categoryId = $categoryModel->id;
		$allCategoryIds = $this->getAllChildCategoryIds($categoryId);
		$allCategoryIds[] = $categoryId; // <-- include parent category


		$products = Product::with(['reviews', 'currency', 'productSuppliers', 'sellingUnitAttribute', 'ingredientsAttribute', 'seoUrl']) // add seoUrl here
		->where('status', 'published')

		->whereHas('categories', function ($query) use ($allCategoryIds) {
			$query->whereIn('categories.id', $allCategoryIds);
		})
		->inRandomOrder()
		->take(15)
		->get();


		if ($products->isEmpty()) {
			return response()->json(['message' => 'No products found in this category or its children'], 404);
		}

		$transformed = $products->map(function ($product) use ($wishlistProductIds) {
			// $product->images = collect($product->images)->map(function ($image) {
			//     return filter_var($image, FILTER_VALIDATE_URL) ? $image : url('storage/' . ltrim($image, '/'));
			// });

			// $videoPaths = json_decode($product->video_path, true) ?? [];
			// $product->video_path = collect($videoPaths)->map(function ($video) {
			//     return filter_var($video, FILTER_VALIDATE_URL) ? $video : url('storage/' . ltrim($video, '/'));
			// });
			$imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
			$cleanedImages = collect($imageArray)->map(function ($item) {
				if (is_string($item) && str_starts_with($item, '[')) {
					$decoded = json_decode($item, true);
					return is_array($decoded) ? $decoded : [$item];
				}
				return [$item];
			})->flatten()->filter()->values();

			$AltArray = is_array($product->alt_tags) ? $product->alt_tags : json_decode($product->alt_tags, true);
			$cleanedAlt = collect($AltArray)->map(function ($item) {
				if (is_string($item) && str_starts_with($item, '[')) {
					$decoded = json_decode($item, true);
					return is_array($decoded) ? $decoded : [$item];
				}
				return [$item];
			})->flatten()->filter()->values();
			$videoPaths = json_decode($product->video_path, true);
			$product->video_path = collect($videoPaths)->map(function ($video) {
				return $video;
			});


			$totalReviews = $product->reviews->count();
			$avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
			$quantity = $product->quantity ?? 0;
			$unitsSold = $product->units_sold ?? 0;
			$leftStock = $quantity - $unitsSold;
			$sellingType = null;
			if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
				$fullValue = $product->sellingUnitAttribute->attribute_value;
				if (strpos($fullValue, '/') !== false) {
					$parts = explode('/', $fullValue);
					$product->sellingUnitAttribute->attribute_value_unit = trim($parts[1]);
				} else {
					$product->sellingUnitAttribute->attribute_value_unit = $fullValue;
				}
			}
			if ($product->ingredientsAttribute && $product->ingredientsAttribute->attribute_value) {
				$fullValue = $product->ingredientsAttribute->attribute_value;
			}

			// Calculate per unit price
			$unitsPerCase = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
			$packType = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');


			$basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
			$perUnitPrice = null;

			if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
				$unitValue = (float) $unitsPerCase->attribute_value;
				if ($unitValue > 0) {
					$calculated = round($basePrice / $unitValue, 2);
					$perUnitPrice = $calculated . '/' . ($packType?->attribute_value ?? '');
				}
			}

			$product->per_unit_price = $perUnitPrice;

			$firstSupplier = $product->productSuppliers()
			->with([
				'vendor.country:id,name',
				'vendor.city:id,name'
			])
			->first();

			return [
				'id' => $product->id,
				'name' => $product->name,
				'category_url' => $product->category_url(),
				'parent_category_url' => $product->parent_category_url(),
				"images" => $cleanedImages,
				"alt_tags" => $cleanedAlt,
				'video_url' => $product->video_url,
				'video_path' => $product->video_path,
				'sku' => $product->sku,
				"url" => $product->seoUrl->url ?? null,
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
				'vendor_sku' => $firstSupplier->vendor_sku ?? null,

				'vendor_country' => $firstSupplier->vendor->country->name ?? null,
				'vendor_city' => $firstSupplier->vendor->city->name ?? null,
				'vendor_address' => $firstSupplier->vendor->address ?? null,
				'vendor_zipcode' => $firstSupplier->vendor->zipcode ?? null,

				'price' => (float) ($firstSupplier->price ?? $product->price ?? 0),
				'sale_price' => (float) ($firstSupplier->sale_price ?? $product->sale_price ?? 0),
				'original_price' => (float) ($firstSupplier->price ?? $product->price ?? 0),
				'front_sale_price' => (float) ($firstSupplier->sale_price ?? $product->sale_price ?? 0),
				"best_price" => (float) $firstSupplier->price,
				"selling_type" => $sellingType,
				"per_unit_price" => $product->per_unit_price,
				'vendor_id' => $firstSupplier->vendor_id ?? null,
				'map' => (float) $firstSupplier->map ?? null,
				'inventory' => $firstSupplier->inventory ?? null,
				'in_stock' => $firstSupplier->in_stock ?? null,
				'delivery_days' => $firstSupplier->delivery_days ?? null,
				'return_policy' => $firstSupplier->return_policy ?? null,
				'free_shipping' => $firstSupplier->free_shipping ?? null,
				'warranty_information' => $firstSupplier->warranty_information ?? null,
				'min_quantity' => $firstSupplier->min_quantity ?? 0,
				'is_fixed' => $firstSupplier->is_fixed ?? 0,
				'quote_available' => $product->quote_available ?? null,
				'isRequired' => $product->isRequired,
			];
		});

		return response()->json([//
			'success' => true,
			'data' => $transformed
		])->header('Cache-Control', 'public, max-age=86400');
	}

	private function getAllChildCategoryIds($categoryId)
	{
		// Get all categories
		$allCategories = Category::select('id', 'parent_id')->get();

		// Build a lookup table
		$childrenMap = [];
		foreach ($allCategories as $cat) {
			$childrenMap[$cat->parent_id][] = $cat->id;
		}

		// Recursively gather all children
		$stack = [$categoryId];
		$result = [];

		while (!empty($stack)) {
			$current = array_pop($stack);
			$result[] = $current;
			if (isset($childrenMap[$current])) {
				foreach ($childrenMap[$current] as $childId) {
					$stack[] = $childId;
				}
			}
		}

		return $result;
	}

	private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Http\Request $request)
	{
		// Log the request to ensure you're receiving the correct parameters
		\Log::info($request->all());
		\Log::info('Request Parameters:', $request->all());
		// Apply ID filter
		if ($request->has('id')) {
			$id = $request->input('id');
			$query->where('id', $id);
			\Log::info('Filter by ID: ' . $id);
		}

		// Search filters
		// if ($request->has('search')) {
		//     $searchTerm = $request->input('search');
		//     $query->where(function($q) use ($searchTerm) {
		//         $q->where('name', 'like', '%' . $searchTerm . '%')
		//           ->orWhere('sku', 'like', '%' . $searchTerm . '%');
		//     });
		// }

		// Search filters with category and brand

		// Search filter (product name or SKU)

		if ($request->has('search')) {
			$searchTerm = $request->input('search');
			$query->where(function ($q) use ($searchTerm) {
				$q->where('name', 'like', '%' . $searchTerm . '%')
				->orWhere('sku', 'like', '%' . $searchTerm . '%')
				->orWhereHas('categories', function ($q) use ($searchTerm) {
					$q->where('name', 'like', '%' . $searchTerm . '%');
				})
				->orWhereHas('brand', function ($q) use ($searchTerm) {
					$q->where('name', 'like', '%' . $searchTerm . '%');
				});
			});
		}

		if ($request->has('name')) {
			$query->where('name', 'LIKE', '%' . $request->input('name') . '%');
		}

		if ($request->has('description')) {
			$query->where('description', 'LIKE', '%' . $request->input('description') . '%');
		}

		// SKU filter
		if ($request->has('sku')) {
			$skus = $request->input('sku');
			if (is_array($skus)) {
				$query->whereIn('sku', $skus);
			} else {
				$query->where('sku', $skus);
			}
		}

		// Status filter
		if ($request->has('status')) {
			$query->where('status', $request->input('status'));
		}

		// Stock status filter
		if ($request->has('stock_status')) {
			$query->where('stock_status', $request->input('stock_status'));
		}

		// Numerical filters
		// Delivery Days
		if ($request->has('delivery_days')) {
			$query->where('delivery_days', $request->input('delivery_days'));
		}
		if ($request->has('price_min')) {
			$query->where('price', '>=', $request->input('price_min'));
		}

		if ($request->has('price_max')) {
			$query->where('price', '<=', $request->input('price_max'));
		}

		if ($request->has('quantity_min')) {
			$query->where('quantity', '>=', $request->input('quantity_min'));
		}

		if ($request->has('quantity_max')) {
			$query->where('quantity', '<=', $request->input('quantity_max'));
		}

		// Date filters
		if ($request->has('start_date')) {
			$query->where('created_at', '>=', $request->input('start_date'));
		}

		if ($request->has('end_date')) {
			$query->where('created_at', '<=', $request->input('end_date'));
		}


		if ($request->has('is_featured')) {
			$query->where('is_featured', $request->input('is_featured'));
		}

		if ($request->has('rating')) {
			$rating = $request->input('rating');
			$query->whereHas('reviews', function ($q) use ($rating) {
				$q->selectRaw('product_id, AVG(star) as avg_rating') // Include product_id in the select statement
				->groupBy('product_id')
					->havingRaw('AVG(star) = ?', [$rating]); // Change from >= to =
				});
		}

		if ($request->has('brand_id')) {
			$brandIds = $request->input('brand_id');

			// Convert to array if needed
			if (!is_array($brandIds)) {
				$brandIds = explode(',', $brandIds);
			}

			// Ensure brand IDs are integers
			$brandIds = array_map('intval', $brandIds);

			\Log::info('Filtering by Brand IDs: ', $brandIds);

			// Apply filter on the existing query object
			$query->whereIn('brand_id', $brandIds);
		}
		// Continue with any other filters or sorting options



		// Brand filter by name
		if ($request->has('brand_names')) {
			$brandNames = $request->input('brand_names');

			// Check if $brandNames is an array
			if (is_array($brandNames)) {
				// Fetch brand IDs based on names
				$brandIds = Brand::whereIn('name', $brandNames)->pluck('id');

				// Apply the filter using brand IDs sd
				$query->whereIn('brand_id', $brandIds);
			} else {
				// If it's a single name, convert it into an array
				$brandIds = Brand::where('name', $brandNames)->pluck('id');
				$query->whereIn('brand_id', $brandIds);
			}
		}

		// Sort by price if specified, else default to the general `sort_by` handling
		if ($request->has('sort_by_price')) {
			$order = strtolower($request->input('sort_by_price')); // Normalize input
			if (in_array($order, ['asc', 'desc'])) {
				$query->orderBy('sale_price', $order);
				\Log::info("Sorting by price in $order order");
			} else {
				\Log::info("Invalid sort_by_price parameter: $order");
			}
		} else {
			// General sorting by other columns
			$allowedSortBy = ['id', 'price', 'created_at', 'name'];
			$sortBy = $request->input('sort_by', 'id');
			$sortOrder = strtolower($request->input('sort_order', 'asc'));

			if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, ['asc', 'desc'])) {
				$query->orderBy($sortBy, $sortOrder);
				\Log::info("Sorting by: $sortBy in $sortOrder order");
			} else {
				\Log::info("Invalid sort parameters: sort_by = $sortBy, sort_order = $sortOrder");
			}
		}

		//$products = $query->orderBy($sortBy, 'asc')->paginate($request->input('per_page', 15)); // Pagination

		//  $products = $query->orderBy($sortBy, 'asc'); // Pagination


		// Log the final SQL query for debugging
		\Log::info($query->toSql());
		\Log::info($query->getBindings());
	}

	/**
	 * @OA\Get(
	 *     path="/api/product-info/{slug}",
	 *     operationId="getProductInfoBySlug",
	 *     tags={"Frontend-Product"},
	 *     summary="Get product info by URL slug",
	 *     description="Returns brand, delivery days, return policy, and shipping info for a product using slug from seo_management table.",
	 *     @OA\Parameter(
	 *         name="slug",
	 *         in="path",
	 *         description="Slug from the SEO management table (url column)",
	 *         required=true,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product details found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="brand", type="string", example="Apple"),
	 *             @OA\Property(property="delivery_days", type="string", example="2-4 Business Days"),
	 *             @OA\Property(property="return_policy", type="string", example="15 Days Return"),
	 *             @OA\Property(property="shipping", type="string", example="Free Shipping")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Invalid product URL or product not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product not found")
	 *         )
	 *     )
	 * )
	 */
	public function getProductInfoBySlug($slug)
	{
		// Get the product ID from seo_management table
		$seo = SeoManagement::where('url', $slug)->first();

		if (!$seo) {
			return response()->json(['message' => 'Invalid product URL'], 404);
		}

		$product = Product::with(['brand', 'productSuppliers'])->find($seo->relational_id);

		if (!$product) {
			return response()->json(['message' => 'Product not found'], 404);
		}

		return response()->json([
			'Brand' => $product->brand ? $product->brand->name : null,
			'Delivery_days' => $product->productSuppliers->first()->delivery_days ?? null,
			'Return_policy' => $product->productSuppliers->first()->return_policy ?? null,
			'Free_shipping' => ($product->productSuppliers->first()->free_shipping ?? null) == 1 ? 'Yes' : 'No',

		])->header('Cache-Control', 'public, max-age=86400');
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/sale-categories/{id}",
	 *     summary="Get all sale products under a specific category",
	 *     description="Returns all published products in a category with sale_price > 0. Supports filters like price, rating, stock, and sorting.",
	 *     tags={"Frontend Products"},
	 *
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Category ID",
	 *         required=false,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         description="Items per page",
	 *         required=false,
	 *         @OA\Schema(type="integer", default=10)
	 *     ),
	 *     @OA\Parameter(
	 *         name="min_price",
	 *         in="query",
	 *         description="Minimum sale price",
	 *         required=false,
	 *         @OA\Schema(type="number", example=10)
	 *     ),
	 *     @OA\Parameter(
	 *         name="max_price",
	 *         in="query",
	 *         description="Maximum sale price",
	 *         required=false,
	 *         @OA\Schema(type="number", example=200)
	 *     ),
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search by product name",
	 *         required=false,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Parameter(
	 *         name="min_rating",
	 *         in="query",
	 *         description="Minimum average rating",
	 *         required=false,
	 *         @OA\Schema(type="number")
	 *     ),
	 *     @OA\Parameter(
	 *         name="in_stock",
	 *         in="query",
	 *         description="Filter only in-stock products (1=yes)",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort",
	 *         in="query",
	 *         description="Sorting: price_asc, price_desc, latest, rating_desc",
	 *         required=false,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Parameter(
	 *         name="brand_id",
	 *         in="query",
	 *         description="Filter by brand ID (comma-separated for multiple brands)",
	 *         required=false,
	 *         @OA\Schema(type="string")
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=200,
	 *         description="Products fetched successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Sale products fetched successfully"),
	 *             @OA\Property(property="pagination", type="object",
	 *                 @OA\Property(property="current_page", type="integer", example=1),
	 *                 @OA\Property(property="last_page", type="integer", example=5),
	 *                 @OA\Property(property="per_page", type="integer", example=10),
	 *                 @OA\Property(property="total", type="integer", example=50),
	 *                 @OA\Property(property="next_page_url", type="string", example="https://.../sale-categories?page=2"),
	 *                 @OA\Property(property="prev_page_url", type="string", example=null),
	 *                 @OA\Property(property="has_more", type="boolean", example=true),
	 *                 @OA\Property(property="links", type="array", @OA\Items(type="string"))
	 *             ),
	 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=404,
	 *         description="Category not found"
	 *     )
	 * )
	 */
	public function saleProductsByCategory(Request $request, $id = null)
	{
		$perPage = $request->get('per_page', 10);

		// Filters
		$minPrice = $request->get('min_price');
		$maxPrice = $request->get('max_price');
		$search = $request->get('search');
		$minRating = $request->get('min_rating');
		$onlyInStock = $request->get('in_stock');
		$sort = $request->get('sort');
		$brandId = $request->get('brand_id');
		$categoryId = $request->get('category_id') ?? $id;

		// Custom category sort sequence
		$categoryOrderNames = [
			'White Chinaware', 'Coloured Chinaware', 'Cutlery', 'Glassware',
			'Glass Racks & Extenders', 'Serving & Table Accessories', 'Salt & Pepper Mills',
			'Bread Box & Baskets', 'Kitchen Utensils & Tools', 'Pizza Utensils & Tools',
			'Pastry & Bakery', 'Cast Iron', 'Buffetware', 'Disposable Cutlery & Napkins',
			'Bar Items', 'Child Friendly'
		];

		$categorySortMap = [];
		$debugFoundCategories = [];

		foreach ($categoryOrderNames as $index => $name) {
			// Use LIKE to be more improved against spacing/case issues
			$cat = Category::where('name', 'LIKE', '%' . $name . '%')->first();
			
			if ($cat) {
				// Assign the parent category ID to this index (priority)
				$categorySortMap[$cat->id] = $index;
				$debugFoundCategories[$name] = $cat->id;

				// Also assign all DESCENDANT category IDs to this same index
				// This ensures "Black Dinnerware" gets the same priority as "Coloured Chinaware"
				$descendants = $cat->getLeafCategories()->pluck('id');
				foreach ($descendants as $childId) {
					// Only set if not already set (higher priority wins if overlap)
					if (!isset($categorySortMap[$childId])) {
						$categorySortMap[$childId] = $index;
					}
				}
				
				// Also get intermediate children if getLeafCategories only returns tips
				// A safer approach for a tree is to just get all children recursive
				$allChildren = $cat->childrenRecursive; 
				// Flatten function to get all IDs
				$traverse = function($categories) use (&$traverse, &$categorySortMap, $index) {
					foreach ($categories as $category) {
						if (!isset($categorySortMap[$category->id])) {
							$categorySortMap[$category->id] = $index;
						}
						$traverse($category->childrenRecursive);
					}
				};
				$traverse($allChildren);

			} else {
				$debugFoundCategories[$name] = 'NOT FOUND';
			}
		}

		// Log the count of IDs found for debugging
		Log::info('Category Sort Map Count: ' . count($categorySortMap));

		// Wishlist logic
		$userId = Auth::id();
		$wishlistProductIds = $userId
		? DB::table('ec_wish_lists')->where('customer_id', $userId)->pluck('product_id')->map(fn($id) => (int)$id)->toArray()
		: session()->get('guest_wishlist', []);

		// Base Query → No category filter by default
		$query = Product::query()
		->where('status', 'published')
		->whereHas('productSuppliers', function ($q) {
			$q->whereNotNull('sale_price')
			  ->where('sale_price', '>', 0)
			  ->where('updated_at', '>=', '2026-02-05');
		})
		->with([
			'reviews:id,product_id,star',
			'currency',
			'productSuppliers',
			'seoUrl',
			'sellingUnitAttribute',
			'ingredientsAttribute',
			'brand:id,name',
			'productAttributes' => function ($query) {
				$query->whereHas('attributeDetails', function ($q) {
					$q->whereIn('name', ['Units per Case', 'Pack Type']);
				});
			}
		]);

		// If Category ID is provided, apply category filter
		if ($categoryId) {
			$category = Category::find($categoryId);

			if (!$category) {
				return response()->json([
					'success' => false,
					'message' => 'Category not found',
				], 404);
			}

			// Filter by category
			$query->whereHas('categories', function ($q) use ($categoryId) {
				$q->where('category_id', $categoryId);
			});
		}

		// ---------------- Filters -----------------

		if ($search) {
			$query->where(function($q) use ($search) {
				// Smart search with fuzzy matching for typos
				$q->where('name', 'LIKE', "%$search%")
					->orWhere('sku', 'LIKE', "%$search%")
					// Fuzzy match using SOUNDEX for phonetic similarity
					->orWhereRaw('SOUNDEX(name) = SOUNDEX(?)', [$search])
					// Search in brand names
					->orWhereHas('brand', function($brandQ) use ($search) {
						$brandQ->where('name', 'LIKE', "%$search%")
							   ->orWhereRaw('SOUNDEX(name) = SOUNDEX(?)', [$search]);
					})
					// Concatenated words search (e.g. "porcelainplate" matches "Porcelain Plate")
					->orWhereRaw("REPLACE(name, ' ', '') LIKE ?", ["%{$search}%"])
					// Word-by-word AND matching (All words must be present in Name, SKU, or Brand)
					->orWhere(function($subQ) use ($search) {
						$words = explode(' ', $search);
						if (count($words) > 1) {
							foreach ($words as $word) {
								if (strlen($word) > 2) {
									$subQ->where(function($wordQ) use ($word) {
										$wordQ->where('name', 'LIKE', "%$word%")
											  ->orWhere('sku', 'LIKE', "%$word%")
											  ->orWhereHas('brand', function($bq) use ($word) {
												  $bq->where('name', 'LIKE', "%$word%");
											  });
									});
								}
							}
						} else {
							$subQ->whereRaw('0 = 1'); // Only 1 word, handled by main logic
						}
					});

					// Consonant-based fuzzy search for typos (e.g. "ballini" matches "Bellini")
					// We only do this if the search term is reasonable length to avoid massive matches
					if (strlen($search) >= 4) {
						// Create a pattern where vowels are wildcards
						// e.g. "ballini" -> "%b%l%l%n%"
						$consonants = preg_replace('/[aeiouyAEIOUY\s]+/', '%', $search);
						// Ensure we don't have multiple % side by side if possible (preg_replace handles it but good to be sure)
						$fuzzyPattern = '%' . $consonants . '%';
						
						// Only apply if we have enough "skeleton" to match on
						if (strlen($consonants) >= 3) {
							$q->orWhere('name', 'LIKE', $fuzzyPattern);
						}
					}
			});
		}


		if ($minPrice) {
			$query->whereHas('productSuppliers', function ($q) use ($minPrice) {
				$q->whereRaw('(CASE WHEN sale_price > 0 THEN sale_price ELSE price END) >= ?', [$minPrice]);
			});
		}

		if ($maxPrice) {
			$query->whereHas('productSuppliers', function ($q) use ($maxPrice) {
				$q->whereRaw('(CASE WHEN sale_price > 0 THEN sale_price ELSE price END) <= ?', [$maxPrice]);
			});
		}

		if ($minRating) {
			$query->whereHas('reviews', function ($r) use ($minRating) {
				$r->havingRaw('AVG(star) >= ?', [$minRating]);
			});
		}

		if ($onlyInStock == 1) {
			$query->whereHas('productSuppliers', function ($q) {
				$q->where('inventory', '>', 0)->where('in_stock', 1);
			});
		}

		// Brand filter
		if ($brandId) {
			$brandIds = is_array($brandId) ? $brandId : explode(',', $brandId);
			$brandIds = array_map('intval', $brandIds);
			$query->whereIn('brand_id', $brandIds);
		}

		// ---------------- Sorting -----------------

		if ($sort) {
			switch ($sort) {
				case 'price_asc':
				$query->orderByRaw("(SELECT sale_price FROM product_suppliers WHERE product_suppliers.product_id = ec_products.id LIMIT 1) ASC");
				break;

				case 'price_desc':
				$query->orderByRaw("(SELECT sale_price FROM product_suppliers WHERE product_suppliers.product_id = ec_products.id LIMIT 1) DESC");
				break;

				case 'latest':
				$query->orderBy('created_at', 'DESC');
				break;

				case 'rating_desc':
				$query->withAvg('reviews', 'star')->orderBy('reviews_avg_star', 'DESC');
				break;
			}
		} else {
			// Default sort: Category-wise (grouped by custom order)
			if (!empty($categorySortMap)) {
				// We need to build a CASE statement for the sort map
				// WHEN category_id = ID THEN INDEX
				$whens = [];
				$ids = [];
				foreach ($categorySortMap as $catId => $index) {
					$whens[] = "WHEN pc_sort.category_id = $catId THEN $index";
					$ids[] = $catId;
				}
				$whenString = implode(' ', $whens);
				$idsString = implode(',', $ids);

				$query->leftJoin('product_categories as pc_sort', 'ec_products.id', '=', 'pc_sort.product_id')
					->select('ec_products.*')
					// Use MIN to pick the highest priority category (lowest index)
					->orderByRaw("MIN(CASE $whenString ELSE 999999 END) ASC")
					->orderBy('brand_id', 'ASC')
					->orderBy('ec_products.id', 'ASC')
					->groupBy('ec_products.id');
			} else {
				// Fallback to Brand-wise
				$query->orderBy('brand_id', 'ASC')
					->orderBy('id', 'ASC');
			}
		}

		// ---------------- Pagination -----------------

		$products = $query->paginate($perPage);

		// Get unique colors/categories/brands from the filtered result set
		$allFilteredIds = $query->clone()->reorder()->groupBy('ec_products.id')->pluck('ec_products.id')->toArray();

		// Get unique brands from the filtered products
		$brands = \App\Models\Brand::whereIn('id', function($q) use ($allFilteredIds) {
				$q->from('ec_products')->whereIn('id', $allFilteredIds)->select('brand_id')->distinct();
			})
			->select('id', 'name')
			->orderBy('name', 'ASC')
			->get()
			->values();

		// Get unique categories from the filtered products
		$categories = Category::whereIn('id', function($q) use ($allFilteredIds) {
				$q->from('product_categories')->whereIn('product_id', $allFilteredIds)->select('category_id')->distinct();
			})
			->select('id', 'name')
			->orderBy('name', 'ASC')
			->get()
			->values();

		// ---------------- Transform Response -----------------

		$transformed = collect($products->items())->map(function ($product) use ($wishlistProductIds) {

			$imageUrls = is_string($product->images)
			? json_decode($product->images, true)
			: (array) $product->images;

			$altTags = is_string($product->alt_tags)
			? json_decode($product->alt_tags, true)
			: (array) $product->alt_tags;

			$videoPaths = collect(json_decode($product->video_path ?? '[]', true));

			$totalReviews = $product->reviews->count();
			$avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;

			$quantity = $product->quantity ?? 0;
			$unitsSold = $product->units_sold ?? 0;
			$leftStock = $quantity - $unitsSold;

			// Selling Type Logic
			$sellingType = null;
			if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
				$fullValue = $product->sellingUnitAttribute->attribute_value;
				$unit = $fullValue;
				if (strpos($fullValue, '/') !== false) {
					$parts = explode('/', $fullValue);
					$unit = trim($parts[1]);
				}
				$sellingType = [
					'attribute_value' => $fullValue,
					'attribute_value_unit' => $unit
				];
			}


			// Per Unit Price Logic
			$perUnitPrice = null;
			$unitsPerCase = null;
			$packType = null;

			if ($product->productAttributes) {
				$unitsPerCase = $product->productAttributes
				->first(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
				$packType = $product->productAttributes
				->first(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');
			}

			$firstSupplier = $product->productSuppliers->first();
			$currentPrice = $firstSupplier ? ($firstSupplier->sale_price > 0 ? $firstSupplier->sale_price : $firstSupplier->price) : 0;


			if ($currentPrice > 0 && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
				$unitValue = (float) $unitsPerCase->attribute_value;
				if ($unitValue > 0) {
					$calculated = round($currentPrice / $unitValue, 2);
					$perUnitPrice = $calculated . '/' . ($packType?->attribute_value ?? '');
				}
			}

			return [
				'id' => $product->id,
				'name' => $product->name,
				'category_url' => $product->category_url(),
				'parent_category_url' => $product->parent_category_url(),
				'images' => $imageUrls,          // ✅ Proper array of URLs
				'alt_tags' => $altTags,
				'video_path' => $videoPaths,
				'sku' => $product->sku,
				'url' => $product->seoUrl->url ?? null,
				'selling_type' => $sellingType,
				'per_unit_price' => $perUnitPrice,
                'discount_percentage' => ($firstSupplier && $firstSupplier->price > 0) ? round((($firstSupplier->price - $firstSupplier->sale_price) / $firstSupplier->price) * 100, 2) : 0,

				// Brand info
				'brand_id' => $product->brand_id ?? null,
				'brand_name' => $product->brand->name ?? null,

				// Prices
				'price' => (float)($firstSupplier->price ?? 0),
				'sale_price' => (float)($firstSupplier->sale_price ?? 0),
				'original_price' => (float)($firstSupplier->price ?? 0),
				'front_sale_price' => (float)($firstSupplier->sale_price ?? 0),
				'best_price' => (float)($firstSupplier->price ?? 0),

				// Currency
				'currency' => $product->currency?->symbol,
				'currency_title' => $product->currency?->symbol ?? null,

				// Reviews
				'total_reviews' => $totalReviews,
				'avg_rating' => $avgRating,

				// Stock
				'leftStock' => $leftStock,

				// Wishlist
				'in_wishlist' => in_array($product->id, $wishlistProductIds),

				// Supplier details
				'vendor_id' => $firstSupplier->vendor_id ?? null,
				'map' => (float)($firstSupplier->map ?? 0),
				'inventory' => $firstSupplier->inventory ?? null,
				'in_stock' => $firstSupplier->in_stock ?? null,
				'delivery_days' => $firstSupplier->delivery_days ?? null,
				'return_policy' => $firstSupplier->return_policy ?? null,
				'free_shipping' => $firstSupplier->free_shipping ?? null,
				'warranty_information' => $firstSupplier->warranty_information ?? null,
				'min_quantity' => $firstSupplier->min_quantity ?? 0,
				'is_fixed' => $firstSupplier->is_fixed ?? 0,

				// Other info
				'quote_available' => $product->quote_available ?? null,
				'isRequired' => $product->isRequired,
				'debug_category_ids' => $product->categories->pluck('id')->toArray(),
			];
		});

		return response()->json([
			'success'    => true,
			'message'    => $id
			? 'Sale products filtered by category'
			: 'All sale products fetched successfully',

			// Pagination Meta
			'pagination' => [
				'current_page'   => $products->currentPage(),
				'last_page'      => $products->lastPage(),
				'per_page'       => $products->perPage(),
				'total'          => $products->total(),
				'next_page_url'  => $products->nextPageUrl(),
				'prev_page_url'  => $products->previousPageUrl(),
				'has_more'       => $products->hasMorePages(),
				'links'          => $products->linkCollection(), // Full Laravel links
			],

			// Actual Product Data
			'data' => $transformed,

			// Available brands and categories in these results
			'brands' => $brands,
			'categories' => $categories,
			'debug_sort_mapping' => $debugFoundCategories,
		])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
	}

	
}
