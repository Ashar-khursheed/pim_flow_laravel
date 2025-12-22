<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use App\Models\Brand;
use App\Models\Category;

use App\Traits\TransformProduct;

class FProductController extends Controller
{
	use TransformProduct;

	/**
	 * @OA\Get(
	 *     path="/api/frontend-products",
	 *     tags={"Frontend Product"},
	 *     summary="Get products with filters",
	 *     description="Retrieves paginated products with comprehensive filtering options",
	 *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
	 *     @OA\Parameter(name="length", in="query", description="Items per page (1-100)", @OA\Schema(type="integer", default=20)),
	 *     @OA\Parameter(name="search", in="query", description="Search by name/SKU", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="category_url", in="query", description="Category slug", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="brand_url", in="query", description="Brand slug", @OA\Schema(type="string")),
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

		$minRating = $request->get('min_rating');
		$priceMin = $request->get('price_min');
		$priceMax = $request->get('price_max');
		$sortBy = $request->get('sort_by', 'created_at');
		$sortOrder = $request->get('sort_order', 'desc');

		$products = Product::select(['id', 'name', 'sku', 'brand_id', 'currency_id', 'alt_tags', 'quote_available'])
		->where('status', 'published')
		->search($search)
		->minRating($minRating)
		->priceRange($priceMin, $priceMax)
		->qwhen($isFeatured, fn($q) => $q->where('is_featured', 1))
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url',
			'productSuppliers:id,product_id,vendor_id,sale_price,price',
			'reviews:id,product_id,star',
			'currency:id,title,symbol',
			'brand:id,name'
		])
		->withCount('reviews')
		->withAvg('reviews', 'star')
		->orderBy($sortBy, $sortOrder)
		->paginate($length, ['*'], 'page', $page);

		return response()->json([
			'success' => true,
			'message' => 'Products retrieved successfully',
			'data' => $products->items(),
			'pagination' => [
				'total' => $products->total(),
				'per_page' => $products->perPage(),
				'current_page' => $products->currentPage(),
				'last_page' => $products->lastPage(),
				'from' => $products->firstItem(),
				'to' => $products->lastItem()
			]
		]);
	}
}
