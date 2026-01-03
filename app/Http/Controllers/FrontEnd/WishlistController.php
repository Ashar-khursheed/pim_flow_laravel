<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FrontEnd\Wishlist;
use App\Models\SeoManagement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class WishlistController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/wishlist/add",
	 *     tags={"Frontend-Wishlist"},
	 *     summary="Add a product to the wishlist",
	 *     description="Adds a product to the authenticated user's wishlist.",
	 *     operationId="addToWishlist",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id"},
	 *             @OA\Property(property="product_id", type="integer", example=123, description="ID of the product to add")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Product added to wishlist",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product added to wishlist"),
	 *             @OA\Property(
	 *                 property="wishlist",
	 *                 type="object",
	 *                 @OA\Property(property="customer_id", type="integer", example=1),
	 *                 @OA\Property(property="product_id", type="integer", example=123),
	 *                 @OA\Property(property="in_wishlist", type="integer", example=1),
	 *                 @OA\Property(property="created_at", type="string", format="date-time"),
	 *                 @OA\Property(property="updated_at", type="string", format="date-time")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=422, description="Validation error"),
	 *     @OA\Response(response=401, description="Unauthenticated"),
	 *     security={{ "bearerAuth": {} }}
	 * )
	 */
	public function addToWishlist(Request $request)
	{
		// Validate the incoming request data
		$validated = $request->validate([
			'product_id' => 'required|integer|exists:ec_products,id',
			'quantity'   => 'nullable|integer|min:1', // quantity not required but must be >= 1
		]);

		$customerId = Auth::id();

		// If quantity not provided, set default = 1
		$quantity = $validated['quantity'] ?? 1;

		// Check if product already exists in wishlist
		$existingWishlist = Wishlist::where('customer_id', $customerId)
		->where('product_id', $validated['product_id'])
		->first();

		if ($existingWishlist) {
			return response()->json([
				'message' => 'Product already in wishlist',
				'wishlist' => [
					'customer_id' => $existingWishlist->customer_id,
					'product_id' => $existingWishlist->product_id,
					'quantity'    => $existingWishlist->quantity,
					'in_wishlist' => 1,
					'created_at' => $existingWishlist->created_at,
					'updated_at' => $existingWishlist->updated_at,
				]
			], 200);
		}

		// Create new wishlist entry
		$wishlist = Wishlist::create([
			'customer_id' => $customerId,
			'product_id' => $validated['product_id'],
			'quantity'   => $quantity,
		]);

		return response()->json([
			'message' => 'Product added to wishlist',
			'wishlist' => [
				'customer_id' => $wishlist->customer_id,
				'product_id' => $wishlist->product_id,
				'quantity'    => $wishlist->quantity,
				'in_wishlist' => 1,
				'created_at' => $wishlist->created_at,
				'updated_at' => $wishlist->updated_at,
			]
		], 201);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/wishlist",
	 *     tags={"Frontend-Wishlist"},
	 *     summary="Get all products in wishlist",
	 *     description="Returns wishlist products for authenticated user.",
	 *     operationId="getWishlist",
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of wishlist items",
	 *         @OA\JsonContent(
	 *             @OA\Property(
	 *                 property="wishlist",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(
	 *                         property="product",
	 *                         type="object",
	 *                         @OA\Property(property="id", type="integer", example=123),
	 *                         @OA\Property(property="name", type="string", example="Product name"),
	 *                         @OA\Property(property="images", type="array", @OA\Items(type="string", format="url")),
	 *                         @OA\Property(property="in_wishlist", type="integer", example=1)
	 *                     )
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=401, description="Unauthenticated"),
	 *     security={{ "bearerAuth": {} }}
	 * )
	 */
	public function getWishlist(Request $request)
	{
		$userId = Auth::id();
		$wishlistItems = Wishlist::with(
			'product.currency',
			'product.productSuppliers',
			'product.brand',
			'product.seoUrl',
			'product.accessories.items'
		)
		->where('customer_id', $userId)
		->orderBy('created_at', 'desc')
		->get();

		// ===============================
		// TRANSFORM DATA
		// ===============================
		$wishlistItems->transform(function ($item) {

			$product = $item->product;
			if (!$product) {
				return $item;
			}

			// --------------------
			// Brand
			// --------------------
			$product->brand_name = optional($product->brand)->name;
			$product->unsetRelation('brand');

			// --------------------
			// Images
			// --------------------
			$product->images = collect(json_decode($product->images, true) ?? []);
			$product->in_wishlist = 1;
			$product->quantity = $item->quantity ?? 1;

			// --------------------
			// Currency
			// --------------------
			$product->currency = optional($product->currency)->symbol;
			$product->unsetRelation('currency');

			// --------------------
			// URLs
			// --------------------
			$product->url = optional($product->seoUrl)->url;
			$product->category_url = method_exists($product, 'category_url') ? $product->category_url() : null;
			$product->parent_category_url = method_exists($product, 'parent_category_url') ? $product->parent_category_url() : null;

			// --------------------
			// Selling Type
			// --------------------
			$sellingType = null;
			if ($product->sellingUnitAttribute?->attribute_value) {
				$full = $product->sellingUnitAttribute->attribute_value;
				$sellingType = [
					'attribute_value' => $full,
					'attribute_value_unit' => str_contains($full, '/')
					? trim(explode('/', $full)[1])
					: $full,
				];
			}

			// --------------------
			// Supplier Info
			// --------------------
			$supplier = $product->productSuppliers()
			->with([
				'vendor.country:id,name',
				'vendor.city:id,name'
			])
			->first();

			if ($supplier) {
				$product->vendor_sku = $supplier->vendor_sku;

				$product->vendor_country = $supplier->vendor->country->name ?? null;
				$product->vendor_city = $supplier->vendor->city->name ?? null;
				$product->vendor_address = $supplier->vendor->address ?? null;
				$product->vendor_zipcode = $supplier->vendor->zipcode ?? null;

				$product->price = (float) $supplier->price;
				$product->sale_price = (float) $supplier->sale_price;
				$product->original_price = (float) $supplier->price;
				$product->front_sale_price = (float) ($supplier->sale_price ?? $supplier->price);
				$product->best_price = (float) $supplier->price;
				$product->vendor_id = $supplier->vendor_id;
				$product->map = (float) $supplier->map;
				$product->inventory = $supplier->inventory;
				$product->in_stock = $supplier->in_stock;
				$product->delivery_days = $supplier->delivery_days;
				$product->return_policy = $supplier->return_policy;
				$product->free_shipping = $supplier->free_shipping;
				$product->min_quantity = $supplier->min_quantity;
				$product->is_fixed = $supplier->is_fixed;
				$product->selling_type = $sellingType;

				$product->warranty_information =
				$product->warrantyAttribute?->attribute_value
				?? $supplier->warranty_information
				?? null;
			} else {
				$product->vendor_sku = null;
				$product->price = null;
				$product->sale_price = null;
				$product->original_price = null;
				$product->front_sale_price = null;
				$product->best_price = null;
				$product->vendor_id = null;
				$product->map = null;
				$product->inventory = null;
				$product->in_stock = null;
				$product->delivery_days = null;
				$product->return_policy = null;
				$product->free_shipping = null;
				$product->min_quantity = 0;
				$product->is_fixed = 0;
				$product->selling_type = $sellingType;
				$product->warranty_information = $product->warrantyAttribute?->attribute_value ?? null;
			}

			// ===============================
			// Accessories (EXACT SAME AS getAllProducts API) ✅
			// ===============================
			$product->accessories = collect($product->accessories)->map(function ($accessory) {
				return [
					'id' => $accessory->id,
					'name' => $accessory->name,
					'isapproved' => $accessory->isapproved,
					'isRequired' => $accessory->isRequired,
					'items' => collect($accessory->items)->map(function ($item) {
						$cleanName = $item->name;

			// Remove extra quotes or slashes if saved like "\"SDE 1\""
						if (is_string($cleanName)) {
							$cleanName = trim($cleanName, '"');
							$cleanName = stripslashes($cleanName);
						}

						return [
							'id' => $item->id,
							'name' => $cleanName,
							'sku' => $item->sku ?? null,
						];
					})->values(),
				];
			})->values();

			// Ensure it's an array if empty
			if ($product->accessories->isEmpty()) {
				$product->accessories = [];
			}
						// --------------------
				// Required flag (main product)
				// --------------------
			$product->isRequired = (bool) ($product->is_required ?? false);

			return $item;
		});

		return response()->json([//
			'wishlist' => $wishlistItems,
			'total_items' => $wishlistItems->count()
		]);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/frontend/wishlist/remove",
	 *     tags={"Frontend-Wishlist"},
	 *     summary="Remove a product from wishlist",
	 *     description="Removes a product from the authenticated user's wishlist.",
	 *     operationId="removeFromWishlist",
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="query",
	 *         required=true,
	 *         description="ID of the product to remove",
	 *         @OA\Schema(type="integer", example=123)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product removed from wishlist",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product removed from wishlist"),
	 *             @OA\Property(property="in_wishlist", type="integer", example=0)
	 *         )
	 *     ),
	 *     @OA\Response(response=404, description="Product not found in wishlist"),
	 *     @OA\Response(response=401, description="Unauthenticated"),
	 *     @OA\Response(response=422, description="Validation error"),
	 *     security={{ "bearerAuth": {} }}
	 * )
	 */
	public function removeFromWishlist(Request $request)
	{
		$request->validate([
			'product_id' => 'required|integer'
		]);

		$productId = $request->query('product_id');
		$userId = Auth::id();

		$deleted = Wishlist::where('customer_id', $userId)
		->where('product_id', $productId)
		->delete();

		if ($deleted) {
			return response()->json([
				'message' => 'Product removed from wishlist',
				'in_wishlist' => 0
			]);
		}

		return response()->json([
			'message' => 'Product not found in wishlist'
		], 404);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/wishlist/check/{product_id}",
	 *     tags={"Frontend-Wishlist"},
	 *     summary="Check if product is in wishlist",
	 *     description="Check if a specific product is in the authenticated user's wishlist.",
	 *     operationId="checkWishlist",
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the product to check",
	 *         @OA\Schema(type="integer", example=123)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Wishlist check result",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="in_wishlist", type="boolean", example=true),
	 *             @OA\Property(property="product_id", type="integer", example=123)
	 *         )
	 *     ),
	 *     @OA\Response(response=401, description="Unauthenticated"),
	 *     security={{ "bearerAuth": {} }}
	 * )
	 */
	public function checkWishlist($productId)
	{
		$userId = Auth::id();

		$exists = Wishlist::where('customer_id', $userId)
		->where('product_id', $productId)
		->exists();

		return response()->json([
			'in_wishlist' => $exists,
			'product_id' => (int) $productId
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/wishlist/count",
	 *     tags={"Frontend-Wishlist"},
	 *     summary="Get wishlist items count",
	 *     description="Returns the total number of items in the authenticated user's wishlist.",
	 *     operationId="getWishlistCount",
	 *     @OA\Response(
	 *         response=200,
	 *         description="Wishlist count",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="count", type="integer", example=5)
	 *         )
	 *     ),
	 *     @OA\Response(response=401, description="Unauthenticated"),
	 *     security={{ "bearerAuth": {} }}
	 * )
	 */
	public function getWishlistCount()
	{
		$userId = Auth::id();

		$count = Wishlist::where('customer_id', $userId)->count();

		return response()->json([
			'count' => $count
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/wishlist/remove-multiple",
	 *     operationId="removeMultipleFromWishlist",
	 *     tags={"Frontend-Wishlist"},
	 *     summary="Remove multiple products from wishlist",
	 *     description="Removes one or more products from the authenticated user's wishlist.",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             type="object",
	 *             required={"product_ids"},
	 *             @OA\Property(
	 *                 property="product_ids",
	 *                 type="array",
	 *                 @OA\Items(type="integer", example=1),
	 *                 example={1, 5, 9}
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Selected products removed from wishlist",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Selected products removed from wishlist."),
	 *             @OA\Property(property="deleted_count", type="integer", example=3)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
	 *             @OA\Property(
	 *                 property="errors",
	 *                 type="object",
	 *                 example={"product_ids": {"The product_ids field is required."}}
	 *             )
	 *         )
	 *     )
	 * )
	 */
	public function removeMultipleFromWishlist(Request $request)
	{
		$request->validate([
			'product_ids' => 'required|array',
			'product_ids.*' => 'required|integer|exists:ec_products,id',
		]);

		$productIds = $request->input('product_ids');
		$userId = Auth::id();

		$deleted = Wishlist::where('customer_id', $userId)
		->whereIn('product_id', $productIds)
		->delete();

		return response()->json([
			'success' => true,
			'message' => $deleted > 0 ? 'Selected products removed from wishlist.' : 'No matching products found in wishlist.',
			'deleted_count' => $deleted,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/wishlist/add-multiple",
	 *     operationId="addMultipleToWishlist",
	 *     tags={"Frontend-Wishlist"},
	 *     summary="Add multiple products to wishlist",
	 *     description="Adds multiple products to the authenticated customer's wishlist.",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             type="object",
	 *             required={"products"},
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     required={"product_id"},
	 *                     @OA\Property(property="product_id", type="integer", example=2)
	 *                 ),
	 *                 example={{"product_id": 2}, {"product_id": 4}, {"product_id": 7}}
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Products added to wishlist",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Products added to wishlist."),
	 *             @OA\Property(property="added_count", type="integer", example=3)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
	 *             @OA\Property(
	 *                 property="errors",
	 *                 type="object",
	 *                 example={
	 *                     "products.0.product_id": {"The selected product_id is invalid."}
	 *                 }
	 *             )
	 *         )
	 *     )
	 * )
	 */
	public function addMultipleToWishlist(Request $request)
	{
		$request->validate([
			'products' => 'required|array',
			'products.*.product_id' => 'required|exists:ec_products,id',
		]);

		$products = $request->input('products');
		$userId = Auth::id();

		$added = 0;

		foreach ($products as $item) {
			$productId = $item['product_id'];
			// Check if the product is already in the wishlist
			$exists = Wishlist::where('customer_id', $userId)
			->where('product_id', $productId)
			->exists();

			if (!$exists) {
				Wishlist::create([
					'customer_id' => $userId,
					'product_id' => $productId,
				]);
				$added++;
			}
		}

		return response()->json([
			'success' => true,
			'message' => $added > 0 ? 'Products added to wishlist.' : 'All products were already in wishlist.',
			'added_count' => $added,
		]);
	}
}