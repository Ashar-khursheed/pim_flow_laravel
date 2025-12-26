<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use App\Models\Product;
use App\Models\SeoManagement;

use App\Traits\TransformProduct;

class FProductController extends BaseController
{
	use TransformProduct;

	/**
	 * @OA\Get(
	 *     path="/api/frontend-products",
	 *     tags={"Frontend FProduct"},
	 *     summary="Get products with filters",
	 *     description="Retrieves paginated products with comprehensive filtering options",
	 *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
	 *     @OA\Parameter(name="length", in="query", description="Items per page (1-100)", @OA\Schema(type="integer", default=20)),
	 *     @OA\Parameter(name="search", in="query", description="Search by name/SKU", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="category_id", in="query", description="Category ID", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="category_url", in="query", description="Category slug", @OA\Schema(type="string", example="ice-bins")),
	 *     @OA\Parameter(name="brand_id", in="query", description="Brand ID", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="brand_url", in="query", description="Brand slug", @OA\Schema(type="string", example="hoshizaki")),
	 *     @OA\Parameter(name="product_id", in="query", description="Product ID", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="product_url", in="query", description="Product slug", @OA\Schema(type="string", example="hoshizaki-ice-storage-bin-140l")),
	 *     @OA\Parameter(name="with_description", in="query", description="Whether to include the product description", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Parameter(name="with_attributes", in="query", description="Whether to include the additional attributes", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Parameter(name="min_rating", in="query", description="Min rating (0-5)", @OA\Schema(type="number")),
	 *     @OA\Parameter(name="price_min", in="query", description="Min price", @OA\Schema(type="number")),
	 *     @OA\Parameter(name="price_max", in="query", description="Max price", @OA\Schema(type="number")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Sort field", @OA\Schema(type="string", enum={"id", "name", "code", "type", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_order", in="query", description="Sort order (asc or desc)", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Products retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function index(Request $request)
	{
		$page = $request->get('page', 1);
		$length = $request->get('length', 20);
		$search = $request->get('search');
		$categoryID = $request->get('category_id');
		$categoryUrl = $request->get('category_url');
		$brandID = $request->get('brand_id');
		$brandUrl = $request->get('brand_url');
		$productID = $request->get('product_id');
		$productURL = $request->get('product_url');
		$withDescription = $request->boolean('with_description');
		$withAttributes = $request->boolean('with_attributes');

		$minRating = $request->get('min_rating');
		$priceMin = $request->get('price_min');
		$priceMax = $request->get('price_max');
		$sortBy = $request->get('sort_by', 'created_at');
		$sortOrder = $request->get('sort_order', 'desc');

		$recordsQuery = Product::select(['id', 'name', 'sku', 'brand_id', 'currency_id', 'alt_tags', 'quote_available', 'brand_id'])
		->where('status', 'published')
		->when($categoryID, function($q) use ($categoryID) {
			$q->whereHas('categories', function($query) use ($categoryID) {
				$query->where('categories.id', $categoryID);
			});
		})
		->when($categoryUrl, function($q) use ($categoryUrl) {
			$q->whereHas('categories.seoUrl', function($query) use ($categoryUrl) {
				$query->where('url', $categoryUrl);
			});
		})
		->when($brandID, function($q) use ($brandID) {
			$q->where('brand_id', $brandID);
		})
		->when($brandUrl, function($q) use ($brandUrl) {
			$q->whereHas('brand.seoUrl', function($query) use ($brandUrl) {
				$query->where('url', $brandUrl);
			});
		})
		->when($productID, function($q) use ($productID) {
			$q->where('id', $productID);
		})
		->when($productURL, function($q) use ($productURL) {
			$q->whereHas('seoProductUrl', function($query) use ($productURL) {
				$query->where('url', $productURL);
			});
		})
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
			'sellingUnitAttribute',
		])
		->when($withAttributes, function($query) {
			$query->with([
				'productAttributes:id,product_id,attribute_id,attribute_value,measurement_unit_id',
				'productAttributes.attributeDetails:id,name',
				'productAttributes.attributeDetails.translations',
				'productAttributes.measurementUnit:id,name',
				'productAttributes.measurementUnit.translations',
				'productAttributes.translations',
			]);
		})
		->withCount('reviews')
		->withAvg('reviews', 'star')
		->orderBy($sortBy, $sortOrder);

		/* Check if fetching single product */
		if ($productID || $productURL) {
			/* Fetch single product */
			$record = $recordsQuery->first();

			if (!$record) {
				return response()->json([
					'success' => false,
					'message' => 'Product not found'
				]);
			}

			/* Transform product */
			$this->transformFeaturedProduct($record, withDescription: $withDescription, withAttributes: $withAttributes);

			return response()->json([
				'success' => true,
				'message' => 'Product retrieved successfully',
				'data' => $record
			]);
		} else {
			/* Clone query for counting */
			$totalRecords = (clone $recordsQuery)->count();
			$totalPages = (int) ceil($totalRecords / $length);

			/* If requested page exceeds total pages (after search), fallback to page 1 */
			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get();

			/* Transform products */
			$records->each(function ($product) use ($withDescription) {
				$this->transformFeaturedProduct($product, withDescription: $withDescription);
			});

			return response()->json([
				'success' => true,
				'message' => 'Products retrieved successfully',
				'data' => $records,
				'total_pages' => $totalPages ?? 1,
				'total_records' => $totalRecords,
			]);
		}
	}
}
