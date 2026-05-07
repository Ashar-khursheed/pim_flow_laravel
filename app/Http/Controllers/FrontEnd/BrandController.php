<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Log;


class BrandController extends Controller
{
	private function getWishlistProductIds()
	{
		$userId = Auth::id();

		if ($userId) {
			return Cache::remember("wishlist_user_{$userId}", 60, function () use ($userId) {
				return DB::table('ec_wish_lists')
				->where('customer_id', $userId)
				->pluck('product_id')
				->toArray();
			});
		}

		return session()->get('guest_wishlist', []);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/homebrandproducts",
	 *     tags={"Frontend-Brands"},
	 *     summary="Get all home brand products for authenticated users",
	 *     description="Retrieves the latest 5 brands with at least 10 products each, limited to 10 products per brand. Includes wishlist status for authenticated users.",
	 *     security={{"bearerAuth": {}}},
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search products by name",
	 *         required=false,
	 *         @OA\Schema(type="string", example="iPhone")
	 *     ),
	 *     @OA\Parameter(
	 *         name="price_min",
	 *         in="query",
	 *         description="Minimum price filter",
	 *         required=false,
	 *         @OA\Schema(type="number", format="float", example=100.00)
	 *     ),
	 *     @OA\Parameter(
	 *         name="price_max",
	 *         in="query",
	 *         description="Maximum price filter",
	 *         required=false,
	 *         @OA\Schema(type="number", format="float", example=1000.00)
	 *     ),
	 *     @OA\Parameter(
	 *         name="rating",
	 *         in="query",
	 *         description="Minimum rating filter",
	 *         required=false,
	 *         @OA\Schema(type="number", format="float", minimum=1, maximum=5, example=4.0)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="brand_name", type="string", example="Apple"),
	 *                     @OA\Property(
	 *                         property="products",
	 *                         type="array",
	 *                         @OA\Items(
	 *                             @OA\Property(property="id", type="integer", example=1),
	 *                             @OA\Property(property="name", type="string", example="iPhone 14"),
	 *                             @OA\Property(property="sku", type="string", example="IPH14-001"),
	 *                             @OA\Property(property="price", type="number", format="float", example=999.99),
	 *                             @OA\Property(property="sale_price", type="number", format="float", example=899.99),
	 *                             @OA\Property(property="delivery_days", type="string", example="2024-01-15"),
	 *                             @OA\Property(property="total_reviews", type="integer", example=150),
	 *                             @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
	 *                             @OA\Property(property="left_stock", type="integer", example=25),
	 *                             @OA\Property(property="currency", type="string", example="USD"),
	 *                             @OA\Property(property="in_wishlist", type="boolean", example=false),
	 *                             @OA\Property(
	 *                                 property="images",
	 *                                 type="array",
	 *                                 @OA\Items(type="string", example="https://example.com/storage/products/iphone14.jpg")
	 *                             ),
	 *                             @OA\Property(property="original_price", type="number", format="float", example=999.99),
	 *                             @OA\Property(property="front_sale_price", type="number", format="float", example=999.99),
	 *                             @OA\Property(property="best_price", type="number", format="float", example=999.99)
	 *                         )
	 *                     )
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Unauthenticated.")
	 *         )
	 *     )
	 * )
	 */
	public function getAllHomeBrandProducts(Request $request)
	{
		$wishlistIds = $this->getWishlistProductIds();

		// Preload brands with featured flag and 10+ published products
		$brands = Brand::where('is_featured', 1)
		->whereHas('products', function ($query) {
			$query->where('status', 'published')
			->select('brand_id')
			->groupBy('brand_id')
			->havingRaw('COUNT(*) >= 10');
		})
		->with(['products' => function ($query) {
			$query->where('status', 'published')
					->take(10) // limit at DB level
					->with([
						'productAttributes.attributeDetails:id,name',
						'reviews:id,product_id,star',
						'currency:id,symbol',
						'bestSupplier',
						'seoUrl'
					]);
				}])
		->orderBy('created_at', 'desc')
		->take(5)
		->get();

		// Structure response
		return response()->json([
			'success' => true,
			'data' => $brands->map(function ($brand) use ($request, $wishlistIds) {
				$products = $brand->products->filter(function ($product) use ($request) {
					// Apply filters in memory (cheaper than querying again)
					if ($request->has('search') && !str_contains(strtolower($product->name), strtolower($request->input('search')))) {
						return false;
					}
					if ($request->has('rating') && $product->reviews->avg('star') < $request->rating) {
						return false;
					}
					return true;
				})->take(10); // Final memory-level filter

				return [
					'brand_name' => $brand->name,
					'products' => $products->map(function ($product) use ($wishlistIds) {
						// Preprocess image
						$rawImages = is_array($product->images) ? $product->images : json_decode($product->images, true);
						$imageUrls = collect($rawImages)->flatten()->filter()->values();

						// Selling unit
						$sellingAttr = $product->sellingUnitAttribute;
						$sellingType = null;
						if ($sellingAttr && $sellingAttr->attribute_value) {
							$value = $sellingAttr->attribute_value;
							$unit = strpos($value, '/') !== false ? trim(explode('/', $value)[1]) : $value;
							$sellingType = [
								'attribute_value' => $value,
								'attribute_value_unit' => $unit,
							];
						}
						$bestSupplier = $product->bestSupplier()
						->with([
							'creator:id,first_name,last_name',
							'vendor:id,name,country_id,city_id,address,zipcode',
							'vendor.country:id,name',
							'vendor.city:id,name',
							'latestPriceTracking',
							'latestPriceTracking.creator:id,first_name,last_name',
							'latestInventoryTracking',
							'latestInventoryTracking.creator:id,first_name,last_name',
						])
						->first();
						// Per unit price
						// $unitsPerCase = $product->productAttributes->firstWhere(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
						// $packType = $product->productAttributes->firstWhere(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');
						$unitsPerCase = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
						$packType = optional($product->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');

						$basePrice = null;
						if ($bestSupplier) {
							$basePrice = ($bestSupplier->sale_price > 0) ? $bestSupplier->sale_price : $bestSupplier->price;
						}
						$perUnitPrice = null;
						if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
							$unitValue = (float) $unitsPerCase->attribute_value;
							if ($unitValue > 0) {
								$calculated = round($basePrice / $unitValue, 2);
								$perUnitPrice = $calculated . ' /' . ($packType?->attribute_value ?? '');
							}
						}


						return [
							"id" => $product->id,
							"name" => $product->name,
							"sku" => $product->sku,
							'category_url' => $product->category_url(),
							'parent_category_url' => $product->parent_category_url(),
							'url' => $product->seoUrl->url ?? null,
							"total_reviews" => $product->reviews->count(),
							"avg_rating" => $product->reviews->avg('star'),
							"left_stock" => $product->left_stock ?? 0,
							"currency" => $product->currency->symbol ?? '$',
							"in_wishlist" => in_array($product->id, $wishlistIds),
							"images" => $imageUrls,
							"selling_type" => $sellingType,
							"per_unit_price" => $perUnitPrice,
							'vendor_sku' => $bestSupplier?->vendor_sku ?? null,

							'vendor_country' => $bestSupplier->vendor->country->name ?? null,
							'vendor_city' => $bestSupplier->vendor->city->name ?? null,
							'vendor_address' => $bestSupplier->vendor->address ?? null,
							'vendor_zipcode' => $bestSupplier->vendor->zipcode ?? null,

							'price' => $bestSupplier ? (float) $bestSupplier->price : 0,
							'sale_price' => $bestSupplier ? (float) $bestSupplier->sale_price : 0,
							'original_price'=> $bestSupplier ? (float) $bestSupplier->price : 0,
							'front_sale_price' => $bestSupplier
							? (float) ($bestSupplier->sale_price ?? $bestSupplier->price)
							: 0,
							'best_price'=> $bestSupplier ? (float) $bestSupplier->price : 0,
							'vendor_id' => $bestSupplier?->vendor_id ?? null,
							'map' => $bestSupplier ? (float) $bestSupplier->map : 0,
							'inventory' => $bestSupplier?->inventory ?? null,
							'in_stock' => $bestSupplier?->in_stock ?? null,
							'best_delivery_days' => $bestSupplier?->delivery_days ?? null,
							'delivery_days' => $bestSupplier?->delivery_days ?? null,
							'return_policy' => $bestSupplier?->return_policy ?? null,
							'free_shipping' => $bestSupplier?->free_shipping ?? null,
							'warranty_information' => $bestSupplier?->warranty_information ?? null,
							'min_quantity' => $bestSupplier->min_quantity ?? 0,
							'is_fixed' => $bestSupplier->is_fixed ?? 0,
							'quote_available' => $product->quote_available ?? null,
							'isRequired' => $product->isRequired,

						];

					})
				];//
			}),//
		]);//
	}//

	/**
	 * @OA\Get(
	 *     path="/api/frontend/brandguestproducts",
	 *     tags={"Frontend-Brands"},
	 *     summary="Get all brand products for guest users",
	 *     description="Retrieves the latest 5 brands with at least 10 products each, optimized for guest users without wishlist functionality.",
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search products by name",
	 *         required=false,
	 *         @OA\Schema(type="string", example="Samsung")
	 *     ),
	 *     @OA\Parameter(
	 *         name="price_min",
	 *         in="query",
	 *         description="Minimum price filter",
	 *         required=false,
	 *         @OA\Schema(type="number", format="float", example=50.00)
	 *     ),
	 *     @OA\Parameter(
	 *         name="price_max",
	 *         in="query",
	 *         description="Maximum price filter",
	 *         required=false,
	 *         @OA\Schema(type="number", format="float", example=2000.00)
	 *     ),
	 *     @OA\Parameter(
	 *         name="rating",
	 *         in="query",
	 *         description="Minimum rating filter",
	 *         required=false,
	 *         @OA\Schema(type="number", format="float", minimum=1, maximum=5, example=3.5)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="brand_name", type="string", example="Samsung"),
	 *                     @OA\Property(
	 *                         property="products",
	 *                         type="array",
	 *                         @OA\Items(
	 *                             @OA\Property(property="id", type="integer", example=2),
	 *                             @OA\Property(property="name", type="string", example="Galaxy S23"),
	 *                             @OA\Property(property="sku", type="string", example="GAL-S23-001"),
	 *                             @OA\Property(property="price", type="number", format="float", example=799.99),
	 *                             @OA\Property(property="original_price", type="number", format="float", example=899.99),
	 *                             @OA\Property(property="sale_price", type="number", format="float", example=799.99),
	 *                             @OA\Property(property="delivery_days", type="string", example="2024-01-10"),
	 *                             @OA\Property(property="total_reviews", type="integer", example=89),
	 *                             @OA\Property(property="avg_rating", type="number", format="float", example=4.2),
	 *                             @OA\Property(property="left_stock", type="integer", example=15),
	 *                             @OA\Property(property="currency", type="string", example="USD"),
	 *                             @OA\Property(
	 *                                 property="images",
	 *                                 type="array",
	 *                                 @OA\Items(type="string", example="https://example.com/storage/galaxy-s23.jpg")
	 *                             ),
	 *                             @OA\Property(property="front_sale_price", type="number", format="float", example=799.99),
	 *                             @OA\Property(property="best_price", type="number", format="float", example=799.99)
	 *                         )
	 *                     )
	 *                 )
	 *             )
	 *         )
	 *     )
	 * )
	 */
	public function getAllBrandGuestProducts(Request $request)
	{
		// Subquery for best price and delivery days by SKU (only published products)
		$subQuery = Product::select('sku')
		->where('status', 'published')
		->groupBy('sku');

		   // Preload brands with featured flag and 10+ published products
		$brands = Brand::where('is_featured', 1)
		->whereHas('products', function ($query) {
			$query->where('status', 'published')
			->select('brand_id')
			->groupBy('brand_id')
			->havingRaw('COUNT(*) >= 10');
		})
		->with(['products' => function ($query) {
			$query->where('status', 'published')
				->take(10) // limit at DB level
				->with([
					'productAttributes.attributeDetails:id,name',
					'reviews:id,product_id,star',
					'currency:id,symbol',
					'seoUrl'
				]);
			}])
		->orderBy('created_at', 'desc')
		->take(5)
		->get();


		return response()->json([
			'success' => true,
			'data' => $brands->map(function ($brand) use ($request, $subQuery) {
				// Filter and limit products to 10 for each brand (only published)
				$products = $brand->products()
				->where('status', 'published')
				->when($request->has('search'), function ($query) use ($request) {
					$query->where('name', 'like', '%' . $request->input('search') . '%');
				})

				->when($request->has('rating'), function ($query) use ($request) {
					$query->whereHas('reviews', function ($q) use ($request) {
						$q->selectRaw('AVG(star) as avg_rating')
						->groupBy('product_id')
						->havingRaw('AVG(star) >= ?', [$request->input('rating')]);
					});
				})
				->take(10)->pluck('id');

				// Fetch product details with joined best_price and eager load (only published)
				$productDetails = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
					$join->on('ec_products.sku', '=', 'best_products.sku');
				})
				->whereIn('ec_products.id', $products)
				->where('ec_products.status', 'published')
				->with(['reviews', 'currency', 'bestSupplier' , 'vendors' ,   'productAttributes' => function ($query) {
					$query->whereHas('attributeDetails', function ($q) {
						$q->whereIn('name', ['Units per Case', 'Pack Type']);
					});
				},])
				->get()
				->keyBy('id');

				return [
					'brand_name' => $brand->name,
					'products' => $productDetails->map(function ($details) {
						$totalReviews = $details->reviews->count();
						$avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
						$leftStock = ($details->quantity ?? 0) - ($details->units_sold ?? 0);
						$currencyTitle = $details->currency->symbol ?? $details->price;

						   // Assuming $details->images is already decoded once and looks like:
						$rawImageData = $details->images;

						   // Step 1: Make sure it's an array
						$imageArray = is_array($rawImageData) ? $rawImageData : json_decode($rawImageData, true);

						   // Step 2: Decode the nested JSON strings (if any)
						$cleanedImages = collect($imageArray)->map(function ($item) {
							   // Check if it's a string and a valid JSON array
							if (is_string($item) && str_starts_with($item, '[')) {
								$decoded = json_decode($item, true);
								return is_array($decoded) ? $decoded : [$item];
							}
							   return [$item]; // already a normal value
							})->flatten()->filter()->values();

						   // Output
						$imageUrls = $cleanedImages;

						$sellingType=null;
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
						$bestSupplier = $product->bestSupplier()
						->with([
							'creator:id,first_name,last_name',
							'vendor:id,name,country_id,city_id,address,zipcode',
							'vendor.country:id,name',
							'vendor.city:id,name',
							'latestPriceTracking',
							'latestPriceTracking.creator:id,first_name,last_name',
							'latestInventoryTracking',
							'latestInventoryTracking.creator:id,first_name,last_name',
						])
						->first();

						// Calculate per unit price
						// $unitsPerCase = $details->per_unit_price_attributes->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
						// $packType = $details->per_unit_price_attributes->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');


						$unitsPerCase = optional($details->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
						$packType = optional($details->per_unit_price_attributes)->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');

						$basePrice = null;
						if ($bestSupplier) {
							$basePrice = ($bestSupplier->sale_price > 0) ? $bestSupplier->sale_price : $bestSupplier->price;
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

						return [
							'id' => $details->id,
							'name' => $details->name,
							'category_url' => $details->category_url(),
							'parent_category_url' => $details->parent_category_url(),
							'sku' => $details->sku,
							'url' => $details->seoUrl->url ?? null,
							'total_reviews' => $totalReviews,
							'avg_rating' => $avgRating,
							'left_stock' => $leftStock,
							'currency' => $currencyTitle,
							'images' => $imageUrls,
							'selling_type' => $sellingType,
							'vendor_id' => $bestSupplier->vendor_id ?? null,
							'per_unit_price' => $details->per_unit_price,
							'vendor_sku' => $bestSupplier->vendor_sku ?? null,

							'vendor_country' => $bestSupplier->vendor->country->name ?? null,
							'vendor_city' => $bestSupplier->vendor->city->name ?? null,
							'vendor_address' => $bestSupplier->vendor->address ?? null,
							'vendor_zipcode' => $bestSupplier->vendor->zipcode ?? null,

							'price' => (float) ($bestSupplier->price ?? 0),
							'sale_price' => $bestSupplier ? (float) $bestSupplier->sale_price : 0,
							'original_price' => (float) ($bestSupplier->price ?? 0),
							'front_sale_price' => (float) ($bestSupplier->sale_price ?? $bestSupplier->price ?? 0),
							'best_price' => (float) ($bestSupplier->price ?? 0),
							'map' => (float) ($bestSupplier->map ?? 0),
							'inventory' => $bestSupplier->inventory ?? null,
							'inventory_updated_by' => $bestSupplier->latestInventoryTracking->creator->name ?? $bestSupplier->creator->name,
							'inventory_updated_at' => $bestSupplier->latestInventoryTracking ? $bestSupplier->latestInventoryTracking->created_at->format('Y-m-d H:i:s') : $bestSupplier->created_at->format('Y-m-d H:i:s'),
							'price_updated_by' => $bestSupplier->latestPriceTracking->creator->name ?? $bestSupplier->creator->name,
							'price_updated_at' => $bestSupplier->latestPriceTracking ? $bestSupplier->latestPriceTracking->created_at->format('Y-m-d H:i:s') : $bestSupplier->created_at->format('Y-m-d H:i:s'),
							'in_stock' => $bestSupplier->in_stock ?? null,
							'delivery_days' => $bestSupplier->delivery_days ?? null,
							'return_policy' => $bestSupplier->return_policy ?? null,
							'free_shipping' => $bestSupplier->free_shipping ?? null,
							'warranty_information' => $bestSupplier->warranty_information ?? null,
							'quote_available' => $details->quote_available ?? null,
							'isRequired' => $details->is_required,
						] ;
					})->values(),
				];//
			}),
		]) ;//
	}//

	/**
	 * @OA\Get(
	 *     path="/api/frontend/brands-by-category/{id}",
	 *     tags={"Frontend-Brands"},
	 *     summary="Get brands by category",
	 *     description="Retrieves all published brands that have products in the specified category.",
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Category ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Brands retrieved successfully."),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(property="name", type="string", example="Apple"),
	 *                     @OA\Property(property="logo", type="string", example="https://example.com/storage/brands/apple-logo.png")
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No brands found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="No brands found for this category."),
	 *             @OA\Property(property="data", type="array", @OA\Items())
	 *         )
	 *     )
	 * )
	 */
	public function brandsByCategory($id): JsonResponse
	{
		// Get the main category and all its child categories
		$categoryIds = collect([$id])->merge($this->getAllChildCategoryIds($id));

		// Find brands that have products in these categories
		$brands = Brand::where('status', '=', 'published')
		->whereNotNull('logo')
		->where('logo', '!=', 'null')
		->whereHas('products', function ($query) use ($categoryIds) {
			$query->whereHas('categories', function ($categoryQuery) use ($categoryIds) {
				$categoryQuery->whereIn('categories.id', $categoryIds);
			});
		})
		->select('id', 'name', 'logo')
		->with('seoUrl')
		->distinct()
		->get()
		->map(function ($brand) {
			return [
				'id'   => $brand->id,
				'name' => $brand->name,
				'logo' => asset($brand->logo),
				'url'  => $brand->seoUrl->url ?? null,
			];
		})
		->values();

		if ($brands->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No published brands with logos found for this category.',
				'data'    => []
			], 404);
		}

		return response()->json([
			'success' => true,
			'message' => 'Brands retrieved successfully.',
			'data'    => $brands
		]);
	}

	/**
	 * Recursive helper to fetch all child category IDs
	 */
	private function getAllChildCategoryIds($categoryId)
	{
		$childIds = Category::where('parent_id', $categoryId)->pluck('id');
		$allChildIds = collect($childIds);

		foreach ($childIds as $childId) {
			$allChildIds = $allChildIds->merge($this->getAllChildCategoryIds($childId));
		}

		return $allChildIds;
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/brand/{id}/categories",
	 *     tags={"Frontend-Brands"},
	 *     summary="Get categories by brand",
	 *     description="Retrieves all unique categories associated with products of the specified brand.",
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Brand ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="sucess", type="string", example="true"),
	 *             @OA\Property(property="brand_id", type="integer", example=1),
	 *             @OA\Property(
	 *                 property="categories",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(property="name", type="string", example="Smartphones"),
	 *                     @OA\Property(property="image", type="string", example="https://example.com/storage/categories/smartphones.jpg")
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Brand not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Brand not found.")
	 *         )
	 *     )
	 * )
	 */
	public function getCategories($id)
	{
		// 🧠 Determine if $id is numeric (brand ID) or a slug
		if (is_numeric($id)) {
			$brand = Brand::with([
				'products' => function ($query) {
					$query->where('status', 'published')
					->whereHas('categories', fn($q) => $q->where('status', 'published'));
				},
				'products.categories.seoURL' // ✅ Load seoURL for categories
			])->findOrFail($id);
		} else {
			$seoEntry = DB::table('seo_management')
			->where('url', $id)
			->where('relational_type', 'Brand')
			->first();

			if (!$seoEntry) {
				return response()->json([
					'success' => false,
					'message' => 'Brand not found with slug',
				], 404);
			}

			$brand = Brand::with([
				'products' => function ($query) {
					$query->where('status', 'published')
					->whereHas('categories', fn($q) => $q->where('status', 'published'));
				},
				'products.categories.seoURL' // ✅ Load seoURL for categories
			])->findOrFail($seoEntry->relational_id);
		}

		$categoryCounts = [];

		foreach ($brand->products as $product) {
			foreach ($product->categories as $category) {
				// Check if this category is a published leaf
				$hasPublishedChildren = Category::where('parent_id', $category->id)
				->where('status', 'published')
				->exists();

				if ($hasPublishedChildren) {
					continue; // Skip non-leaf categories
				}

				if (!isset($categoryCounts[$category->id])) {
					$categoryCounts[$category->id] = [
						'id' => $category->id,
						'name' => $category->name,
						'image' => $category->image,
						'url' => optional($category->seoURL)->url, // ✅ Add category URL
						'product_count' => 0
					];
				}

				$categoryCounts[$category->id]['product_count']++;
			}
		}

		$categories = array_values($categoryCounts);

		return response()->json([
			'success' => true,
			'brand_id' => $brand->id,
			'categories' => $categories
		]) ;
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/products/brand/{brandId}/category/{categoryId?}",
	 *     tags={"Frontend-Brands"},
	 *     summary="Get products by brand and optional category",
	 *     description="Retrieves published products for a specific brand, optionally filtered by category with search functionality and pagination.",
	 *     @OA\Parameter(
	 *         name="brandId",
	 *         in="path",
	 *         description="Brand ID",
	 *         required=true,
	 *         @OA\Schema(type="string", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="categoryId",
	 *         in="path",
	 *         description="Category ID (optional)",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search by product name or SKU",
	 *         required=false,
	 *         @OA\Schema(type="string", example="iPhone")
	 *     ),
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination",
	 *         required=false,
	 *         @OA\Schema(type="integer", minimum=1, example=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Products retrieved successfully"),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(property="name", type="string", example="iPhone 14 Pro"),
	 *                     @OA\Property(
	 *                         property="images",
	 *                         type="array",
	 *                         @OA\Items(type="string", example="https://example.com/storage/products/iphone14pro.jpg")
	 *                     ),
	 *                     @OA\Property(property="video_url", type="string", example="https://youtube.com/watch?v=xyz"),
	 *                     @OA\Property(
	 *                         property="video_path",
	 *                         type="array",
	 *                         @OA\Items(type="string", example="https://example.com/storage/videos/iphone14pro.mp4")
	 *                     ),
	 *                     @OA\Property(property="sku", type="string", example="IPH14PRO-001"),
	 *                     @OA\Property(property="original_price", type="number", format="float", example=1099.99),
	 *                     @OA\Property(property="front_sale_price", type="number", format="float", example=1099.99),
	 *                     @OA\Property(property="sale_price", type="number", format="float", example=999.99),
	 *                     @OA\Property(property="price", type="number", format="float", example=1099.99),
	 *                     @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
	 *                     @OA\Property(property="end_date", type="string", format="date", example="2024-12-31"),
	 *                     @OA\Property(property="warranty_information", type="string", example="1 year limited warranty"),
	 *                     @OA\Property(property="currency", type="string", example="USD"),
	 *                     @OA\Property(property="total_reviews", type="integer", example=245),
	 *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.7),
	 *                     @OA\Property(property="best_price", type="number", format="float", example=999.99),
	 *                     @OA\Property(property="delivery_days", type="string", nullable=true, example=null),
	 *                     @OA\Property(property="leftStock", type="integer", example=42),
	 *                     @OA\Property(property="currency_title", type="string", example="$1099.99")
	 *                 )
	 *             ),
	 *             @OA\Property(
	 *                 property="pagination",
	 *                 @OA\Property(property="total", type="integer", example=150),
	 *                 @OA\Property(property="per_page", type="integer", example=50),
	 *                 @OA\Property(property="current_page", type="integer", example=1),
	 *                 @OA\Property(property="last_page", type="integer", example=3)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Brand not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Brand not found.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Server error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="An error occurred while fetching products"),
	 *             @OA\Property(property="error", type="string", example="Database connection failed")
	 *         )
	 *     )
	 * )
	 */
	public function getProductsByBrandAndCategory(Request $request, $brandId, $categoryId = null)
	{
		try {
			$userId = auth()->id();
			$isUserLoggedIn = $userId !== null;

			// 🧠 Get wishlist product IDs
			$wishlistProductIds = $isUserLoggedIn
			? DB::table('ec_wish_lists')
			->where('customer_id', $userId)
			->pluck('product_id')
			->map(fn($id) => (int) $id)
			->toArray()
			: session()->get('guest_wishlist', []);

			$searchTerm = strtolower($request->input('search'));

			// 🔍 Determine if $brandId is a numeric ID or a slug from seo_management
			if (is_numeric($brandId)) {
				$brand = Brand::with([
					'products' => function ($query) {
						$query->where('status', 'published')
						->whereHas('categories', function ($catQuery) {
							$catQuery->where('status', 'published');
						});
					},
					'products.categories' => function ($query) {
						$query->where('status', 'published');
					}
				])->findOrFail($brandId);
			} else {
				$seoEntry = \DB::table('seo_management')
				->where('url', $brandId)
				->where('relational_type', 'Brand')
				->first();

				if (!$seoEntry) {
					return response()->json(['success' => false, 'message' => 'Brand not found'], 404);
				}

				$brand = Brand::with([
					'products' => function ($query) {
						$query->where('status', 'published')
						->whereHas('categories', function ($catQuery) {
							$catQuery->where('status', 'published');
						});
					},
					'products.categories' => function ($query) {
						$query->where('status', 'published');
					}
				])->findOrFail($seoEntry->relational_id);
			}

			// 🔎 Filter by category
			// $filteredProducts = is_null($categoryId)
			//     ? $brand->products
			//     : $brand->products->filter(function ($product) use ($categoryId) {
			//         return $product->categories->contains('id', $categoryId);
			//     })->values();
			// 🔎 Filter by category (works with both ID or URL)
			if (!is_null($categoryId)) {
				if (!is_numeric($categoryId)) {
					// If slug, resolve to category ID
					$seoCategory = DB::table('seo_management')
					->where('url', $categoryId)
					->where('relational_type', 'Category')
					->first();

					if (!$seoCategory) {
						return response()->json([
							'success' => false,
							'message' => 'Category not found'
						], 404) ;
					}

					$categoryId = $seoCategory->relational_id;
				}

				// Now always filter by ID (whether original or resolved)
				$filteredProducts = $brand->products->filter(function ($product) use ($categoryId) {
					return $product->categories->contains('id', $categoryId);
				})->values();
			} else {
				$filteredProducts = $brand->products;
			}


			// 🔍 Filter by search term
			if (!empty($searchTerm)) {
				$filteredProducts = $filteredProducts->filter(function ($product) use ($searchTerm) {
					return stripos($product->name, $searchTerm) !== false;
				})->values();
			}

			if ($filteredProducts->isEmpty()) {
				return response()->json([
					'success' => true,
					'message' => 'No products found for this brand' . ($categoryId ? ' and category' : '') . ($searchTerm ? ' with search term' : ''),
					'data' => [],
					'pagination' => $this->emptyPagination(),
				]);
			}

			$productIds = $filteredProducts->pluck('id')->toArray();

			$productsWithRelations = Product::whereIn('id', $productIds)
			->with([
				'reviews:id,product_id,star',
				'currency',
				'bestSupplier',
				'seoUrl'
			])
			->get()
			->keyBy('id');

			$perPage = 50;
			$page = max(1, (int) $request->input('page', 1));
			$total = count($productIds);
			$offset = ($page - 1) * $perPage;
			$paginatedProducts = $filteredProducts->slice($offset, $perPage);

			$pagination = $this->buildPagination($page, $perPage, $total);

			$transformedProducts = $paginatedProducts->map(function ($product) use ($productsWithRelations, $wishlistProductIds) {
				$productWithRelations = $productsWithRelations->get($product->id) ?? $product;

				$imageUrls = is_string($product->images)
				? json_decode($product->images, true)
				: (array) $product->images;

				$videos = is_string($product->video_path)
				? json_decode($product->video_path, true) ?? []
				: ($product->video_path ?? []);

				$totalReviews = $productWithRelations->reviews ? $productWithRelations->reviews->count() : 0;
				$avgRating = $totalReviews > 0 ? $productWithRelations->reviews->avg('star') : null;

				$quantity = $product->quantity ?? 0;
				$unitsSold = $product->units_sold ?? 0;
				$leftStock = $quantity - $unitsSold;

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

				$bestSupplier = $product->bestSupplier()
				->with([
					'creator:id,first_name,last_name',
					'vendor:id,name,country_id,city_id,address,zipcode',
					'vendor.country:id,name',
					'vendor.city:id,name',
					'latestPriceTracking',
					'latestPriceTracking.creator:id,first_name,last_name',
					'latestInventoryTracking',
					'latestInventoryTracking.creator:id,first_name,last_name',
				])
				->first();

				return [
					'id' => $product->id,
					'name' => $product->name,
					'sku' => $product->sku,
					'category_url' => $product->category_url(),
					'parent_category_url' => $product->parent_category_url(),
					'url' => $product->seoUrl->url ?? null,
					'vendor_sku' => $bestSupplier->vendor_sku ?? null,

					'vendor_country' => $bestSupplier->vendor->country->name ?? null,
					'vendor_city' => $bestSupplier->vendor->city->name ?? null,
					'vendor_address' => $bestSupplier->vendor->address ?? null,
					'vendor_zipcode' => $bestSupplier->vendor->zipcode ?? null,

					'price' => $bestSupplier ? (float) $bestSupplier->price : null,
					'sale_price' => $bestSupplier ? (float) $bestSupplier->sale_price : null,
					'total_reviews' => $totalReviews,
					'avg_rating' => $avgRating,
					'left_stock' => $leftStock,
					'currency_title' => $productWithRelations->currency
					? ($productWithRelations->currency->is_prefix_symbol
						? $productWithRelations->currency->symbol
						: ($product->price . ' ' . $productWithRelations->currency->symbol))
					: $product->price,
					'in_wishlist' => in_array($product->id, $wishlistProductIds),
					'images' => $imageUrls,
					"original_price" => $bestSupplier ? (float) $bestSupplier->price : null,
					'front_sale_price' => $bestSupplier ? (float) $bestSupplier->sale_price : null,
					"best_price" => $bestSupplier ? (float) $bestSupplier->price : null,
					"selling_type" => $sellingType,
					"per_unit_price" => $product->per_unit_price,
					'vendor_id' => $bestSupplier->vendor_id ?? null,
					'map' => $bestSupplier ? (float) $bestSupplier->map : null,
					'inventory' => $bestSupplier->inventory ?? null,
					'inventory_updated_by' => $bestSupplier->latestInventoryTracking->creator->name ?? $bestSupplier->creator->name,
					'inventory_updated_at' => $bestSupplier->latestInventoryTracking ? $bestSupplier->latestInventoryTracking->created_at->format('Y-m-d H:i:s') : $bestSupplier->created_at->format('Y-m-d H:i:s'),
					'price_updated_by' => $bestSupplier->latestPriceTracking->creator->name ?? $bestSupplier->creator->name,
					'price_updated_at' => $bestSupplier->latestPriceTracking ? $bestSupplier->latestPriceTracking->created_at->format('Y-m-d H:i:s') : $bestSupplier->created_at->format('Y-m-d H:i:s'),
					'in_stock' => $bestSupplier->in_stock ?? null,
					'delivery_days' => $bestSupplier->delivery_days ?? null,
					'return_policy' => $bestSupplier->return_policy ?? null,
					'free_shipping' => $bestSupplier->free_shipping ?? null,
					'warranty_information' => $bestSupplier->warranty_information ?? null,
					'min_quantity' => $bestSupplier->min_quantity ?? 0,
					'is_fixed' => $bestSupplier->is_fixed ?? 0,
					'quote_available' => $product->quote_available ?? null,
					'isRequired' => $product->isRequired,
				];
			});

			return response()->json([//
				'success' => true,
				'data' => $transformedProducts->values(),
				'pagination' => $pagination,
				'message' => 'Products retrieved successfully',
			]) ;
		} catch (\Exception $e) {
			Log::error('Error in getProductsByBrandAndCategory: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'An error occurred while fetching products',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	protected function emptyPagination()
	{
		return [
			'total' => 0,
			'per_page' => 0,
			'current_page' => 1,
			'last_page' => 1,
		];
	}

	protected function buildPagination($page, $perPage, $total)
	{
		return [
			'total' => $total,
			'per_page' => $perPage,
			'current_page' => $page,
			'last_page' => ceil($total / $perPage),
		];
	}

	protected function normalizeMediaUrls($media)
	{
		if (is_array($media)) {
			return array_map(fn ($url) => url($url), $media);
		}
		return $media ? url($media) : null;
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/brands/alphabetical",
	 *     tags={"Frontend-Brands"},
	 *     summary="Get all brands alphabetically",
	 *     description="Retrieves all published brands either grouped alphabetically or filtered by starting letter.",
	 *     @OA\Parameter(
	 *         name="letter",
	 *         in="query",
	 *         description="Filter brands by starting letter (A-Z)",
	 *         required=false,
	 *         @OA\Schema(
	 *             type="string",
	 *             pattern="^[A-Z]$",
	 *             example="A"
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response="200",
	 *         description="Successful operation - Filtered by letter",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Brands starting with letter 'A'."),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(property="name", type="string", example="Apple"),
	 *                     @OA\Property(property="logo", type="string", example="https://example.com/storage/brands/apple-logo.png")
	 *                 )
	 *             )
	 *         )
	 *     )
	 * )
	 */
	public function getAllBrandsAlphabetically(Request $request): JsonResponse
	{
		$letter = strtoupper($request->query('letter')); // e.g. ?letter=B

		$brandsQuery = Brand::where('status', 'published')
		->whereNotNull('thumbnail')
		->select('id', 'name', 'logo', 'thumbnail', 'ar_thumbnail')
		->orderBy('name');

		if ($letter) {
			$brandsQuery->where('name', 'LIKE', $letter . '%');
		}

		$brands = $brandsQuery->get()->map(function ($brand) {
			$brand->logo = $brand->logo ? asset($brand->logo) : null;
			$brand->thumbnail = $brand->thumbnail ? asset($brand->thumbnail) : null;
			$brand->ar_thumbnail = $brand->ar_thumbnail ? asset($brand->ar_thumbnail) : null;

			// 👇 Add the slug from seo_management
			$seoEntry = DB::table('seo_management')
			->where('relational_id', $brand->id)
			->where('relational_type', 'Brand')
			->first();

			$brand->slug = $seoEntry?->url ?? null;

			return $brand;
		});

		if ($letter) {
			return response()->json([
				'success' => true,
				'message' => "Brands starting with letter '$letter'.",
				'data' => $brands
			]);
		} else {
			$grouped = $brands->groupBy(function ($brand) {
				return strtoupper(substr($brand->name, 0, 1));
			})->sortKeys();

			return response()->json([
				'success' => true,
				'message' => 'Brands grouped alphabetically.',
				'data' => $grouped
			]) ;
		}
	}
}
