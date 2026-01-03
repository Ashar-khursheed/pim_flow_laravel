<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

class ProductYouMayLikeController extends Controller
{
	/**
	 * Get products you may like based on a given product.
	 *
	 * @param Request $request
	 * @param int|null $product_id
	 * @return \Illuminate\Http\JsonResponse
	 */


	/**
	 * @OA\Get(
	 *     path="/api/frontend/products/{product_id}/you-may-like",
	 *     operationId="getProductsYouMayLike",
	 *     tags={"Frontend-Product You May Like"},
	 *      security={{"bearerAuth":{}}},
	 *     summary="Get products you may like based on a given product ID",
	 *     description="Returns a list of products that are recommended based on a specific product. Accepts optional pagination.",
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="path",
	 *         description="ID of the product to get recommendations for",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=123)
	 *     ),
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination",
	 *         required=false,
	 *         @OA\Schema(type="integer", default=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Products you may like retrieved successfully"),
	 *             @OA\Property(property="data", type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=101),
	 *                     @OA\Property(property="name", type="string", example="Recommended Product"),
	 *                     @OA\Property(property="images", type="array", @OA\Items(type="string", format="url")),
	 *                     @OA\Property(property="video_url", type="string", format="url", nullable=true),
	 *                     @OA\Property(property="video_path", type="array", @OA\Items(type="string", format="url")),
	 *                     @OA\Property(property="sku", type="string", example="SKU-1234"),
	 *                     @OA\Property(property="original_price", type="number", format="float", example=100.0),
	 *                     @OA\Property(property="sale_price", type="number", format="float", example=80.0),
	 *                     @OA\Property(property="currency", type="string", example="USD"),
	 *                     @OA\Property(property="total_reviews", type="integer", example=25),
	 *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
	 *                     @OA\Property(property="leftStock", type="integer", example=10),
	 *                     @OA\Property(property="in_wishlist", type="boolean", example=true),
	 *                 )
	 *             ),
	 *             @OA\Property(property="pagination", type="object",
	 *                 @OA\Property(property="current_page", type="integer", example=1),
	 *                 @OA\Property(property="last_page", type="integer", example=2),
	 *                 @OA\Property(property="per_page", type="integer", example=50),
	 *                 @OA\Property(property="total", type="integer", example=100),
	 *                 @OA\Property(property="has_more_pages", type="boolean", example=true),
	 *                 @OA\Property(property="visible_pages", type="array", @OA\Items(type="integer")),
	 *                 @OA\Property(property="has_previous", type="boolean", example=false),
	 *                 @OA\Property(property="has_next", type="boolean", example=true),
	 *                 @OA\Property(property="previous_page", type="integer", example=0),
	 *                 @OA\Property(property="next_page", type="integer", example=2),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="Bad request - missing product_id",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Product ID is required")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Internal server error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="An error occurred while fetching products you may like"),
	 *             @OA\Property(property="error", type="string", example="Exception message here")
	 *         )
	 *     )
	 * )
	 */
	public function getProductsYouMayLike(Request $request, $product_id = null)
	{
		try {
			// Get product ID from route param or request input
			$input = $product_id ?? $request->input('product_id');

			if (!$input) {
				return response()->json([
					'success' => false,
					'message' => 'Product ID or slug is required',
				], 400);
			}

				// Determine if input is numeric (ID) or string (slug)
			if (is_numeric($input)) {
				$productId = (int) $input;
			} else {
					// Fetch by slug via seoUrl relationship
				$product = Product::whereHas('seoUrl', function ($q) use ($input) {
					$q->where('url', $input);
				})->first();

				if (!$product) {
					return response()->json([
						'success' => false,
						'message' => 'Product not found by slug',
					], 404);
				}

				$productId = $product->id;
			}

			$userId = Auth::id();
			$isUserLoggedIn = $userId !== null;

			Log::info('Fetching recommendations for product:', ['product_id' => $productId, 'user_id' => $userId]);

			// Get wishlist product IDs (for logged in user or guest)
			$wishlistProductIds = $isUserLoggedIn
			? DB::table('ec_wish_lists')->where('customer_id', $userId)->pluck('product_id')->map(fn($id) => (int) $id)->toArray()
			: session()->get('guest_wishlist', []);

			// Step 1: Find the main "product_you_may_likes" record for this product
			$productYouMayLike = DB::table('product_you_may_likes')
			->where('product_id', $productId)
			->first();

			Log::info('ProductYouMayLike lookup:', [
				'product_id' => $productId,
				'found' => $productYouMayLike ? 'yes' : 'no',
				'record_id' => $productYouMayLike->id ?? null,
			]);

			if (!$productYouMayLike) {
				return response()->json([
					'success' => true,
					'message' => 'No related products found for this product',
					'data' => [],
					'pagination' => $this->emptyPagination(),
				]);
			}

			// Step 2: Fetch all recommended products linked to this "product_you_may_like" record by product_you_may_like_id
		   // Step 2 (Updated): Get only product IDs from product_you_may_like_items where the product exists in ec_products
			$relatedProductIds = DB::table('product_you_may_like_items')
			->where('product_you_may_like_id', $productYouMayLike->id)
			->pluck('product_id')
			->toArray();


			Log::info('ProductYouMayLikeItems:', [
				'product_you_may_like_id' => $productYouMayLike->id,
				'count' => count($relatedProductIds),
				'product_ids' => $relatedProductIds,
			]);

			if (empty($relatedProductIds)) {
				return response()->json([
					'success' => true,
					'message' => 'No recommended products configured',
					'data' => [],
					'pagination' => $this->emptyPagination(),
				]);
			}

			// Step 3: Get published products matching recommended IDs
			$productsQuery = Product::with(['categories', 'brand'])
			->where('status', 'published')
			->whereIn('id', $relatedProductIds);

			$products = $productsQuery->get();

			Log::info('Products query result:', [
				'expected_ids' => $relatedProductIds,
				'found_count' => $products->count(),
				'found_ids' => $products->pluck('id')->toArray(),
			]);

			if ($products->isEmpty()) {
				return response()->json([
					'success' => true,
					'message' => 'No published products found in recommendations',
					'data' => [],
					'pagination' => $this->emptyPagination(),
				]);
			}

			// Sort products to preserve priority order
			$products = $products->sortBy(fn($product) => array_search($product->id, $relatedProductIds));

			// Pagination logic
			$perPage = 50;
			$page = max(1, (int) $request->input('page', 1));
			$total = $products->count();
			$offset = ($page - 1) * $perPage;
			$paginatedProducts = $products->slice($offset, $perPage);

			// Load additional relationships for paginated products
			$productIds = $paginatedProducts->pluck('id')->toArray();
			$productsWithRelations = Product::whereIn('id', $productIds)
			->with(['reviews:id,product_id,star', 'currency' ,'productSuppliers', 'seoUrl'])
			->get()
			->keyBy('id');

			// Prepare pagination metadata
			$pagination = $this->buildPagination($page, $perPage, $total);

			// Transform products for response
			$transformedProducts = $paginatedProducts->map(function ($product) use ($wishlistProductIds, $productsWithRelations) {
				$productWithRelations = $productsWithRelations->get($product->id) ?? $product;

				// Decode benefit features safely
				// $benefitFeatures = [];
				// if (!empty($product->benefit_features)) {
				//     $decoded = json_decode($product->benefit_features, true);
				//     if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				//         $benefitFeatures = array_map(fn($b) => [
				//             'benefit' => $b['benefit'] ?? null,
				//             'feature' => $b['feature'] ?? null,
				//         ], $decoded);
				//     }
				// }

				// Normalize images and videos URLs
				$images = $this->normalizeMediaUrls($product->images);
				$videos = $this->normalizeMediaUrls($product->video_path);

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
				$firstSupplier = $product->productSuppliers->first();

				return [
					'id' => $product->id,
					'name' => $product->name,
					'category_url' => $product->category_url(),
					'parent_category_url' => $product->parent_category_url(),
					'url' => $product->seoUrl->url ?? null,
					'images' => $images,
					'video_url' => $product->video_url,
					'video_path' => $videos,
					'sku' => $product->sku,
					'start_date' => $product->start_date,
					'end_date' => $product->end_date,
					'currency' => $productWithRelations->currency?->symbol,
					'total_reviews' => $totalReviews,
					'avg_rating' => $avgRating,
					'leftStock' => $leftStock,
					'currency_title' => $productWithRelations->currency
					? ($productWithRelations->currency->is_prefix_symbol
						? $productWithRelations->currency->symbol
						: ($product->price . ' ' . $productWithRelations->currency->symbol))
					: $product->price,
					'in_wishlist' => in_array($product->id, $wishlistProductIds),
					'selling_type' => $sellingType,
					'vendor_sku' => $firstSupplier->vendor_sku ?? null,
					'price' => $firstSupplier ? (float) $firstSupplier->price : null,
					'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
					'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
					'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
					'best_price' => $firstSupplier ? (float) $firstSupplier->price : null,
					'per_unit_price' => $product->per_unit_price,
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
					'quote_available' => $product->quote_available ?? null,
					'isRequired' => $product->isRequired,
				];

			});

			Log::info('Returning products:', [
				'total' => $total,
				'page' => $page,
				'count' => $transformedProducts->count(),
			]);

			return response()->json([
				'success' => true,
				'data' => $transformedProducts->values(),
				'pagination' => $pagination,
				'message' => 'Products you may like retrieved successfully',
			]);
		} catch (\Exception $e) {
			Log::error('Error in getProductsYouMayLike: ' . $e->getMessage());
			Log::error('Stack trace: ' . $e->getTraceAsString());

			return response()->json([
				'success' => false,
				'message' => 'An error occurred while fetching products you may like',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * Return empty pagination structure.
	 */
	protected function emptyPagination(): array
	{
		return [
			'current_page' => 1,
			'last_page' => 1,
			'per_page' => 50,
			'total' => 0,
			'has_more_pages' => false,
			'visible_pages' => [1],
			'has_previous' => false,
			'has_next' => false,
			'previous_page' => 0,
			'next_page' => 2,
		];
	}

	/**
	 * Build pagination metadata array.
	 */
	protected function buildPagination(int $currentPage, int $perPage, int $total): array
	{
		$lastPage = (int) ceil($total / $perPage);
		$startPage = max($currentPage - 2, 1);
		$endPage = min($startPage + 4, $lastPage);

		if ($endPage - $startPage < 4) {
			$startPage = max($endPage - 4, 1);
		}

		return [
			'current_page' => $currentPage,
			'last_page' => $lastPage,
			'per_page' => $perPage,
			'total' => $total,
			'has_more_pages' => $currentPage < $lastPage,
			'visible_pages' => range($startPage, $endPage),
			'has_previous' => $currentPage > 1,
			'has_next' => $currentPage < $lastPage,
			'previous_page' => $currentPage - 1,
			'next_page' => $currentPage + 1,
		];
	}

	/**
	 * Normalize image/video URLs (accepts JSON string or array).
	 */
	protected function normalizeMediaUrls($media)
	{
		if (empty($media)) {
			return [];
		}

		if (is_string($media)) {
			$decoded = json_decode($media, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				return is_array($decoded) ? $decoded : [];
			}
			return [];
		}

		if (is_array($media)) {
			return $media;
		}

		return [];
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/products/{product_id}/you-may-like-guest",
	 *     operationId="getProductsYouMayLikeGuest",
	 *     tags={"Frontend-Product You May Like"},
	 *     summary="Get products you may like based on a given product ID",
	 *     description="Returns a list of products that are recommended based on a specific product. Accepts optional pagination.",
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="path",
	 *         description="ID of the product to get recommendations for",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=123)
	 *     ),
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination",
	 *         required=false,
	 *         @OA\Schema(type="integer", default=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Products you may like retrieved successfully"),
	 *             @OA\Property(property="data", type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=101),
	 *                     @OA\Property(property="name", type="string", example="Recommended Product"),
	 *                     @OA\Property(property="images", type="array", @OA\Items(type="string", format="url")),
	 *                     @OA\Property(property="video_url", type="string", format="url", nullable=true),
	 *                     @OA\Property(property="video_path", type="array", @OA\Items(type="string", format="url")),
	 *                     @OA\Property(property="sku", type="string", example="SKU-1234"),
	 *                     @OA\Property(property="original_price", type="number", format="float", example=100.0),
	 *                     @OA\Property(property="sale_price", type="number", format="float", example=80.0),
	 *                     @OA\Property(property="currency", type="string", example="USD"),
	 *                     @OA\Property(property="total_reviews", type="integer", example=25),
	 *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
	 *                     @OA\Property(property="leftStock", type="integer", example=10),
	 *                     @OA\Property(property="in_wishlist", type="boolean", example=true),
	 *                 )
	 *             ),
	 *             @OA\Property(property="pagination", type="object",
	 *                 @OA\Property(property="current_page", type="integer", example=1),
	 *                 @OA\Property(property="last_page", type="integer", example=2),
	 *                 @OA\Property(property="per_page", type="integer", example=50),
	 *                 @OA\Property(property="total", type="integer", example=100),
	 *                 @OA\Property(property="has_more_pages", type="boolean", example=true),
	 *                 @OA\Property(property="visible_pages", type="array", @OA\Items(type="integer")),
	 *                 @OA\Property(property="has_previous", type="boolean", example=false),
	 *                 @OA\Property(property="has_next", type="boolean", example=true),
	 *                 @OA\Property(property="previous_page", type="integer", example=0),
	 *                 @OA\Property(property="next_page", type="integer", example=2),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="Bad request - missing product_id",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Product ID is required")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Internal server error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="An error occurred while fetching products you may like"),
	 *             @OA\Property(property="error", type="string", example="Exception message here")
	 *         )
	 *     )
	 * )
	 */
	public function getProductsYouMayLikeGuest(Request $request, $product_id = null)
	{
		try {
			$input = $product_id ?? $request->input('product_id');

			if (!$input) {
				return response()->json([
					'success' => false,
					'message' => 'Product ID or slug is required',
				], 400);
			}

				// Determine if input is numeric (ID) or string (slug)
			if (is_numeric($input)) {
				$productId = (int) $input;
			} else {
					// Fetch by slug via seoUrl relationship
				$product = Product::whereHas('seoUrl', function ($q) use ($input) {
					$q->where('url', $input);
				})->first();

				if (!$product) {
					return response()->json([
						'success' => false,
						'message' => 'Product not found by slug',
					], 404);
				}

				$productId = $product->id;
			}

			Log::info('Fetching recommendations for product:', ['product_id' => $productId]);


			// Step 1: Find the main "product_you_may_likes" record for this product
			$productYouMayLike = DB::table('product_you_may_likes')
			->where('product_id', $productId)
			->first();

			Log::info('ProductYouMayLike lookup:', [
				'product_id' => $productId,
				'found' => $productYouMayLike ? 'yes' : 'no',
				'record_id' => $productYouMayLike->id ?? null,
			]);

			if (!$productYouMayLike) {
				return response()->json([
					'success' => true,
					'message' => 'No related products found for this product',
					'data' => [],
					'pagination' => $this->emptyPagination(),
				]);
			}

			// Step 2: Get only product IDs from product_you_may_like_items where the product exists in ec_products
			$relatedProductIds = DB::table('product_you_may_like_items')
			->where('product_you_may_like_id', $productYouMayLike->id)
			->pluck('product_id')
			->toArray();

			Log::info('ProductYouMayLikeItems:', [
				'product_you_may_like_id' => $productYouMayLike->id,
				'count' => count($relatedProductIds),
				'product_ids' => $relatedProductIds,
			]);

			if (empty($relatedProductIds)) {
				return response()->json([
					'success' => true,
					'message' => 'No recommended products configured',
					'data' => [],
					'pagination' => $this->emptyPagination(),
				]);
			}

			$productsQuery = Product::with(['categories', 'brand'])
			->where('status', 'published')
			->whereIn('id', $relatedProductIds);

			$products = $productsQuery->get();

			Log::info('Products query result:', [
				'expected_ids' => $relatedProductIds,
				'found_count' => $products->count(),
				'found_ids' => $products->pluck('id')->toArray(),
			]);

			if ($products->isEmpty()) {
				return response()->json([
					'success' => true,
					'message' => 'No published products found in recommendations',
					'data' => [],
					'pagination' => $this->emptyPagination(),
				]);
			}

			$products = $products->sortBy(fn($product) => array_search($product->id, $relatedProductIds));

			$perPage = 50;
			$page = max(1, (int) $request->input('page', 1));
			$total = $products->count();
			$offset = ($page - 1) * $perPage;
			$paginatedProducts = $products->slice($offset, $perPage);

			$productIds = $paginatedProducts->pluck('id')->toArray();
			$productsWithRelations = Product::whereIn('id', $productIds)
			->with(['reviews:id,product_id,star', 'currency', 'productSuppliers' , 'seoUrl'])
			->get()
			->keyBy('id');

			$pagination = $this->buildPagination($page, $perPage, $total);

			$transformedProducts = $paginatedProducts->map(function ($product) use ($productsWithRelations) {
				$productWithRelations = $productsWithRelations->get($product->id) ?? $product;

				$images = $this->normalizeMediaUrls($product->images);
				$videos = $this->normalizeMediaUrls($product->video_path);

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

				$firstSupplier = $product->productSuppliers->first();

				return [
					'id' => $product->id,
					'name' => $product->name,
					'category_url' => $product->category_url(),
					'parent_category_url' => $product->parent_category_url(),
					'images' => $images,
					'url' => $product->seoUrl->url ?? null,
					'video_url' => $product->video_url,
					'video_path' => $videos,
					'sku' => $product->sku,
					'start_date' => $product->start_date,
					'end_date' => $product->end_date,
					'currency' => $productWithRelations->currency?->symbol,
					'total_reviews' => $totalReviews,
					'avg_rating' => $avgRating,
					'leftStock' => $leftStock,
					'currency_title' => $productWithRelations->currency
					? ($productWithRelations->currency->is_prefix_symbol
						? $productWithRelations->currency->symbol
						: ($product->price . ' ' . $productWithRelations->currency->symbol))
					: $product->price,
					'selling_type' => $sellingType,
					'vendor_sku' => $firstSupplier->vendor_sku ?? null,
					'price' => $firstSupplier ? (float) $firstSupplier->price : null,
					'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
					'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
					'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
					'best_price' => $firstSupplier ? (float) $firstSupplier->price : null,
					'per_unit_price' => $product->per_unit_price,
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
					'quote_available' => $product->quote_available ?? null,
					'isRequired' => $product->isRequired,
				];

			});

			Log::info('Returning products:', [
				'total' => $total,
				'page' => $page,
				'count' => $transformedProducts->count(),
			]);

			return response()->json([
				'success' => true,
				'data' => $transformedProducts->values(),
				'pagination' => $pagination,
				'message' => 'Products you may like retrieved successfully',
			]);
		} catch (\Exception $e) {
			Log::error('Error in getProductsYouMayLike: ' . $e->getMessage());
			Log::error('Stack trace: ' . $e->getTraceAsString());

			return response()->json([
				'success' => false,
				'message' => 'An error occurred while fetching products you may like',
				'error' => $e->getMessage(),
			], 500);
		}
	}
}
