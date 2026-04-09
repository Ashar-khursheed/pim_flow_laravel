<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;
use App\Models\FrontEnd\CustomerCart;
use App\Models\FrontEnd\CustomerCartProduct;
use App\Models\FrontEnd\Wishlist;
use App\Models\FrontEnd\SaveForLater;
use App\Models\Product;
use App\Helpers\PriceHelper;


class SaveForLaterController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/save-for-later",
	 *     summary="Move a product from cart to Save for Later",
	 *     tags={"Frontend-Save For Later"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id", "quantity", "vendor_id"},
	 *             @OA\Property(property="product_id", type="integer", example=123, description="ID of the product"),
	 *             @OA\Property(property="quantity", type="integer", example=1, description="Quantity to move"),
	 *             @OA\Property(property="vendor_id", type="integer", example=1, description="Vendor ID related to the product")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product has been moved to Save for Later",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product has been moved to Save for Later.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found in cart",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Product not found in cart.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Validation failed"),
	 *             @OA\Property(property="errors", type="object")
	 *         )
	 *     )
	 * )
	 */
	public function saveForLater(Request $request)
	{
		$request->validate([
			'product_id' => 'required|exists:ec_products,id',
			'vendor_id' => 'nullable|exists:vendors,id',
			'quantity' => 'required|integer',
		]);

		if (!Auth::check()) {
			return response()->json([
				'success' => false,
				'message' => 'Customer not authenticated.',
			], 401);
		}

		$userId = Auth::id();
		$productId = $request->product_id;
		$vendorId = $request->vendor_id;
		$quantity = $request->quantity;

		// Get product with supplier info
		$product = Product::with('productSuppliers')->find($productId);
		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found',
			], 404);
		}

		//  Determine actual vendor
		$supplier = $vendorId
		? $product->productSuppliers->where('vendor_id', $vendorId)->first()
		: $product->productSuppliers->first();

		if (!$supplier) {
			return response()->json([
				'success' => false,
				'message' => 'Product supplier not found',
			], 200);
		}



		// Check if product exists in the cart
		$customerCart = CustomerCart::where('customer_id', $userId)->first();
		$cartProduct = $customerCart
		? CustomerCartProduct::where('customer_cart_id', $customerCart->id)
		->where('product_id', $productId)
		->where('vendor_id', $supplier->vendor_id)
		->first()
		: null;

		if ($cartProduct) {
			$quantity = $cartProduct->quantity;
			$cartProduct->delete();
		} else {
			// Check if product exists in wishlist
			$wishlistItem = Wishlist::where('customer_id', $userId)
			->where('product_id', $productId)
			->first();

			if ($wishlistItem) {
				$wishlistItem->delete();
			} else {
				return response()->json([
					'success' => false,
					'message' => 'Product not found in cart or wishlist.',
				], 200);
			}
		}

		// Move to SaveForLater (ignore vendor_id here)
		SaveForLater::updateOrCreate(
			[
				'user_id' => $userId,
				'product_id' => $productId,
			],
			[
				'quantity' => $quantity,
			]
		);

		return response()->json([
			'success' => true,
			'message' => 'Product has been moved to Save for Later.',
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/save-for-later",
	 *     summary="Get all products saved for later by the user",
	 *     tags={"Frontend-Save For Later"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of saved products",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Saved for Later Products retrieved successfully."),
	 *             @OA\Property(property="product", type="array", @OA\Items(
	 *                 @OA\Property(property="id", type="integer", example=123),
	 *                 @OA\Property(property="name", type="string", example="Sample Product"),
	 *                 @OA\Property(property="price", type="number", format="float", example=99.99),
	 *                 @OA\Property(property="currency_title", type="string", example="$"),
	 *                 @OA\Property(property="total_reviews", type="integer", example=10),
	 *                 @OA\Property(property="avg_rating", type="number", format="float", example=4.5)
	 *             ))
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No products saved for later",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="No products saved for later.")
	 *         )
	 *     )
	 * )
	 */
	public function showSaveForLater(Request $request)
	{
		$userId = auth()->id();

		// Get wishlist product IDs
		$wishlistProductIds = DB::table('ec_wish_lists')
		->where('customer_id', $userId)
		->pluck('product_id')
		->map(fn($id) => (int) $id)
		->toArray();

		// Fetch all saved products with relationships
		$savedProducts = SaveForLater::where('user_id', $userId)
		->with([
			'product.reviews',
			'product.currency',
			'product.sellingUnitAttribute',
			'product.productSuppliers',
		   'product.seoUrl', // <- move seoUrl here
		])
		->get();

		if ($savedProducts->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No products saved for later.',
				'data' => []
			], 200);
		}

		$productsData = $savedProducts->map(function ($item) use ($wishlistProductIds) {
			$product = $item->product;
			if (!$product) return null;

		// Ratings
			$totalReviews = $product->reviews->count();
			$avgRating = $totalReviews > 0 ? round($product->reviews->avg('star'), 1) : null;

		// Images
			$imageUrls = is_string($product->images) ? json_decode($product->images, true) : (array) $product->images;

		// Selling type
			$sellingType = null;
			if ($product->sellingUnitAttribute?->attribute_value) {
				$fullValue = $product->sellingUnitAttribute->attribute_value;
				$attributeUnit = strpos($fullValue, '/') !== false
				? trim(explode('/', $fullValue)[1])
				: $fullValue;

				$sellingType = [
					'attribute_value' => $fullValue,
					'attribute_value_unit' => $attributeUnit,
				];
			}

		// Per unit price
			$unitsPerCase = $product->per_unit_price_attributes?->firstWhere(
				fn($attr) => $attr->attributeDetails->name === 'Units per Case'
			);
			$packType = $product->per_unit_price_attributes?->firstWhere(
				fn($attr) => $attr->attributeDetails->name === 'Pack Type'
			);
			$basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
			$perUnitPrice = null;
			if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
				$unitValue = (float) $unitsPerCase->attribute_value;
				if ($unitValue > 0) {
					$perUnitPrice = round($basePrice / $unitValue, 2) . ' /' . ($packType?->attribute_value ?? '');
				}
			}
			$product->per_unit_price = $perUnitPrice;

			$firstSupplier = $product->productSuppliers()
			->with([
				'vendor.country:id,name',
				'vendor.city:id,name',
				'inventoryUpdator:id,first_name,last_name'
			])
			->first();

			return [
				'id' => $product->id,
				'name' => $product->name,
				'category_url' => $product->category_url(),
				'parent_category_url' => $product->parent_category_url(),
				'sku' => $product->sku,
				'url' => $product->seoUrl->url ?? null,
				'total_reviews' => $totalReviews,
				'avg_rating' => $avgRating,
				'left_stock' => ($product->quantity ?? 0) - ($product->units_sold ?? 0),
				// 'currency' => $product->currency->symbol ?? null,
				'currency' => PriceHelper::symbol(),
				'in_wishlist' => in_array($product->id, $wishlistProductIds),
				'images' => $imageUrls,
				'selling_type' => $sellingType,
				'per_unit_price' => $product->per_unit_price,
				'vendor_sku' => $firstSupplier->vendor_sku ?? null,

				'vendor_country' => $firstSupplier->vendor->country->name ?? null,
				'vendor_city' => $firstSupplier->vendor->city->name ?? null,
				'vendor_address' => $firstSupplier->vendor->address ?? null,
				'vendor_zipcode' => $firstSupplier->vendor->zipcode ?? null,

				'price' => (float) ($firstSupplier->price ?? 0),
				'sale_price' => (float) ($firstSupplier->sale_price ?? 0),
				'original_price' => (float) ($firstSupplier->price ?? 0),
				'front_sale_price' => (float) ($firstSupplier->sale_price ?? 0),
				'best_price' => (float) ($firstSupplier->price ?? 0),
				'vendor_id' => $firstSupplier->vendor_id ?? null,
				'map' => (float) ($firstSupplier->map ?? 0),
				'inventory' => $firstSupplier->inventory ?? null,
				'inventory_updated_by' => $firstSupplier->inventoryUpdator->name ?? null,
				'inventory_updated_at' => $firstSupplier->inventory_updated_at ?? null,
				'in_stock' => $firstSupplier->in_stock ?? null,
				'delivery_days' => $firstSupplier->delivery_days ?? null,
				'return_policy' => $firstSupplier->return_policy ?? null,
				'free_shipping' => $firstSupplier->free_shipping ?? null,
				'warranty_information' => $firstSupplier->warranty_information ?? null,
				'min_quantity' => $firstSupplier->min_quantity ?? 0,
				'is_fixed' => $firstSupplier->is_fixed ?? 0,
				'quantity' => $item->quantity ?? 1,
				'quote_available' => $product->quote_available ?? null,
				'isRequired' => $product->isRequired,
			];
		})->filter()->values();

		return response()->json([
			'success' => true,
			'message' => 'Saved for Later Products retrieved successfully.',
			'data' => $productsData
		], 200);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/frontend/save-for-later",
	 *     summary="Remove a product from Save for Later",
	 *     tags={"Frontend-Save For Later"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id"},
	 *             @OA\Property(property="product_id", type="integer", example=123)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product has been removed from Save for Later",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product has been removed from Save for Later.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found in Save for Later",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product not found in Save for Later.")
	 *         )
	 *     )
	 * )
	 */
	public function removeFromSaveForLater($product_id)
	{
		 // Optional: Check if the product exists in the products table
		if (!\DB::table('ec_products')->where('id', $product_id)->exists()) {
			return response()->json([
				'success' => false,
				'message' => 'Product does not exist.',
				'data' => []
			], 404);
		}

		 // Get the logged-in user ID
		$userId = auth()->id();

		 // Find the saved product
		$savedProduct = SaveForLater::where('user_id', $userId)
		->where('product_id', $product_id)
		->first();

		if (!$savedProduct) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found in Save for Later.',
				'data' => [],
			], 200);
		}

		 // Delete the record
		$savedProduct->delete();

		return response()->json([
			'success' => true,
			'message' => 'Product has been removed from Save for Later.',
			'data' => []
		], 200);
	}
}
