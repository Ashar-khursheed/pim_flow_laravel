<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\FrontEnd\AccessoryCharge;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\FrontEnd\CustomerCart;
use App\Models\FrontEnd\CustomerCartProduct;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\Customer;
use App\Jobs\Order\CartCreationMailJob;

class CustomerCartController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/carts",
	 *     summary="Get authenticated customer's cart",
	 *     tags={"Frontend-Carts"},
	 *     @OA\Parameter(
	 *         name="country",
	 *         in="query",
	 *         description="Country name",
	 *         required=false,
	 *         @OA\Schema(type="string", example="United States")
	 *     ),
	 *     @OA\Response(response=200, description="Cart retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		/* Validate country parameter if provided */
		$request->validate([
			'country' => 'nullable|string|exists:countries,name',
		]);

		/* Get authenticated customer's cart */
		$cart = CustomerCart::with([
			'customerCartProducts:id,customer_cart_id,product_id,vendor_id,quantity,unit_price,amount,accessory_item_charge,shipping_charge,total_amount',
			'customerCartProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
			'customerCartProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
			'customerCartProducts.accessoryCharges.accessoryItem.accessory:id,name',
			'customerCartProducts.product:id,name,images,sku,barcode',
			'customerCartProducts.product.seoUrl:id,relational_id,relational_type,url',
			'customerCartProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
		])
		->where('customer_id', auth()->id())
		->first();

		if (!$cart) {
			return response()->json([
				'success' => true,
				'message' => 'No cart found',
				'data' => null
			], 200);
		}

		/* Get country and currency based on query parameter or default address */
		if ($request->filled('country')) {
			/* Use country from query parameter */
			$country = Country::with('currency:id,symbol')
			->where('name', $request->country)
			->first(['id', 'name', 'currency_id', 'margin']);

			$margin = $country->margin ?? 0;
			$currency = $country->currency ?? null;
		} else {
			/* Use default customer address */
			$defaultAddress = auth()->user()->customerAddress()
			->with('relatedCountry.currency:id,symbol')
			->where('is_default', 1)
			->first(['id', 'country']);

			$countryData = $defaultAddress->relatedCountry ?? null;
			$margin = $countryData->margin ?? 0;
			$currency = $countryData->currency ?? null;
		}

		$cartSubtotal = 0;
		$cartTotalShipping = 0;
		$cartTotalAccessories = 0;

		/* Process each cart product */
		foreach ($cart->customerCartProducts as $cartProduct) {
			$product = $cartProduct->product;

			if ($product) {
				/* Parse and set product data */
				$images = is_array($product->images)
				? $product->images
				: (json_decode($product->images, true) ?: []);

				$product->image = $images[0] ?? null;
				$product->currency_symbol = $currency->symbol ?? null;
				$product->category_url = $product->category_url() ?? null;
				$product->parent_category_url = $product->parent_category_url() ?? null;
				$product->url = $product->seoUrl->url ?? null;

				/* Extract selling unit type */
				$fullValue = $product->sellingUnitAttribute->attribute_value ?? '';
				$product->selling_type = $fullValue && strpos($fullValue, '/') !== false
				? trim(explode('/', $fullValue)[1])
				: trim($fullValue);

				/* Remove unnecessary fields */
				unset($product->images, $product->seoUrl, $product->categories, $product->sellingUnitAttribute);
			}

			/* Get vendor supplier and calculate prices */
			$supplier = optional($cartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);

			if ($supplier && isset($supplier['price'])) {
				/* Calculate base unit price (use sale price if available and lower) */
				$baseUnitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price'])
				? $supplier['sale_price']
				: $supplier['price'];

				/* Apply margin based on website */
				$unitPrice = $baseUnitPrice + (in_array(config('app.website'), ['UAE', 'UAE_T'])
					? ($baseUnitPrice * ($margin / 100))
					: 0);

				$amount = $cartProduct->quantity * $unitPrice;
				$shippingCharge = $supplier['shipping_charge'] ?? 0;
				$accessoryCharge = $cartProduct->accessory_item_charge ?? 0;

				/* Update cart product */
				$cartProduct->unit_price = number_format($unitPrice, 2, '.', '');
				$cartProduct->amount = number_format($amount, 2, '.', '');
				$cartProduct->shipping_charge = number_format($shippingCharge, 2, '.', '');
				$cartProduct->accessory_item_charge = number_format($accessoryCharge, 2, '.', '');
				$cartProduct->total_amount = number_format($amount + $shippingCharge + $accessoryCharge, 2, '.', '');

				/* Accumulate totals */
				$cartSubtotal += $amount;
				$cartTotalShipping += $shippingCharge;
				$cartTotalAccessories += $accessoryCharge;
			} else {
				/* Use stored values */
				$cartSubtotal += $cartProduct->amount;
				$cartTotalShipping += $cartProduct->shipping_charge;
				$cartTotalAccessories += $cartProduct->accessory_item_charge ?? 0;

				/* Format stored values */
				$cartProduct->unit_price = number_format($cartProduct->unit_price, 2, '.', '');
				$cartProduct->amount = number_format($cartProduct->amount, 2, '.', '');
				$cartProduct->shipping_charge = number_format($cartProduct->shipping_charge, 2, '.', '');
				$cartProduct->accessory_item_charge = number_format($cartProduct->accessory_item_charge ?? 0, 2, '.', '');
				$cartProduct->total_amount = number_format($cartProduct->total_amount, 2, '.', '');
			}

			/* Format accessory charges */
			$cartProduct->accessory_charges = $cartProduct->accessoryCharges->map(function ($charge) {
				return [
					'id' => $charge->id,
					'accessory_item_id' => $charge->accessory_item_id,
					'accessory_item_name' => $charge->accessoryItem->name ?? null,
					'accessory_item_price' => number_format($charge->accessoryItem->price ?? 0, 2, '.', ''),
					'product_accessory_id' => $charge->accessoryItem->product_accessory_id ?? null,
					'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
					'amount' => number_format($charge->amount, 2, '.', ''),
				];
			})->toArray();

			/* Remove unnecessary cart product fields */
			unset(
				$cartProduct->accessoryCharges,
				$cartProduct->customer_cart_id,
				$cartProduct->product_id,
				$cartProduct->vendor_id
			);
		}

		/* Calculate cart totals */
		$liftGateCharge = $cart->is_lift_gate ? 75 : 0;
		$residentialCharge = $cart->is_residential_address ? 199 : 0;
		$insideDeliveryCharge = $cart->is_inside_delivery ? 249 : 0;
		$additionalAmount = $cart->additional_amount_price ?? 0;

		$amountBeforeTax = $cartSubtotal + $cartTotalShipping + $cartTotalAccessories + $liftGateCharge + $residentialCharge + $insideDeliveryCharge + $additionalAmount;
		$taxPercentage = $cart->tax_percentage ?? 0;
		$taxAmount = ($amountBeforeTax * $taxPercentage) / 100;
		$totalAmount = $amountBeforeTax + $taxAmount;

		/* Update cart with calculated values */
		$cart->amount = number_format($cartSubtotal, 2, '.', '');
		$cart->shipping_charge = number_format($cartTotalShipping, 2, '.', '');
		$cart->tax_percentage = number_format($taxPercentage, 4, '.', '');
		$cart->tax_amount = number_format($taxAmount, 2, '.', '');
		$cart->total_amount = number_format($totalAmount, 2, '.', '');
		$cart->additional_amount_price = number_format($additionalAmount, 2, '.', '');

		/* Remove unnecessary cart fields */
		unset(
			$cart->reference_number,
			$cart->created_by,
			$cart->updated_by,
			$cart->created_at,
			$cart->updated_at
		);

		return response()->json([
			'success' => true,
			'message' => 'Cart retrieved successfully',
			'data' => $cart
		], 200);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/carts/add",
	 *     summary="Add product to cart (create or update)",
	 *     tags={"Frontend-Carts"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id", "vendor_id", "quantity", "shipping_charge"},
	 *             @OA\Property(property="country", type="string", example="United States", description="Country name"),
	 *             @OA\Property(property="product_id", type="integer", example=101, description="Product ID"),
	 *             @OA\Property(property="vendor_id", type="integer", example=22, description="Vendor ID"),
	 *             @OA\Property(property="quantity", type="integer", example=5, description="Product quantity"),
	 *             @OA\Property(property="shipping_charge", type="number", example=50.00, description="Product Shipping Charge"),
	 *             @OA\Property(
	 *                 property="accessory_item_ids",
	 *                 type="array",
	 *                 description="Array of accessory item IDs",
	 *                 @OA\Items(type="integer", example=50)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Product added to cart successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function addToCart(Request $request)
	{
		/* Validate request */
		$request->validate([
			'country' => 'nullable|string|exists:countries,name',
			'product_id' => 'required|integer|exists:ec_products,id',
			'vendor_id' => 'required|integer|exists:vendors,id',
			'quantity' => 'required|integer|min:1',
			'shipping_charge' => 'required|numeric|min:0',
			'accessory_item_ids' => 'nullable|array',
			'accessory_item_ids.*' => 'integer|exists:accessory_items,id',
		]);

		$customerId = auth()->id();

		/* Get customer and default address */
		$customer = Customer::find($customerId);

		/* Get country and currency based on query parameter or default address */
		if ($request->filled('country')) {
			/* Use country from query parameter */
			$country = Country::with('currency:id,symbol')
			->where('name', $request->country)
			->first(['id', 'name', 'currency_id', 'margin']);

			$margin = $country->margin ?? 0;
		} else {
			/* Use default customer address */
			$defaultAddress = auth()->user()->customerAddress()
			->with('relatedCountry.currency:id,symbol')
			->where('is_default', 1)
			->first(['id', 'country']);

			$countryData = $defaultAddress->relatedCountry ?? null;
			$margin = $countryData->margin ?? 0;
		}

		/* Prepare product data for calculation */
		$productData = [[
			'product_id' => $request->product_id,
			'vendor_id' => $request->vendor_id,
			'quantity' => $request->quantity,
			'shipping_charge' => $request->shipping_charge,
			'accessory_item_ids' => $request->accessory_item_ids ?? [],
		]];

		/* Create temporary request for calculation */
		$tempRequest = new Request();
		$tempRequest->merge([
			'products' => $productData,
			'tax_percentage' => 0,
		]);

		/* Calculate amounts */
		$amountCalculations = $this->calculateAmount($tempRequest, $customer->is_tax_free, margin: $margin);

		DB::beginTransaction();

		try {
			/* Check if customer already has a cart */
			$customerCart = CustomerCart::where('customer_id', $customerId)->first();

			/* Prepare cart data */
			$cartData = [
				'customer_address_id' => $defaultAddress->id,
				'amount' => $amountCalculations['subtotal'],
				'shipping_charge' => $amountCalculations['shipping_charge'],
				'tax_percentage' => $amountCalculations['tax_percentage'],
				'tax_amount' => $amountCalculations['tax_amount'],
				'total_amount' => $amountCalculations['grand_total'],
				'total_products' => $amountCalculations['total_products'],
				'updated_by' => $customerId,
			];

			if ($customerCart) {
				/* Update existing cart */
				$customerCart->update($cartData);
				$message = 'Product added to cart successfully';
			} else {
				/* Generate reference number for new cart */
				$latestReferenceNumber = CustomerCart::orderBy('id', 'desc')->value('reference_number');

				$referenceNumber = $latestReferenceNumber && is_numeric($latestReferenceNumber)
				? (int) $latestReferenceNumber + 1
				: (in_array(config('app.website'), ['US', 'US_T']) ? 10001 : (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101));

				/* Create new cart */
				$customerCart = CustomerCart::create(array_merge($cartData, [
					'customer_id' => $customerId,
					'reference_number' => $referenceNumber,
					'created_by' => $customerId,
				]));

				$message = 'Cart created and product added successfully';
			}

			/* Check if product already exists in cart */
			$existingCartProduct = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
			->where('product_id', $request->product_id)
			->where('vendor_id', $request->vendor_id)
			->first();

			$productDetails = $amountCalculations['product_details'][0];
			$productAmount = $productDetails['quantity'] * $productDetails['unit_price'];

			if ($existingCartProduct) {
				/* Update existing product - increase quantity */
				$newQuantity = $existingCartProduct->quantity + $request->quantity;
				$newAmount = $newQuantity * $productDetails['unit_price'];

				/* Delete old accessory charges */
				AccessoryCharge::where('relation_type', CustomerCartProduct::class)
				->where('relation_id', $existingCartProduct->id)
				->delete();

				/* Update cart product */
				$existingCartProduct->update([
					'quantity' => $newQuantity,
					'amount' => $newAmount,
					'accessory_item_charge' => $productDetails['accessory_item_charge'],
					'shipping_charge' => $productDetails['shipping_charge'],
					'total_amount' => $newAmount + $productDetails['shipping_charge'] + $productDetails['accessory_item_charge'],
				]);

				$cartProduct = $existingCartProduct;
			} else {
				/* Add new product to cart */
				$cartProduct = CustomerCartProduct::create([
					'customer_cart_id' => $customerCart->id,
					'product_id' => $productDetails['product_id'],
					'vendor_id' => $productDetails['vendor_id'],
					'quantity' => $productDetails['quantity'],
					'unit_price' => $productDetails['unit_price'],
					'amount' => $productAmount,
					'accessory_item_charge' => $productDetails['accessory_item_charge'],
					'shipping_charge' => $productDetails['shipping_charge'],
					'total_amount' => $productAmount + $productDetails['shipping_charge'] + $productDetails['accessory_item_charge'],
				]);
			}

			/* Save accessory charges if present */
			if (!empty($productDetails['accessoryItems'])) {
				foreach ($productDetails['accessoryItems'] as $accessoryItem) {
					$cartProduct->accessoryCharges()->create([
						'accessory_item_id' => $accessoryItem['id'],
						'amount' => $accessoryItem['price'] * $productDetails['quantity'],
					]);
				}
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => $message,
				'data' => [
					'cart_id' => $customerCart->id,
					'total_products' => $customerCart->total_products,
					'total_amount' => number_format($customerCart->total_amount, 2, '.', ''),
				]
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to add product to cart: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Put(
	 *     path="/api/frontend/carts/update-quantity/{cart_product_id}",
	 *     summary="Update cart product quantity",
	 *     tags={"Frontend-Carts"},
	 *     @OA\Parameter(
	 *         name="cart_product_id",
	 *         in="path",
	 *         description="Cart Product ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"quantity"},
	 *             @OA\Property(property="quantity", type="integer", example=3, description="New quantity")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Quantity updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function updateQuantity(Request $request, $cart_product_id)
	{
		/* Validate request */
		$request->validate([
			'quantity' => 'required|integer|min:1',
		]);

		$customerId = auth()->id();

		/* Get cart product and verify ownership */
		$cartProduct = CustomerCartProduct::with('customerCart')
		->whereHas('customerCart', function($query) use ($customerId) {
			$query->where('customer_id', $customerId);
		})
		->find($cart_product_id);

		if (!$cartProduct) {
			return response()->json([
				'success' => false,
				'message' => 'Cart product not found or does not belong to you.'
			], 404);
		}

		$customerCart = $cartProduct->customerCart;
		$customer = Customer::find($customerId);

		/* Get margin from cart address */
		$address = CustomerAddress::with('relatedCountry:id,name,margin')
		->find($customerCart->customer_address_id);

		$margin = $address->relatedCountry->margin ?? 0;

		/* Get product supplier for price calculation */
		$supplier = ProductSupplier::where('product_id', $cartProduct->product_id)
		->where('vendor_id', $cartProduct->vendor_id)
		->first(['price', 'sale_price', 'shipping_charge']);

		if (!$supplier) {
			return response()->json([
				'success' => false,
				'message' => 'Product supplier not found.'
			], 404);
		}

		/* Calculate unit price with margin */
		$baseUnitPrice = ($supplier->sale_price > 0 && $supplier->sale_price < $supplier->price)
		? $supplier->sale_price
		: $supplier->price;

		$unitPrice = $baseUnitPrice + (in_array(config('app.website'), ['UAE', 'UAE_T'])
			? ($baseUnitPrice * ($margin / 100))
			: 0);

		DB::beginTransaction();

		try {
			/* Recalculate accessory charges based on new quantity */
			$accessoryCharges = AccessoryCharge::where('relation_type', CustomerCartProduct::class)
			->where('relation_id', $cartProduct->id)
			->with('accessoryItem:id,price')
			->get();

			$totalAccessoryCharge = 0;

			foreach ($accessoryCharges as $charge) {
				$newAmount = ($charge->accessoryItem->price ?? 0) * $request->quantity;
				$charge->update(['amount' => $newAmount]);
				$totalAccessoryCharge += $newAmount;
			}

			/* Update cart product */
			$amount = $request->quantity * $unitPrice;
			$shippingCharge = $supplier->shipping_charge ?? 0;
			$totalAmount = $amount + $shippingCharge + $totalAccessoryCharge;

			$cartProduct->update([
				'quantity' => $request->quantity,
				'unit_price' => $unitPrice,
				'amount' => $amount,
				'accessory_item_charge' => $totalAccessoryCharge,
				'shipping_charge' => $shippingCharge,
				'total_amount' => $totalAmount,
			]);

			/* Recalculate cart totals */
			$this->recalculateCartTotals($customerCart->id, $customer->is_tax_free);

			DB::commit();

			/* Get updated cart */
			$updatedCart = CustomerCart::find($customerCart->id);

			return response()->json([
				'success' => true,
				'message' => 'Quantity updated successfully',
				'data' => [
					'cart_product_id' => $cartProduct->id,
					'quantity' => $cartProduct->quantity,
					'unit_price' => number_format($cartProduct->unit_price, 2, '.', ''),
					'amount' => number_format($cartProduct->amount, 2, '.', ''),
					'total_amount' => number_format($cartProduct->total_amount, 2, '.', ''),
					'cart_total' => number_format($updatedCart->total_amount, 2, '.', ''),
				]
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to update quantity: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Recalculate cart totals after product changes
	 *
	 * @param int $cartId
	 * @param bool $isTaxFree
	 * @return void
	 */
	private function recalculateCartTotals($cartId, $isTaxFree = false)
	{
		$cart = CustomerCart::with('customerCartProducts')->find($cartId);

		/* Calculate totals */
		$subtotal = $cart->customerCartProducts->sum('amount');
		$totalShipping = $cart->customerCartProducts->sum('shipping_charge');
		$totalAccessories = $cart->customerCartProducts->sum('accessory_item_charge');
		$totalProducts = $cart->customerCartProducts->count();

		/* Calculate charges */
		$liftGateCharge = $cart->is_lift_gate ? 75 : 0;
		$residentialCharge = $cart->is_residential_address ? 199 : 0;
		$insideDeliveryCharge = $cart->is_inside_delivery ? 249 : 0;
		$additionalAmount = $cart->additional_amount_price ?? 0;

		/* Calculate amount before tax */
		$amountBeforeTax = $subtotal + $totalShipping + $totalAccessories + $liftGateCharge + $residentialCharge + $insideDeliveryCharge + $additionalAmount;

		/* Calculate tax */
		$taxPercentage = $isTaxFree ? 0 : ($cart->tax_percentage ?? 0);
		$taxAmount = ($amountBeforeTax * $taxPercentage) / 100;

		/* Calculate final total */
		$totalAmount = $amountBeforeTax + $taxAmount;

		/* Update cart */
		$cart->update([
			'amount' => $subtotal,
			'shipping_charge' => $totalShipping,
			'tax_percentage' => $taxPercentage,
			'tax_amount' => $taxAmount,
			'total_amount' => $totalAmount,
			'total_products' => $totalProducts,
			'updated_by' => auth()->id(),
		]);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/frontend/carts/remove/{cart_product_id}",
	 *     summary="Remove product from cart (deletes cart if empty)",
	 *     tags={"Frontend-Carts"},
	 *     @OA\Parameter(
	 *         name="cart_product_id",
	 *         in="path",
	 *         description="Cart Product ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Product removed successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function removeProduct($cart_product_id)
	{
		$customerId = auth()->id();

		/* Get cart product and verify ownership */
		$cartProduct = CustomerCartProduct::with('customerCart')
		->whereHas('customerCart', function($query) use ($customerId) {
			$query->where('customer_id', $customerId);
		})
		->find($cart_product_id);

		if (!$cartProduct) {
			return response()->json([
				'success' => false,
				'message' => 'Cart product not found.'
			], 404);
		}

		$cartId = $cartProduct->customer_cart_id;

		DB::beginTransaction();

		try {
			/* Delete accessory charges first */
			AccessoryCharge::where('relation_type', CustomerCartProduct::class)
			->where('relation_id', $cartProduct->id)
			->delete();

			/* Delete cart product */
			$cartProduct->delete();

			/* Check if cart is now empty */
			$remainingProducts = CustomerCartProduct::where('customer_cart_id', $cartId)->count();

			if ($remainingProducts === 0) {
				/* No products left - delete the cart */
				CustomerCart::find($cartId)->delete();

				DB::commit();

				return response()->json([
					'success' => true,
					'message' => 'Product removed and cart deleted (no products left)',
					'data' => [
						'cart_exists' => false,
						'remaining_products' => 0,
						'cart_total' => '0.00'
					]
				], 200);
			}

			/* Cart still has products - recalculate totals */
			$customer = Customer::find($customerId);
			$this->recalculateCartTotals($cartId, $customer->is_tax_free);

			DB::commit();

			/* Get updated cart */
			$updatedCart = CustomerCart::find($cartId);

			return response()->json([
				'success' => true,
				'message' => 'Product removed successfully',
				'data' => [
					'cart_exists' => true,
					'remaining_products' => $updatedCart->total_products,
					'cart_total' => number_format($updatedCart->total_amount, 2, '.', '')
				]
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to remove product: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/frontend/carts/empty",
	 *     summary="Empty cart - Remove all products and delete cart",
	 *     tags={"Frontend-Carts"},
	 *     @OA\Response(response=200, description="Cart emptied successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function emptyCart()
	{
		$customerId = auth()->id();

		/* Get customer's cart */
		$cart = CustomerCart::where('customer_id', $customerId)->first();

		if (!$cart) {
			return response()->json([
				'success' => false,
				'message' => 'No cart found.'
			], 404);
		}

		DB::beginTransaction();

		try {
			/* Get all cart product IDs */
			$cartProductIds = CustomerCartProduct::where('customer_cart_id', $cart->id)
			->pluck('id')
			->toArray();

			if (!empty($cartProductIds)) {
				/* Delete all accessory charges */
				AccessoryCharge::where('relation_type', CustomerCartProduct::class)
				->whereIn('relation_id', $cartProductIds)
				->delete();
			}

			/* Delete all cart products */
			CustomerCartProduct::where('customer_cart_id', $cart->id)->delete();

			/* Delete the cart */
			$cart->delete();

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Cart emptied successfully'
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to empty cart: ' . $e->getMessage()
			], 500);
		}
	}
}
