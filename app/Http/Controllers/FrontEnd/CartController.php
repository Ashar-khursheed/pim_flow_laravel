<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FrontEnd\CustomerCart;
use App\Models\FrontEnd\CustomerCartProduct;
use App\Models\Product;
use App\Models\ProductAccessory;
use App\Models\AccessoryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;


class CartController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/cart/add",
	 *     tags={"Frontend-Cart"},
	 *     summary="Add product to cart",
	 *     description="Add a product to the user's shopping cart. If the product already exists in cart, the quantity will be increased.",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id", "quantity"},
	 *             @OA\Property(property="product_id", type="integer", description="ID of the product to add", example=1),
	 *             @OA\Property(property="quantity", type="integer", minimum=1, description="Quantity to add", example=2),
	 *             @OA\Property(property="vendor_id", type="integer", description="ID of the vendor", example=69)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product added to cart successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="object",
	 *                 @OA\Property(property="id", type="integer", example=1),
	 *                 @OA\Property(property="user_id", type="integer", example=1),
	 *                 @OA\Property(property="product_id", type="integer", example=1),
	 *                 @OA\Property(property="quantity", type="integer", example=3),
	 *                 @OA\Property(property="currency_id", type="integer", example=1),
	 *                 @OA\Property(property="currency_title", type="string", example="USD")
	 *             )
	 *         )
	 *     ),
	 *     security={{"bearerAuth": {}}}
	 * )
	 */
	public function addToCart(Request $request)
	{
		$request->validate([
			'product_id' => 'required|exists:ec_products,id',
			'quantity' => 'required|integer|min:1',
			'vendor_id' => 'nullable|exists:vendors,id',
			'accessories_options' => 'nullable|array',
			'accessories_options.*' => 'integer|exists:accessory_items,id',
		]);

		$productId = $request->input('product_id');
		$quantity = $request->input('quantity');
		$vendorId = $request->input('vendor_id');
		$selectedOptions = $request->input('accessories_options', []);

		if (!Auth::check()) {
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized user',
			], 401);
		}

		$userId = Auth::id();

		// Get or create customer cart
		$customerCart = CustomerCart::firstOrCreate(
			['customer_id' => $userId],
			[
				'reference_number' => $this->generateReferenceNumber(),
				'customer_address_id' => 0,
				'shipping_charge' => 0,
				'is_lift_gate' => 0,
				'is_residential_address' => 1,
				'is_inside_delivery' => 0,
				'amount' => 0,
				'tax_percentage' => 0,
				'tax_amount' => 0,
				'total_amount' => 0,
				'total_products' => 0,
				'created_by' => 0,
			]
		);

		// Get product with supplier
		$product = Product::with('currency', 'productSuppliers')->find($productId);
		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found',
			], 404);
		}

		// Get supplier info
		$supplier = $vendorId
		? $product->productSuppliers->where('vendor_id', $vendorId)->first()
		: $product->productSuppliers->first();

		if (!$supplier) {
			return response()->json([
				'success' => false,
				'message' => 'Product supplier not found',
			], 404);
		}

		$unitPrice = $supplier->sale_price ?: $supplier->price;
		$actualVendorId = $supplier->vendor_id;

		// Calculate price of selected accessory items
		$optionPrice = 0;
		foreach ($selectedOptions as $itemId) {
			$accessoryItem = AccessoryItem::find($itemId);
			if ($accessoryItem) {
				$optionPrice += $accessoryItem->price ?? 0;
			}
		}

		$totalUnitPrice = $unitPrice + $optionPrice;
		$amount = $quantity * $totalUnitPrice;

		// Check if same product with same options exists in cart
		$cartProduct = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
		->where('product_id', $productId)
		->where('vendor_id', $actualVendorId)
		->where('accessories_options', json_encode($selectedOptions))
		->first();

		if ($cartProduct) {
			// Update quantity and total
			$cartProduct->quantity += $quantity;
			$cartProduct->amount = $cartProduct->quantity * $totalUnitPrice;
			$cartProduct->total_amount = $cartProduct->amount + $cartProduct->shipping_charge;
			$cartProduct->save();
		} else {
			// Create new cart product
			$cartProduct = CustomerCartProduct::create([
				'customer_cart_id' => $customerCart->id,
				'product_id' => $productId,
				'vendor_id' => $actualVendorId,
				'quantity' => $quantity,
				'unit_price' => $unitPrice,
				'accessories_options' => $selectedOptions, // JSON: accessory_id => selected_item_id
				'amount' => $amount,
				'shipping_charge' => 0,
				'total_amount' => $amount,
			]);
		}

		// Update cart totals
		$this->updateCartTotals($customerCart);

		return response()->json([
			'success' => true,
			'data' => [
				'id' => $cartProduct->id,
				'user_id' => $userId,
				'product_id' => $cartProduct->product_id,
				'quantity' => $cartProduct->quantity,
				'currency_id' => $product->currency->id,
				'currency_title' => $product->currency->symbol,
				'accessories_options' => $cartProduct->accessories_options,
				'unit_price' => $totalUnitPrice,
				'total_amount' => $cartProduct->total_amount,
			],
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/cart",
	 *     tags={"Frontend-Cart"},
	 *     summary="View cart items",
	 *     description="Retrieve all items in the user's shopping cart with product details, discounts, and wishlist status",
	 *     security={{"bearerAuth": {}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="product_id", type="integer", example=1),
	 *                     @OA\Property(property="name", type="string", example="Sample Product"),
	 *                     @OA\Property(property="price", type="number", format="float", example=99.99),
	 *                     @OA\Property(property="quantity", type="integer", example=2),
	 *                     @OA\Property(property="discount", type="number", format="float", example=10.00),
	 *                     @OA\Property(property="wishlist", type="boolean", example=false)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Bearer token missing or invalid"
	 *     )
	 * )
	 */
	public function viewCart(Request $request)
	{
		$userId = auth()->id();
		if (!$userId) {
			return response()->json([
				'success' => false,
				'message' => 'User not authenticated',
			], 401);
		}

		$cartId = $this->generateSimpleCartId($userId);

		$wishlistProductIds = DB::table('ec_wish_lists')
		->where('customer_id', $userId)
		->pluck('product_id')
		->map(fn($id) => (int)$id)
		->toArray();

		$customerCart = CustomerCart::where('customer_id', $userId)->first();

		if (!$customerCart) {
			return response()->json([
				'success' => true,
				'data' => [],
				'cart_id' => $cartId,
				'checkout_url' => url("review/Checkout/{$cartId}")
			]);
		}

		$cartItems = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
		->with([
			'product.currency',
			'product.productSuppliers',
			'product.seoUrl',
			'product.sellingUnitAttribute',
			'product.accessories.items'
		])
		->get();

		$transformedItems = $cartItems->map(function ($cartProduct) use ($wishlistProductIds, $customerCart) {
			$product = $cartProduct->product;

			$cartItem = (object)[
				'id' => $cartProduct->id,
				'user_id' => $cartProduct->customerCart->customer_id,
				'product_id' => $cartProduct->product_id,
				'product_id' => $cartProduct->product_id,
				'product_id' => $cartProduct->product_id,
				'quantity' => $cartProduct->quantity,
				'product' => $product
			];

			$product->in_wishlist = in_array($product->id, $wishlistProductIds);
			$product->images = collect(json_decode($product->images, true) ?? []);
			$product->category_url = method_exists($product, 'category_url') ? $product->category_url() : null;
			$product->parent_category_url = method_exists($product, 'parent_category_url') ? $product->parent_category_url() : null;

			$product->url = $product->seoUrl->url ?? null;

			$symbol = optional($product->currency)->symbol;
			$product->unsetRelation('currency');
			$product->currency = $symbol;

			$supplier = $cartProduct->vendorProductSupplier;
			if ($supplier) {
				$product->vendor_sku = $supplier->vendor_sku ?? null;

				$product->vendor_country = $supplier->vendor->country->name ?? null;
				$product->vendor_city = $supplier->vendor->city->name ?? null;
				$product->vendor_address = $supplier->vendor->address ?? null;
				$product->vendor_zipcode = $supplier->vendor->zipcode ?? null;

				$product->price = (float)$supplier->price;
				$product->sale_price = (float)$supplier->sale_price;
				$product->original_price = (float)$supplier->price;
				$product->front_sale_price = (float)$cartProduct->unit_price;
				$product->best_price = (float)$cartProduct->unit_price;
				$product->vendor_id = $cartProduct->vendor_id;
				$product->map = (float)$supplier->map;
				$product->inventory = $supplier->inventory;
				$product->in_stock = $supplier->in_stock;
				$product->delivery_days = $supplier->delivery_days;
				$product->return_policy = $supplier->return_policy;
				$product->free_shipping = $supplier->free_shipping;
				$product->warranty_information = $supplier->warranty_information;
			} else {
				$product->vendor_sku = null;
				$product->price = (float)$cartProduct->unit_price;
				$product->sale_price = (float)$cartProduct->unit_price;
				$product->original_price = (float)$cartProduct->unit_price;
				$product->front_sale_price = (float)$cartProduct->unit_price;
				$product->best_price = (float)$cartProduct->unit_price;
				$product->vendor_id = $cartProduct->vendor_id;
				$product->map = null;
				$product->inventory = null;
				$product->in_stock = null;
				$product->delivery_days = null;
				$product->return_policy = null;
				$product->free_shipping = null;
				$product->warranty_information = null;
			}

			// Add selling unit
			$sellingUnit = null;
			if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
				$fullValue = $product->sellingUnitAttribute->attribute_value;
				$sellingUnit = strpos($fullValue, '/') !== false
				? trim(explode('/', $fullValue)[1])
				: $fullValue;
			}
			$product->selling_unit = $sellingUnit;

			// Add selected accessories details
			$cartItem->accessories_options_details = [];

			if ($cartProduct->accessories_options && is_array($cartProduct->accessories_options)) {
					// eager load accessory relation for efficiency
				$accessoryItems = AccessoryItem::with('accessory')
				->whereIn('id', $cartProduct->accessories_options)
				->get();

				foreach ($accessoryItems as $item) {
					$cartItem->accessories_options_details[] = [
						'accessory_name' => $item->accessory->name ?? null,
						'item_name'      => $item->name,
						'item_id'      => $item->id,
						'price'          => (float)$item->price,
					];
				}
			}
			$productShipping = $cartProduct->shipping_charge ?? 0;

			if (in_array(config('app.website'), ['US', 'US_T'])) {

				$state = $customerCart->customerAddress->state ?? null;

				if (!$customerCart->is_customer_pickup) {
					if ($state === 'Texas') {
						$productShipping = ($productShipping > 0) ? $productShipping : 99;
					} else {
						$productShipping = ($productShipping > 0) ? $productShipping : 199;
					}
				} else {
					$productShipping = 0;
				}
			}

			$product->shippingCharge = $productShipping;

				// ------------------- SHIPPING CHARGE LOGIC FOR CUSTOMER CART -------------------
			$cartShippingCharge = $customerCart->shipping_charge ?? 0;

			if (in_array(config('app.website'), ['US', 'US_T'])) {

				$state = $customerCart->customerAddress->state ?? null;

				if (!$customerCart->is_customer_pickup) {

					if ($state === 'Texas') {
						$cartShippingCharge = ($cartShippingCharge > 0) ? $cartShippingCharge : 99;
					} else {
						$cartShippingCharge = ($cartShippingCharge > 0) ? $cartShippingCharge : 199;
					}

				} else {
					$cartShippingCharge = 0;
				}
			}

				// assign final charge to customer cart model
			$customerCart->shipping_charge = $cartShippingCharge;



			return $cartItem;
		});

		return response()->json([//
			'success' => true,
			'data' => $transformedItems,
			'cart_id' => $cartId,
			'checkout_url' => url("/Checkout/{$cartId}"),
			'customer_cart' => $customerCart,
		]);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/frontend/cart/clear",
	 *     tags={"Frontend-Cart"},
	 *     summary="Clear entire cart",
	 *     description="Remove all items from the user's shopping cart",
	 *     security={{"bearerAuth": {}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Cart cleared successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Cart has been cleared.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Bearer token missing or invalid"
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Server error while clearing the cart"
	 *     )
	 * )
	 */
	public function clearCart(Request $request)
	{
		if (!Auth::check()) {
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized user',
			], 401);
		}

		$userId = Auth::id();
		$customerCart = CustomerCart::where('customer_id', $userId)->first();

		if (!$customerCart) {
			return response()->json([
				'success' => false,
				'message' => 'Cart was already empty or could not be cleared.',
			]);
		}

		$deleted = CustomerCartProduct::where('customer_cart_id', $customerCart->id)->delete();

		if ($deleted > 0) {
			// Update cart totals to zero
			$customerCart->update([
				'amount' => 0,
				'tax_amount' => 0,
				'total_amount' => 0,
				'total_products' => 0,
				'updated_by' => $userId,
			]);

			return response()->json([
				'success' => true,
				'message' => 'Cart cleared successfully.',
			]);
		} else {
			return response()->json([
				'success' => false,
				'message' => 'Cart was already empty or could not be cleared.',
			]);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/frontend/cart/product/{productId}",
	 *     tags={"Frontend-Cart"},
	 *     summary="Remove specific product from cart",
	 *     description="Remove a specific product from the user's shopping cart",
	 *     security={{"bearerAuth": {}}},
	 *     @OA\Parameter(
	 *         name="productId",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the product to remove",
	 *         @OA\Schema(type="integer", example=123)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product removed successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product removed from cart.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found in cart"
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Bearer token missing or invalid"
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Server error while removing product from cart"
	 *     )
	 * )
	 */
	public function clearProductFromCart(Request $request, $productId)
	{
		if (!Auth::check()) {
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized user',
			], 401);
		}

		$userId = Auth::id();
		$customerCart = CustomerCart::where('customer_id', $userId)->first();

		if (!$customerCart) {
			return response()->json(['success' => false, 'message' => 'Cart not found']);
		}

		$deleted = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
		->where('product_id', $productId)
		->delete();

		if ($deleted > 0) {
		// Update cart totals
			$this->updateCartTotals($customerCart);
		}

		return response()->json(['success' => true]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/cart/product/{productId}",
	 *     tags={"Frontend-Cart"},
	 *     summary="Remove specific product (with optional accessories) from cart",
	 *     description="Removes a specific product from the user's shopping cart. If 'accessories_options' are provided, only the item with matching accessories will be removed. If not provided, only the product with no accessories will be removed.",
	 *     security={{"bearerAuth": {}}},
	 *
	 *     @OA\Parameter(
	 *         name="productId",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the product to remove from the cart",
	 *         @OA\Schema(type="integer", example=123)
	 *     ),
	 *
	 *     @OA\RequestBody(
	 *         required=false,
	 *         description="Optional array of accessories to match a specific cart item variant",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(
	 *                 property="accessories_options",
	 *                 type="array",
	 *                 example={106,107},
	 *                 @OA\Items(type="integer")
	 *             )
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product removed successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product removed successfully")
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found in cart"
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Bearer token missing or invalid"
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=500,
	 *         description="Server error while removing product from cart"
	 *     )
	 * )
	 */
	public function clearProductFromCarts(Request $request, $productId)
	{
		try {
			if (!Auth::check()) {
				return response()->json([
					'success' => false,
					'message' => 'Unauthorized user',
				], 401);
			}

			$userId = Auth::id();
			$customerCart = CustomerCart::where('customer_id', $userId)->first();

			if (!$customerCart) {
				return response()->json([
					'success' => false,
					'message' => 'Cart not found'
				]);
			}

			$accessories = $request->input('accessories_options', []);

			$query = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
			->where('product_id', $productId);

			if (!empty($accessories)) {
				// Delete product only if it has matching accessories
				$query->whereJsonContains('accessories_options', $accessories);
			} else {
				// Delete only items with no accessories
				$query->where(function ($q) {
					$q->whereNull('accessories_options')
					->orWhere('accessories_options', '[]')
					->orWhere('accessories_options', '');
				});
			}

			$deleted = $query->delete();

			if ($deleted > 0) {
				$this->updateCartTotals($customerCart);

				return response()->json([
					'success' => true,
					'message' => 'Product removed successfully'
				]);
			}

			return response()->json([
				'success' => false,
				'message' => 'Product not found in cart'
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Server error while removing product from cart',
				'error'   => $e->getMessage(),
			]);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/cart/update-quantity",
	 *     tags={"Frontend-Cart"},
	 *     summary="Update cart item quantity",
	 *     description="Update the quantity of a specific product in the cart",
	 *     security={{"bearerAuth": {}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             type="object",
	 *             required={"productId","quantity"},
	 *             @OA\Property(property="productId", type="integer", example=123, description="ID of the product"),
	 *             @OA\Property(property="quantity", type="integer", example=3, description="New quantity for the product")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Quantity updated successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Quantity updated."),
	 *             @OA\Property(property="cart_total", type="number", format="float", example=249.99)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="Invalid input (e.g., quantity less than 1)"
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found in cart"
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Bearer token missing or invalid"
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Server error while updating cart quantity"
	 *     )
	 * )
	 */
	public function updateCartQuantity(Request $request)
	{
		$request->validate([
			'product_id'   => 'required|integer',
			'quantity'     => 'required|integer|min:1',
			'vendor_id'    => 'nullable|integer',
			'accessories_options' => 'nullable|array'
		]);

		if (!Auth::check()) {
			return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
		}

		$start = microtime(true);
		$userId = Auth::id();
		$productId = $request->input('product_id');
		$quantity = $request->input('quantity');
		$vendorId = $request->input('vendor_id');
		$accessories = $request->input('accessories_options', []);

		$customerCart = CustomerCart::where('customer_id', $userId)->first();

		if (!$customerCart) {
			return response()->json(['success' => false, 'message' => 'Cart not found']);
		}

		// Base query
		$cartProductQuery = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
		->where('product_id', $productId);

		if ($vendorId) {
			$cartProductQuery->where('vendor_id', $vendorId);
		}

		if (!empty($accessories)) {
			$accessoryIds = array_column($accessories, 'accessory_id');
			$cartProductQuery->whereJsonContains('accessories_options', $accessoryIds);
		} else {
				// No accessories
			$cartProductQuery->where(function ($q) {
				$q->whereNull('accessories_options')
				->orWhere('accessories_options', '[]')
				->orWhere('accessories_options', '');
			});
		}




		$cartProduct = $cartProductQuery->first();

		if (!$cartProduct) {
			return response()->json(['success' => false, 'message' => 'Product not found in cart']);
		}

		// Update quantity and recalc amounts
		$cartProduct->quantity = $quantity;
		$cartProduct->amount = $quantity * $cartProduct->unit_price;
		$cartProduct->total_amount = $cartProduct->amount + $cartProduct->shipping_charge;
		$cartProduct->save();

		// Update full cart totals
		$this->updateCartTotals($customerCart);

		$end = microtime(true);
		Log::info('updateCartQuantity duration: ' . round(($end - $start) * 1000) . 'ms');

		return response()->json(['success' => true]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/cart/decrease-quantity",
	 *     tags={"Frontend-Cart"},
	 *     summary="Decrease cart item quantity",
	 *     description="Decrease the quantity of a specific product in the cart. If quantity becomes 0 or less, the item will be removed from cart.",
	 *     security={{"bearerAuth": {}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             type="object",
	 *             required={"productId","quantity"},
	 *             @OA\Property(property="productId", type="integer", example=123, description="ID of the product"),
	 *             @OA\Property(property="quantity", type="integer", example=1, description="Amount to decrease (defaults to 1 if not provided)")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Quantity decreased successfully (or product removed if quantity <= 0)",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Quantity decreased."),
	 *             @OA\Property(property="cart_total", type="number", format="float", example=199.99)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="Invalid input (e.g., negative quantity)"
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found in cart"
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Bearer token missing or invalid"
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Server error while decreasing cart quantity"
	 *     )
	 * )
	 */
	public function decreaseQuantity(Request $request)
	{
		$request->validate([
			'product_id' => 'required|exists:ec_products,id',
			'quantity' => 'required|integer|min:1',
			'vendor_id' => 'nullable|integer',
		]);

		if (!Auth::check()) {
			return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
		}

		$userId = Auth::id();
		$productId = $request->input('product_id');
		$quantityToDecrease = $request->input('quantity');
		$vendorId = $request->input('vendor_id');

		$customerCart = CustomerCart::where('customer_id', $userId)->first();

		if (!$customerCart) {
			return response()->json(['success' => false, 'message' => 'Cart not found'], 404);
		}

		$cartProduct = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
		->where('product_id', $productId);

		if ($vendorId) {
			$cartProduct->where('vendor_id', $vendorId);
		}

		$cartProduct = $cartProduct->first();

		if (!$cartProduct) {
			Log::info('Cart item not found for product', [
				'product_id' => $productId,
				'user_id' => $userId
			]);
			return response()->json(['success' => false, 'message' => 'Item not found in cart.'], 404);
		}

		// Decrease quantity or remove item
		if ($quantityToDecrease >= $cartProduct->quantity) {
			$cartProduct->delete();
			$this->updateCartTotals($customerCart);
			return response()->json(['success' => true, 'message' => 'Item removed from cart.']);
		} else {
			$cartProduct->quantity -= $quantityToDecrease;
			$cartProduct->amount = $cartProduct->quantity * $cartProduct->unit_price;
			$cartProduct->total_amount = $cartProduct->amount + $cartProduct->shipping_charge;
			$cartProduct->save();

			$this->updateCartTotals($customerCart);

			return response()->json([
				'success' => true,
				'data' => [
					'id' => $cartProduct->id,
					'user_id' => $userId,
					'session_id' => null,
					'product_id' => $cartProduct->product_id,
					'quantity' => $cartProduct->quantity,
					'created_at' => $cartProduct->created_at,
					'updated_at' => $cartProduct->updated_at,
				],
			]);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/cart-summary",
	 *     tags={"Frontend-Cart"},
	 *     summary="Get cart summary for the authenticated user",
	 *     description="Returns subtotal, tax, total, shipping rate, and other summary details for the cart.",
	 *     security={{"bearerAuth": {}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Cart summary retrieved successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="subtotal", type="number", format="float", example=199.99),
	 *             @OA\Property(property="tax", type="number", format="float", example=10.00),
	 *             @OA\Property(property="shipping_rate", type="number", format="float", example=5.00),
	 *             @OA\Property(property="discount", type="number", format="float", example=20.00),
	 *             @OA\Property(property="total", type="number", format="float", example=194.99),
	 *             @OA\Property(
	 *                 property="currency",
	 *                 type="string",
	 *                 example="USD",
	 *                 description="Currency code for the cart summary"
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Bearer token missing or invalid"
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Server error while retrieving cart summary"
	 *     )
	 * )
	 */
	public function cartSummary(Request $request)
	{
		if (!Auth::check()) {
			return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
		}

		$userId = Auth::id();
		$customerCart = CustomerCart::where('customer_id', $userId)->first();

		if (!$customerCart) {
			return response()->json([
				'subtotal' => 0,
				'tax' => 0,
				'total_with_tax' => 0,
				'savings' => 0,
				'item_count' => 0,
				'currency_title' => 'USD',
			]);
		}

		// Get first product's currency for display
		$firstCartProduct = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
		->with('product.currency')
		->first();

		$currencyTitle = $firstCartProduct->product->currency->symbol ?? 'USD';

		return response()->json([
			'subtotal' => (float) $customerCart->amount,
			'tax' => (float) $customerCart->tax_amount,
			'total_with_tax' => (float) $customerCart->total_amount,
										'savings' => 0, // Calculate if needed
										'item_count' => (int) $customerCart->total_products,
										'currency_title' => $currencyTitle,
									]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/cart/total-products",
	 *     tags={"Frontend-Cart"},
	 *     summary="Get total quantity of products in cart for authenticated user",
	 *     description="Returns the total number of items in the cart for a logged-in user.",
	 *     security={{"bearerAuth": {}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Total products retrieved successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="total_products", type="integer", example=5, description="Total quantity of items in the cart")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Bearer token missing or invalid"
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Server error while retrieving total products in cart"
	 *     )
	 * )
	 */
	public function totalProductsInCart(Request $request)
	{
		if (!Auth::check()) {
			return response()->json(['total' => 0]);
		}

		$userId = Auth::id();
		$customerCart = CustomerCart::where('customer_id', $userId)->first();

		if (!$customerCart) {
			return response()->json(['total' => 0]);
		}

		$totalQuantity = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
		->sum('quantity');

		return response()->json(['total' => (int) $totalQuantity]);
	}

	/**
	 * Helper method to generate reference number
	 */
	private function generateReferenceNumber()
	{
		return time() . rand(1000, 9999);
	}

	/**
	 * Helper method to generate cart ID
	 */
	private function generateSimpleCartId($userId)
	{
		$timestamp = time();
		$random = uniqid();

		if ($userId) {
			return "user_{$userId}_{$timestamp}_{$random}";
		} else {
			return "guest_{$timestamp}_{$random}";
		}
	}

	/**
	 * Helper method to update cart totals
	 */
	private function updateCartTotals(CustomerCart $customerCart)
	{
		$cartProducts = CustomerCartProduct::where('customer_cart_id', $customerCart->id)->get();

		$totalAmount = $cartProducts->sum('amount');
		$totalQuantity = $cartProducts->sum('quantity');
		$taxAmount = $totalAmount * ($customerCart->tax_percentage / 100);
		$finalTotal = $totalAmount + $taxAmount + $customerCart->shipping_charge;

		$customerCart->update([
			'amount' => $totalAmount,
			'tax_amount' => $taxAmount,
			'total_amount' => $finalTotal,
			'total_products' => $totalQuantity,
			'updated_by' => Auth::id(),
		]);
	}

	/**
	 * Add multiple products to the cart.
	 *
	 * @OA\Post(
	 *     path="/api/frontend/cart/add-multiple",
	 *     tags={"Frontend-Cart"},
	 *     summary="Add multiple products to cart",
	 *     description="Adds an array of products with quantity to the cart for the authenticated user.",
	 *     operationId="addMultipleToCart",
	 *     security={{"bearerAuth": {}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"products"},
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     required={"product_id", "quantity"},
	 *                     @OA\Property(property="product_id", type="integer", example=1, description="ID of the product"),
	 *                     @OA\Property(property="quantity", type="integer", example=2, description="Quantity to add"),
	 *                     @OA\Property(property="vendor_id", type="integer", nullable=true, example=3, description="Optional vendor ID for supplier pricing")
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Products added to cart",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Products added to cart"),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(property="user_id", type="integer", example=10),
	 *                     @OA\Property(property="product_id", type="integer", example=1),
	 *                     @OA\Property(property="quantity", type="integer", example=2),
	 *                     @OA\Property(property="currency_id", type="integer", example=1),
	 *                     @OA\Property(property="currency_title", type="string", example="$")
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized user",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Unauthorized user")
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
	 *                 example={"products.0.product_id": {"The product_id field is required."}}
	 *             )
	 *         )
	 *     )
	 * )
	 */
	public function addMultipleToCart(Request $request)
	{
		$request->validate([
			'products' => 'required|array',
			'products.*.product_id' => 'required|exists:ec_products,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.vendor_id' => 'nullable|exists:vendors,id',
			'products.*.accessories_options' => 'nullable|array',
			'products.*.accessories_options.*' => 'integer|exists:accessory_items,id',
		]);

		if (!Auth::check()) {
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized user',
			], 401);
		}

		$userId = Auth::id();

		$customerCart = CustomerCart::firstOrCreate(
			['customer_id' => $userId],
			[
				'reference_number' => $this->generateReferenceNumber(),
				'customer_address_id' => 0,
				'shipping_charge' => 0,
				'is_lift_gate' => 0,
				'is_residential_address' => 1,
				'is_inside_delivery' => 0,
				'amount' => 0,
				'tax_percentage' => 0,
				'tax_amount' => 0,
				'total_amount' => 0,
				'total_products' => 0,
				'created_by' => 0,
			]
		);

		$addedProducts = [];

		// Preload all accessory items at once
		$allAccessoryIds = collect($request->products)->pluck('accessories_options')->flatten()->unique()->toArray();
		$accessoryItems = AccessoryItem::whereIn('id', $allAccessoryIds)->get()->keyBy('id');

		foreach ($request->products as $item) {
			$productId = $item['product_id'];
			$quantity = $item['quantity'];
			$vendorId = $item['vendor_id'] ?? null;
			$selectedOptions = $item['accessories_options'] ?? [];

			sort($selectedOptions);

			$product = Product::with('currency', 'productSuppliers')->find($productId);
			if (!$product) continue;

			$supplier = $vendorId
			? $product->productSuppliers->where('vendor_id', $vendorId)->first()
			: $product->productSuppliers->first();

			if (!$supplier) continue;

			$unitPrice = $supplier->sale_price ?: $supplier->price;
			$actualVendorId = $supplier->vendor_id;

			// Calculate accessory options price
			$optionPrice = 0;
			foreach ($selectedOptions as $itemId) {
				if (isset($accessoryItems[$itemId])) {
					$optionPrice += $accessoryItems[$itemId]->price ?? 0;
				}
			}

			$totalUnitPrice = $unitPrice + $optionPrice;
			$amount = $quantity * $totalUnitPrice;

			// Check if same product with same options exists in cart
			sort($selectedOptions);
			$cartProduct = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
			->where('product_id', $productId)
			->where('vendor_id', $actualVendorId)
			->get()
			->first(function ($cart) use ($selectedOptions) {
				$cartOptions = $cart->accessories_options ?? [];
				sort($cartOptions);
				return $cartOptions === $selectedOptions;
			});


			if ($cartProduct) {
				$cartProduct->quantity += $quantity;
				$cartProduct->amount = $cartProduct->quantity * $totalUnitPrice;
				$cartProduct->total_amount = $cartProduct->amount + $cartProduct->shipping_charge;
				$cartProduct->save();
			} else {
				$cartProduct = CustomerCartProduct::create([
					'customer_cart_id' => $customerCart->id,
					'product_id' => $productId,
					'vendor_id' => $actualVendorId,
					'quantity' => $quantity,
					'unit_price' => $unitPrice,
					'accessories_options' => $selectedOptions,
					'amount' => $amount,
					'shipping_charge' => 0,
					'total_amount' => $amount,
				]);
			}

			$addedProducts[] = [
				'id' => $cartProduct->id,
				'user_id' => $userId,
				'product_id' => $cartProduct->product_id,
				'quantity' => $cartProduct->quantity,
				'currency_id' => $product->currency->id,
				'currency_title' => $product->currency->symbol,
				'accessories_options' => $cartProduct->accessories_options,
				'unit_price' => $totalUnitPrice,
				'total_amount' => $cartProduct->total_amount,
			];
		}

		$this->updateCartTotals($customerCart);

		return response()->json([
			'success' => true,
			'message' => 'Products added to cart',
			'data' => $addedProducts,
		]);
	}
}