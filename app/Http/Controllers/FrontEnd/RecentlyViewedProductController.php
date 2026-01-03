<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\FrontEnd\RecentlyViewedProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use App\Models\FrontEnd\GuestRecentlyViewedProduct;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use App\Models\SeoManagement;

class RecentlyViewedProductController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/recent-products/add",
	 *     tags={"Frontend-Recently Viewed Products"},
	 *     summary="Add product to recently viewed list",
	 *     description="Adds a product to the authenticated user's recently viewed list.",
	 *     operationId="addToRecent",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id"},
	 *             @OA\Property(property="product_id", type="integer", example=123, description="ID of the product to be added")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product added to recently viewed list",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product added to recently viewed list.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="User not authenticated",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="User not authenticated.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error"
	 *     )
	 * )
	 */
	public function addToRecent(Request $request)
	{
		$request->validate([
			'product_id' => 'required|string',
		]);

		$input = $request->input('product_id');

		// Resolve product ID
		if (is_numeric($input)) {
			$productId = (int) $input;
		} else {
			$product = Product::whereHas('seoUrl', function ($query) use ($input) {
				$query->where('url', $input);
			})->first();

			if (!$product) {
				return response()->json(['message' => 'Product not found.'], 404);
			}

			$productId = $product->id;
		}

		$userId = Auth::id();

		if ($userId) {
			RecentlyViewedProduct::updateOrCreate(
				['customer_id' => $userId, 'product_id' => $productId],
				['updated_at' => now()]
			);

			return response()->json(['message' => 'Product added to recently viewed list.'], 200);
		}

		return response()->json(['message' => 'User not authenticated.'], 401);
	}

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
	 *     path="/api/frontend/recent-products",
	 *     tags={"Frontend-Recently Viewed Products"},
	 *     summary="Get recently viewed products",
	 *     description="Returns the last 5 recently viewed products for the authenticated user.",
	 *     operationId="getRecentProducts",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Recently viewed products retrieved successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="product_id", type="integer", example=123),
	 *                     @OA\Property(property="name", type="string", example="Sample Product"),
	 *                     @OA\Property(property="sku", type="string", example="SP123"),
	 *                     @OA\Property(property="price", type="number", format="float", example=99.99),
	 *                     @OA\Property(property="sale_price", type="number", format="float", example=89.99),
	 *                     @OA\Property(property="delivery_days", type="string", example="2025-06-10"),
	 *                     @OA\Property(property="total_reviews", type="integer", example=5),
	 *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
	 *                     @OA\Property(property="left_stock", type="integer", example=10),
	 *                     @OA\Property(property="currency", type="string", example="USD"),
	 *                     @OA\Property(property="in_wishlist", type="boolean", example=true),
	 *                     @OA\Property(property="images", type="array", @OA\Items(type="string", example="https://example.com/image.jpg")),
	 *                     @OA\Property(property="original_price", type="number", format="float", example=99.99),
	 *                     @OA\Property(property="front_sale_price", type="number", format="float", example=89.99),
	 *                     @OA\Property(property="best_price", type="number", format="float", example=89.99)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No recently viewed products found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="No recently viewed products found.")
	 *         )
	 *     )
	 * )
	 */
	public function getRecentProducts()
	{
		$userId = Auth::id(); // Get authenticated user

		if ($userId) {
			// Fetch recently viewed products for the logged-in user, eager load the related product data
			$recentlyViewed = RecentlyViewedProduct::with([
				'product.productSuppliers',
				'product.reviews',
				'product.currency',
				'product.sellingUnitAttribute',
				'product.productAttributes.attributeDetails',
				'product.seoUrl',
			])
			->where('customer_id', $userId)
			->latest()
			->get();

			// Get wishlist product IDs
			$wishlistIds = $this->getWishlistProductIds();

			// Check if we have any recently viewed products
			if ($recentlyViewed->isEmpty()) {
				return response()->json(['message' => 'No recently viewed products found.'], 404);
			}


			return response()->json([
				'success' => true,
				'data' => $recentlyViewed
				->map(function ($viewed) use ($wishlistIds) {
					$product = $viewed->product;

					// ✅ Skip null or unpublished products
					if (!$product || $product->status !== 'published') {
						return null;
					}

					$imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
					$cleanedImages = collect($imageArray)->map(function ($item) {
						if (is_string($item) && str_starts_with($item, '[')) {
							$decoded = json_decode($item, true);
							return is_array($decoded) ? $decoded : [$item];
						}
						return [$item];
					})->flatten()->filter()->values();

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
						'sku' => $product->sku,
						'category_url' => $product->category_url(),
						'parent_category_url' => $product->parent_category_url(),
						'url' => $product->seoUrl->url ?? null,
						'total_reviews' => $product->reviews->count(),
						'avg_rating' => $product->reviews->count() > 0 ? $product->reviews->avg('star') : null,
						'left_stock' => $product->left_stock ?? 0,
						'currency' => $product->currency->symbol ?? '$',
						'in_wishlist' => in_array($product->id, $wishlistIds),
						'images' => $cleanedImages,
						"selling_type" => $sellingType,

						// 🔹 Supplier-safe values
						'vendor_sku' => $firstSupplier->vendor_sku ?? null,
						'price' => (float) ($firstSupplier->price ?? 0),
						'sale_price' => (float) ($firstSupplier->sale_price ?? 0),
						'original_price' => (float) ($firstSupplier->price ?? 0),
						'front_sale_price' => (float) ($firstSupplier->sale_price ?? 0),
						'best_price' => (float) ($firstSupplier->price ?? 0),
						"per_unit_price"=> $product->per_unit_price ?? 0,

						'vendor_id' => $firstSupplier->vendor_id ?? null,
						'map' => (float) ($firstSupplier->map ?? 0),
						'inventory' => $firstSupplier->inventory ?? 0,
						'in_stock' => $firstSupplier->in_stock ?? 0,
						'delivery_days' => $firstSupplier->delivery_days ?? null,
						'return_policy' => $firstSupplier->return_policy ?? null,
						'free_shipping' => $firstSupplier->free_shipping ?? null,
						'warranty_information' => $firstSupplier->warranty_information ?? null,
						'min_quantity' => $firstSupplier->min_quantity ?? 0,
						'is_fixed' => $firstSupplier->is_fixed ?? 0,
						'quote_available' => $product->quote_available ?? null,
						'isRequired' => $product->isRequired,
					];
				})
				->filter()
				->values()
				->all(),
			]);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/guest/view-product",
	 *     tags={"Guest"},
	 *     summary="Save guest product view",
	 *     description="Stores a product view by a guest user using a cookie-based guest_token.",
	 *     operationId="saveGuestProductView",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id"},
	 *             @OA\Property(property="product_id", type="integer", example=123)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product view saved successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="Invalid product ID or no guest_token",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Invalid product ID.")
	 *         )
	 *     )
	 * )
	 */
	public function saveGuestProductView(Request $request)
	{
		$url = $request->input('product_id');
		$guestToken = $request->input('guest_token');

		// Find SEO record by URL
		$seo = SeoManagement::where('url', $url)->first();

		if (!$seo) {
			return response()->json(['message' => 'Product not found by URL.'], 404);
		}

		// Now fetch the product using relational_id (assumed to be product_id)
		$product = Product::where('id', $seo->relational_id)
		->where('status', 'published')
		->first();

		if (!$product) {
			return response()->json(['message' => 'Invalid or unpublished product.'], 400);
		}

		// Generate guest token if not provided
		if (!$guestToken) {
			$guestToken = Str::uuid()->toString();
		}

		// Check if this view already exists
		$exists = GuestRecentlyViewedProduct::where('guest_token', $guestToken)
		->where('product_id', $product->id)
		->exists();

		if (!$exists) {
			GuestRecentlyViewedProduct::create([
				'guest_token' => $guestToken,
				'product_id' => $product->id,
			]);
		}

		return response()->json([
			'success' => true,
			'guest_token' => $guestToken,
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/guest/recent-products",
	 *     tags={"Guest"},
	 *     summary="Get recently viewed guest products",
	 *     description="Returns the last 5 products viewed by a guest using the guest_token from cookies.",
	 *     operationId="getGuestRecentProducts",
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of recently viewed products",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="product_id", type="integer", example=1),
	 *                     @OA\Property(property="name", type="string", example="Product Name"),
	 *                     @OA\Property(property="sku", type="string", example="SKU123"),
	 *                     @OA\Property(property="price", type="number", format="float", example=99.99),
	 *                     @OA\Property(property="sale_price", type="number", format="float", example=89.99),
	 *                     @OA\Property(property="delivery_days", type="string", format="date", example="2025-06-20"),
	 *                     @OA\Property(property="total_reviews", type="integer", example=15),
	 *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
	 *                     @OA\Property(property="left_stock", type="integer", example=10),
	 *                     @OA\Property(property="currency", type="string", example="USD"),
	 *                     @OA\Property(property="in_wishlist", type="boolean", example=false),
	 *                     @OA\Property(property="images", type="array", @OA\Items(type="string", example="https://example.com/image.jpg")),
	 *                     @OA\Property(property="original_price", type="number", format="float", example=99.99),
	 *                     @OA\Property(property="front_sale_price", type="number", format="float", example=99.99),
	 *                     @OA\Property(property="best_price", type="number", format="float", example=99.99)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="No guest token found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="No guest token found.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No recently viewed products found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="No recently viewed products found.")
	 *         )
	 *     )
	 * )
	 */
	public function getGuestRecentProducts(Request $request)
	{
		 // Get token from param or cookie
		$guestToken = $request->input('guest_token') ?? $request->cookie('guest_token');

		if (!$guestToken) {
			return response()->json(['message' => 'No guest token found.'], 400);
		}

		$data = $this->getGuestRecentlyViewedData($guestToken);

		if (empty($data)) {
			return response()->json(['message' => 'No recently viewed products found.'], 404);
		}

		return response()->json([
			'success' => true,
			'data' => $data,
		]);
	}

	private function getGuestRecentlyViewedData(string $guestToken): array
	{
		$recentlyViewed = GuestRecentlyViewedProduct::with('product.reviews', 'product.currency' ,'product.productSuppliers', 'product.seoUrl')
		->where('guest_token', $guestToken)
		->latest()
		->get();

		$data = [];

		foreach ($recentlyViewed as $viewed) {
			$product = $viewed->product;
			if (!$product) continue;

			$images = $product->images;
			if (is_string($images)) {
				$images = json_decode($images, true);
			}
			if (!is_array($images)) {
				$images = [];
			}

			$cleanedImages = collect($images)->flatten()->filter()->values();
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


			$data[] = [
				'id' => $product->id,
				'name' => $product->name,
				'category_url' => $product->category_url(),
				'parent_category_url' => $product->parent_category_url(),
				'sku' => $product->sku,
				'url' => $product->seoUrl->url ?? null,
				'total_reviews' => $product->reviews->count(),
				'avg_rating' => $product->reviews->avg('star'),
				'left_stock' => $product->left_stock ?? 0,
				'currency' => $product->currency->symbol ?? '$',
				'in_wishlist' => false,
				'images' => $cleanedImages,
				"selling_type"=> $sellingType,
				'vendor_sku' => $firstSupplier->vendor_sku ?? null,
				'price' => (float) ($firstSupplier->price ?? 0),
				"sale_price" => (float) ($firstSupplier->sale_price ?? 0),
				"original_price"=> (float) ($firstSupplier->price ?? 0),
				'front_sale_price' => (float) ($firstSupplier->sale_price ?? $firstSupplier->price ?? 0),
				"best_price"=> (float) ($firstSupplier->price ?? 0),
				"per_unit_price"=> $product->per_unit_price ?? null,
				'vendor_id' => $firstSupplier->vendor_id ?? null,
				'map' => (float) ($firstSupplier->map ?? 0),
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
		}

		return $data;
	}
}

