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
	 *     @OA\Parameter(name="start_letter", in="query", description="Filter brands by starting letter (a-z)", @OA\Schema(type="string", example="A")),
	 *     @OA\Parameter(name="with_logo_only", in="query", description="Show only brands with logos", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name"})),
	 *     @OA\Response(response=200, description="Brands retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function index(Request $request)
	{
		$categoryId = $request->get('category_id');
		$startLetter = strtoupper($request->query('start_letter'));
		$sortBy = $request->get('sort_by');
		$withLogoOnly = $request->boolean('with_logo_only', true);

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
		$brandsQuery = Brand::select(['id', 'name', 'logo', 'thumbnail'])
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url'
		])
		->where('status', 'published');

		if ($startLetter) {
			$brandsQuery->where('name', 'LIKE', $startLetter . '%');
		}

		/* Filter by logo if required */
		if ($withLogoOnly) {
			$brandsQuery->whereNotNull('logo')
			->where('logo', '!=', '')
			->where('logo', '!=', 'null')
			->whereNotNull('thumbnail')
			->where('thumbnail', '!=', '')
			->where('thumbnail', '!=', 'null');
		}

		/* Filter by category if provided */
		if (!empty($leafCategoryIds)) {
			$brandsQuery->whereHas('products', function ($query) use ($leafCategoryIds) {
				$query->whereHas('categories', function ($q) use ($leafCategoryIds) {
					$q->whereIn('id', $leafCategoryIds);
				});
			});
		}

		if ($sortBy) {
			$brandsQuery = $brandsQuery->orderBy($sortBy);
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
		$records = $this->fetchFeaturedBrandProducts($request);

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
		$wishlistProductIds = auth()->user()->wishlist()->pluck('product_id')->toArray();
		$records = $this->fetchFeaturedBrandProducts($request, $wishlistProductIds);

		return response()->json([
			'success' => true,
			'message' => 'Featured brands retrieved successfully',
			'data' => $records
		]);
	}

	private function fetchFeaturedBrandProducts(Request $request, ?array $wishlistProductIds = null)
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
			'translations:id,locale,brand_id,name_tr',
			'seoUrl:id,relational_id,relational_type,url',
			'featuredProducts' => function($query) use ($productsLimit, $search, $minRating, $priceMin, $priceMax) {
				$query->select(['id', 'name', 'sku', 'currency_id', 'units_sold', 'alt_tags', 'quote_available', 'brand_id'])
				->search($search)
				->minRating($minRating)
				->priceRange($priceMin, $priceMax)
				->with([
					'translations',
					'seoUrl:id,relational_id,relational_type,url',
					'productSuppliers' => function($q) use ($priceMin, $priceMax) {
						$q->select(['id', 'product_id', 'vendor_id', 'vendor_sku', 'cost_per_item', 'sale_price', 'price', 'inventory', 'in_stock', 'min_quantity', 'is_fixed', 'delivery_days', 'return_policy', 'free_shipping', 'shipping_charge', 'warranty_information'])
						->priceRange($priceMin, $priceMax)
						->cheapest();
					},
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

				/* Add wishlist status if wishlist IDs provided */
				if ($wishlistProductIds !== null) {
					$product->in_wishlist = in_array($product->id, $wishlistProductIds);
				}
			});

			/* Remove unwanted attributes from brand */
			unset($brand->translations, $brand->seoUrl);

			return $brand;
		});

		return $records;
	}
}
