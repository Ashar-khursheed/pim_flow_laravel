<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Models\FrontEnd\HorecaPage;
use App\Models\SeoManagement;

use App\Traits\TransformProduct;

class HorecaPageController extends BaseController
{
	use TransformProduct;

	/**
	 * @OA\Get(
	 *     path="/api/frontend/horeca-pages/{id}",
	 *     summary="Get Horeca page details",
	 *     tags={"FrontEnd-Horeca Pages"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Horeca Page ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Horeca page details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function show($id)
	{
		try {
			/* Find the horeca page with relationships */
			$page = HorecaPage::with([
				'categories:id,name,image',
				'categories.seoUrl:id,relational_id,relational_type,url',
				'productTypes:id,horeca_page_id,type,description,order',
				'productTypes.products' => function($query) {
					/* Build product relationships array */
					$productRelationships = [
						'seoUrl:id,relational_id,relational_type,url',
						'productSuppliers' => function($q) {
							$q->select(['id', 'product_id', 'vendor_id', 'vendor_sku', 'cost_per_item', 'sale_price', 'price', 'inventory', 'in_stock', 'min_quantity', 'is_fixed', 'delivery_days', 'return_policy', 'free_shipping', 'shipping_charge', 'warranty_information', 'map'])
							->cheapest();
						},
						'productSuppliers.vendor:id,name,address,zipcode,city_id,country_id',
						'productSuppliers.vendor.country:id,name',
						'productSuppliers.vendor.city:id,name',
						'reviews:id,product_id,star',
						'currency:id,title,symbol,is_prefix_symbol',
						'sellingUnitAttribute',
						'ingredientsAttribute',
						'brand.seoUrl:id,relational_id,relational_type,url',
						'categories' => function($q) {
							$q->select(['categories.id', 'categories.name', 'categories.image', 'categories.parent_id'])
							->with(['seoUrl:id,relational_id,relational_type,url', 'parent:id,name,parent_id', 'parent.seoUrl:id,relational_id,relational_type,url', 'parent.parent:id,name,parent_id', 'parent.parent.seoUrl:id,relational_id,relational_type,url']);
						}
					];

					/* Add translations for UAE websites only */
					if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
						$productRelationships[] = 'translations';
					}

					$query->select([
						'ec_products.id',
						'ec_products.name',
						'ec_products.sku',
						'ec_products.images',
						'ec_products.currency_id',
						'ec_products.alt_tags',
						'ec_products.quote_available',
						'ec_products.brand_id',
						'ec_products.video_url',
						'ec_products.video_path',
						'ec_products.start_date',
						'ec_products.end_date',
						'ec_products.quantity',
						'ec_products.units_sold'
					])
					->where('ec_products.status', 'published')
					->with($productRelationships)
					->withCount('reviews')
					->withAvg('reviews', 'star');
				}
			])->find($id);

			/* Check if page exists */
			if (!$page) {
				return response()->json([
					'success' => false,
					'message' => 'Horeca page not found'
				], 404);
			}

			/* Transform categories data */
			if ($page->categories) {
				$page->categories->transform(function ($category) {
					$category->order = optional($category->pivot)->order ?? null;
					$category->slug = optional($category->seoUrl)->url ?? null;
					unset($category->pivot);
					unset($category->seoUrl);
					return $category;
				});
			}

			/* Get wishlist IDs for the current user */
			$wishlistProductIds = $this->getWishlistProductIds();

			/* Transform product types and their products */
			if ($page->productTypes) {
				$page->productTypes->transform(function ($productType) use ($wishlistProductIds) {
					if ($productType->products) {
						$productType->products = $productType->products->map(function ($product) use ($wishlistProductIds) {
							return $this->transformEnhancedProduct($product, $wishlistProductIds);
						});
					}
					return $productType;
				});
			}

			return response()->json([
				'success' => true,
				'message' => 'Horeca page retrieved successfully',
				'data' => $page
			], 200);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to retrieve horeca page',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/horeca-pages-by-slug/{slug}",
	 *     summary="Get Horeca page details by slug",
	 *     tags={"FrontEnd-Horeca Pages"},
	 *     @OA\Parameter(
	 *         name="slug",
	 *         in="path",
	 *         description="Horeca Page SEO Slug",
	 *         required=true,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Response(response=200, description="Horeca page details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function showBySlug($slug)
	{
		try {
			// Find seo record matching slug and relational_type 'Page' (as requested)
			$seoRecord = SeoManagement::where('url', $slug)
				->where('relational_type', 'Page')
				->first();

			if (!$seoRecord) {
				return response()->json([
					'success' => false,
					'message' => 'Horeca page slug not found'
				], 404);
			}

			/* Find the horeca page with relationships */
			$page = HorecaPage::with([
				'categories:id,name,image',
				'categories.seoUrl:id,relational_id,relational_type,url',
				'productTypes:id,horeca_page_id,type,description,order',
				'productTypes.products' => function($query) {
					/* Build product relationships array */
					$productRelationships = [
						'seoUrl:id,relational_id,relational_type,url',
						'productSuppliers' => function($q) {
							$q->select(['id', 'product_id', 'vendor_id', 'vendor_sku', 'cost_per_item', 'sale_price', 'price', 'inventory', 'in_stock', 'min_quantity', 'is_fixed', 'delivery_days', 'return_policy', 'free_shipping', 'shipping_charge', 'warranty_information', 'map'])
							->cheapest();
						},
						'productSuppliers.vendor:id,name,address,zipcode,city_id,country_id',
						'productSuppliers.vendor.country:id,name',
						'productSuppliers.vendor.city:id,name',
						'reviews:id,product_id,star',
						'currency:id,title,symbol,is_prefix_symbol',
						'sellingUnitAttribute',
						'ingredientsAttribute',
						'brand.seoUrl:id,relational_id,relational_type,url',
						'categories' => function($q) {
							$q->select(['categories.id', 'categories.name', 'categories.image', 'categories.parent_id'])
							->with(['seoUrl:id,relational_id,relational_type,url', 'parent:id,name,parent_id', 'parent.seoUrl:id,relational_id,relational_type,url', 'parent.parent:id,name,parent_id', 'parent.parent.seoUrl:id,relational_id,relational_type,url']);
						}
					];

					/* Add translations for UAE websites only */
					if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
						$productRelationships[] = 'translations';
					}

					$query->select([
						'ec_products.id',
						'ec_products.name',
						'ec_products.sku',
						'ec_products.images',
						'ec_products.currency_id',
						'ec_products.alt_tags',
						'ec_products.quote_available',
						'ec_products.brand_id',
						'ec_products.start_date',
						'ec_products.end_date',
						'ec_products.quantity',
						'ec_products.units_sold'
					])
					->where('ec_products.status', 'published')
					->with($productRelationships)
					->withCount('reviews')
					->withAvg('reviews', 'star');
				}
			])->find($seoRecord->relational_id);

			/* Check if page exists */
			if (!$page) {
				return response()->json([
					'success' => false,
					'message' => 'Horeca page not found'
				], 404);
			}

			/* Transform categories data */
			if ($page->categories) {
				$page->categories->transform(function ($category) {
					$category->order = optional($category->pivot)->order ?? null;
					$category->slug = optional($category->seoUrl)->url ?? null;
					unset($category->pivot);
					unset($category->seoUrl);
					return $category;
				});
			}

			/* Get wishlist IDs for the current user */
			$wishlistProductIds = $this->getWishlistProductIds();

			/* Transform product types and their products */
			if ($page->productTypes) {
				$page->productTypes->transform(function ($productType) use ($wishlistProductIds) {
					if ($productType->products) {
						$productType->products = $productType->products->map(function ($product) use ($wishlistProductIds) {
							return $this->transformEnhancedProduct($product, $wishlistProductIds);
						});
					}
					return $productType;
				});
			}

			return response()->json([
				'success' => true,
				'message' => 'Horeca page retrieved successfully',
				'data' => $page
			], 200);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to retrieve horeca page',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Get wishlist product IDs for the current user or guest session
	 *
	 * @return array
	 */
	private function getWishlistProductIds()
	{
		$userId = Auth::id();
		if ($userId) {
			return DB::table('ec_wish_lists')
				->where('customer_id', $userId)
				->pluck('product_id')
				->map(fn($id) => (int) $id)
				->toArray();
		}
		return session()->get('guest_wishlist', []);
	}

	/**
	 * Transform a product into the enhanced format requested by the user
	 *
	 * @param object $product
	 * @param array $wishlistProductIds
	 * @return array
	 */
	private function transformEnhancedProduct($product, $wishlistProductIds)
	{
		// Clean images
		$imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
		$cleanedImages = collect($imageArray)->map(function ($item) {
			if (is_string($item) && str_starts_with($item, '[')) {
				$decoded = json_decode($item, true);
				return is_array($decoded) ? $decoded : [$item];
			}
			return [$item];
		})->flatten()->filter()->values();

		// Clean alt tags
		$AltArray = is_array($product->alt_tags) ? $product->alt_tags : json_decode($product->alt_tags, true);
		$cleanedAlt = collect($AltArray)->map(function ($item) {
			if (is_string($item) && str_starts_with($item, '[')) {
				$decoded = json_decode($item, true);
				return is_array($decoded) ? $decoded : [$item];
			}
			return [$item];
		})->flatten()->filter()->values();

		$videoPaths = json_decode($product->video_path, true);
		$video_path = collect($videoPaths)->map(fn($video) => $video);

		$totalReviews = $product->reviews_count ?? 0;
		$avgRating = $product->reviews_avg_star ?? null;
		$quantity = $product->quantity ?? 0;
		$unitsSold = $product->units_sold ?? 0;
		$leftStock = $quantity - $unitsSold;

		if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
			$fullValue = $product->sellingUnitAttribute->attribute_value;
			if (strpos($fullValue, '/') !== false) {
				$parts = explode('/', $fullValue);
				$product->sellingUnitAttribute->attribute_value_unit = trim($parts[1]);
			} else {
				$product->sellingUnitAttribute->attribute_value_unit = $fullValue;
			}
		}

		$firstSupplier = $product->productSuppliers->first();

		// Category hierarchy
		$category = $product->categories->first();
		$parentCategory = $category?->parent;
		$grandparentCategory = $parentCategory?->parent;

		return [
			'id' => $product->id,
			'name' => $product->name,
			'url' => $product->seoUrl->url ?? null,
			'images' => $cleanedImages,
			'alt_tags' => $cleanedAlt,
			'sku' => $product->sku,
			'start_date' => $product->start_date,
			'end_date' => $product->end_date,
			'original_price' => (float) ($firstSupplier->price ?? 0),
			'product_id' => $product->id,
			'product_name' => $product->name,
			'clicks' => 0, // Placeholder
			'sales' => 0.0, // Placeholder
			'views' => 0, // Placeholder
			'sale_price' => (float) ($firstSupplier->sale_price ?? 0),
			'front_sale_price' => (float) ($firstSupplier->sale_price ?? $firstSupplier->price ?? 0),
			'map' => (float) ($firstSupplier->map ?? null),
			'free_shipping' => $firstSupplier->free_shipping ?? null,
			'shipping_charge' => (float) ($firstSupplier->shipping_charge ?? 0.0),
			'inventory' => $firstSupplier->inventory ?? null,
			'in_stock' => $firstSupplier->in_stock ?? null,
			'in_wishlist' => in_array($product->id, $wishlistProductIds),
			'isRequired' => (bool) $product->getIsRequiredAttribute(),
			'vendor_id' => $firstSupplier->vendor_id ?? null,
			'vendor_sku' => $firstSupplier->vendor_sku ?? null,
			'vendor_country' => $firstSupplier->vendor->country->name ?? null,
			'vendor_city' => $firstSupplier->vendor->city->name ?? null,
			'vendor_port' => null, // Placeholder
			'vendor_zipcode' => $firstSupplier->vendor->zipcode ?? null,
			'vendor_address' => $firstSupplier->vendor->address ?? null,
			'selling_unit' => $product->sellingUnitAttribute->attribute_value ?? null,
			'delivery_days' => $firstSupplier->delivery_days ?? null,
			'warranty_information' => $firstSupplier->warranty_information ?? null,
			'return_policy' => $firstSupplier->return_policy ?? null,
			'category_name' => $category->name ?? null,
			'category_id' => $category->id ?? null,
			'category_image' => $category ? (filter_var($category->image, FILTER_VALIDATE_URL) ? $category->image : url('storage/' . ltrim($category->image, '/'))) : null,
			'category_url' => $category->seoUrl->url ?? null,
			'category_parent_id' => $category->parent_id ?? null,
			'parent_category_name' => $parentCategory->name ?? null,
			'parent_category_slug' => $parentCategory->seoUrl->url ?? null,
			'parent_category_url' => $parentCategory->seoUrl->url ?? null,
			'grandparent_category_name' => $grandparentCategory->name ?? null,
			'grandparent_category_slug' => $grandparentCategory->seoUrl->url ?? null,
			'brand' => $product->brand->name ?? null,
			'brand_id' => $product->brand_id ?? null,
			'brand_logo' => $product->brand ? (filter_var($product->brand->logo, FILTER_VALIDATE_URL) ? $product->brand->logo : url('storage/' . ltrim($product->brand->logo, '/'))) : null,
			'brand_seo_url' => $product->brand->seoUrl->url ?? null,
			'product_images' => $cleanedImages->map(fn($img) => ['url' => $img]),
			'primary_image' => $cleanedImages->first(),
			'quote_available' => $product->quote_available,
			'is_fixed' => $firstSupplier->is_fixed ?? 0,
			'min_quantity' => $firstSupplier->min_quantity ?? 1,
			'currency' => $product->currency->symbol ?? null,
			'currency_title' => $product->currency
				? ($product->currency->is_prefix_symbol
					? $product->currency->symbol
					: ($product->price . ' ' . $product->currency->symbol))
				: null,
			'total_reviews' => $totalReviews,
			'avg_rating' => $avgRating,
			'leftStock' => $leftStock,
			'best_price' => (float) ($firstSupplier->price ?? 0),
		];
	}
}