<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use App\Models\ProductSupplier;
use App\Models\FrontEnd\AccessoryCharge;
use App\Models\FrontEnd\CustomerCart;
use App\Models\FrontEnd\CustomerCartProduct;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\Customer;
use App\Jobs\Order\CartCreationMailJob;

use App\Traits\CalculationTrait;

class CustomerCartController extends Controller
{
	use CalculationTrait;
	/**
	 * @OA\Get(
	 *     path="/api/carts",
	 *     summary="Get all carts with pagination and filters",
	 *     tags={"Carts"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="from_admin_panel", in="query", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Parameter(name="only_total_amount", in="query", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Parameter(name="only_id", in="query", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Parameter(name="username", in="query", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "reference_number", "shipping_charge", "total_amount", "total_products", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'reference_number'];
		$sortableColumns = array_merge($searchableColumns, ['shipping_charge', 'total_amount', 'total_products', 'created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = CustomerCart::query();

		/* Eager load relationships */
		$recordsQuery->with([
			'customer:id,name,email,country_code,mobile_number',
			'customerAddress:id,address,city,country',
			'customerCartProducts:id,customer_cart_id,product_id,vendor_id,quantity',
			'customerCartProducts.product:id,name,images,sku,barcode',
			'creator:id,username',
		]);

		/* Date range filter */
		if ($request->has('from_date') && $request->has('to_date')) {
			$recordsQuery->whereBetween('customer_carts.created_at', [
				$request->from_date . ' 00:00:00',
				$request->to_date . ' 23:59:59',
			]);
		} elseif ($request->has('from_date')) {
			$recordsQuery->where('customer_carts.created_at', '>=', $request->from_date . ' 00:00:00');
		} elseif ($request->has('to_date')) {
			$recordsQuery->where('customer_carts.created_at', '<=', $request->to_date . ' 23:59:59');
		}

		/* Global search */
		if ($request->filled('global')) {
			$search = $request->input('global');
			$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
				foreach ($searchableColumns as $col) {
					$q->orWhere("customer_carts.$col", 'like', '%' . $search . '%');
				}
				$q->orWhereHas('customer', function ($sub) use ($search) {
					$sub->where('name', 'like', '%' . $search . '%')
					->orWhere('email', 'like', '%' . $search . '%');
				});
			});
		}

		/* Admin panel filter */
		if ($request->has('from_admin_panel') && $request->boolean('from_admin_panel')) {
			$recordsQuery->where('customer_carts.created_by', '>', 0);
		}

		/* Creator username filter */
		if ($request->filled('username')) {
			$username = $request->input('username');
			$recordsQuery->orWhereHas('creator', function ($sub) use ($username) {
				$sub->where('username', $username);
			});
		}

		/* Sorting */
		$recordsQuery->orderBy($sortBy, $sortDir);

		$onlyTotalAmount = $request->has('only_total_amount') && $request->boolean('only_total_amount');
		$onlyID = $request->has('only_id') && $request->boolean('only_id');

		$totalRecords = (clone $recordsQuery)->count();
		$totalPages = 1;

		/* Pagination setup */
		if ($request->filled('page') && $request->filled('length')) {
			$length = (int) $request->input('length');
			$page = (int) $request->input('page');
			$totalPages = (int) ceil($totalRecords / $length);
			$page = ($page > $totalPages && $totalPages > 0) ? 1 : $page;
		} else {
			$page = 1;
			$length = $totalRecords;
		}

		/* Return only total amount sum */
		if ($onlyTotalAmount) {
			$records = (clone $recordsQuery)->sum('total_amount');

			return response()->json([
				'success' => true,
				'message' => 'Total amount',
				'data' => $records,
				'total_pages' => $totalPages,
				'total_records' => $totalRecords,
			]);
		}

		/* Return only IDs */
		if ($onlyID) {
			$records = (clone $recordsQuery)->pluck('id');

			return response()->json([
				'success' => true,
				'message' => __('msg_rec_list'),
				'data' => $records,
				'total_pages' => $totalPages,
				'total_records' => $totalRecords,
			]);
		}

		/* Fetch paginated records */
		$records = $recordsQuery
		->offset(($page - 1) * $length)
		->limit($length)
		->get(['id', 'reference_number', 'customer_id', 'customer_address_id', 'is_lift_gate', 'is_residential_address', 'is_inside_delivery', 'tax_percentage', 'total_amount', 'total_products', 'created_by', 'created_at']);

		/* Batch-fetch vendor product suppliers to avoid N+1 — not a relation, so with() cannot be used */
		$allCartProducts = $records->flatMap(fn($cart) => $cart->customerCartProducts);

		if ($allCartProducts->isNotEmpty()) {
			$vendorSuppliers = ProductSupplier::where(function ($query) use ($allCartProducts) {
				foreach ($allCartProducts as $cartProduct) {
					$query->orWhere(function ($q) use ($cartProduct) {
						$q->where('product_id', $cartProduct->product_id)
						->where('vendor_id', $cartProduct->vendor_id);
					});
				}
			})
			->select('id', 'product_id', 'vendor_id', 'price', 'sale_price', 'shipping_charge')
			->get()
			->keyBy(fn($item) => $item->product_id . '_' . $item->vendor_id);

			/* Attach supplier as dynamic attribute on each cart product */
			foreach ($allCartProducts as $cartProduct) {
				$key = $cartProduct->product_id . '_' . $cartProduct->vendor_id;
				$cartProduct->vendor_product_supplier = $vendorSuppliers->get($key);
			}
		}

		$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']);

		/* Transform records — mutate model directly to preserve relation serialization */
		$records->transform(function ($record) use ($isUAE) {
			$totalProducts = 0;
			$cartAmount = 0;
			$cartShipping = 0;

			/* Map cart products with already-attached vendor_product_supplier attribute */
			$record->products = $record->customerCartProducts->map(function ($cartProduct) use (&$totalProducts, &$cartAmount, &$cartShipping) {
				$product = $cartProduct->product;
				if (!$product) return null;

				/* Decode images */
				$images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);

				$supplier = optional($cartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge']);

				$unitPrice = 0;
				$shippingCharge = 0;

				if ($supplier) {
					$unitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price']) ? $supplier['sale_price'] : $supplier['price'];
					$shippingCharge = $supplier['shipping_charge'] ?? 0;
				}

				$quantity = $cartProduct->quantity ?? 0;
				$subTotal = $quantity * $unitPrice;

				$totalProducts += $quantity;
				$cartAmount += $subTotal;
				$cartShipping += $shippingCharge;

				return [
					'product_id'      => $cartProduct->product_id,
					'vendor_id'       => $cartProduct->vendor_id,
					'image'           => $images[0] ?? null,
					'name'            => $product->name,
					'unit_price'      => number_format($unitPrice, 2, '.', ''),
					'quantity'        => $quantity,
					'sub_total'       => number_format($subTotal, 2, '.', ''),
					'shipping_charge' => number_format($shippingCharge, 2, '.', ''),
				];
			})->filter()->values();

			/* Add US surcharges */
			if ($record->is_lift_gate) $cartAmount += 75;
			if ($record->is_residential_address) $cartAmount += 199;
			if ($record->is_inside_delivery) $cartAmount += 249;

			/* Tax calculation */
			$taxPercentage = $record->tax_percentage ?? 0;
			$taxAmount = round(($cartAmount * $taxPercentage) / 100, 2);

			/* UAE shipping rule */
			if ($isUAE) {
				$cartShipping = ($cartAmount + $taxAmount) < 500 ? 30 : 0;
			}

			/* Mutate model attributes directly — keeps relations intact for serialization */
			$record->amount = number_format($cartAmount, 2, '.', '');
			$record->tax_amount = number_format($taxAmount, 2, '.', '');
			$record->shipping_charge = number_format($cartShipping, 2, '.', '');
			$record->total_amount = number_format($cartAmount + $taxAmount + $cartShipping, 2, '.', '');
			$record->total_products = $totalProducts;

			/* Remove raw relation from output */
			unset($record->customerCartProducts);

			return $record;
		});

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/carts",
	 *     summary="Create a new cart",
	 *     tags={"Carts"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"customer_id", "customer_address_id", "products"},
	 *             @OA\Property(property="customer_id", type="integer", example=1),
	 *             @OA\Property(property="customer_address_id", type="integer", example="1"),
	 *             @OA\Property(property="is_lift_gate", type="boolean", example=true),
	 *             @OA\Property(property="is_residential_address", type="boolean", example=true),
	 *             @OA\Property(property="is_inside_delivery", type="boolean", example=true),
	 *             @OA\Property(property="is_new_customer", type="boolean", example=false),
	 *             @OA\Property(property="pay_with_cheque", type="boolean", example=false),
	 *             @OA\Property(property="tax_percentage", type="number", example=5),
	 *  		   @OA\Property(property="additional_amount_name", type="string", example="Accessory 1"),
	 *             @OA\Property(property="additional_amount_price", type="number", format="float", example=100),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity", "shipping_charge"},
	 *                     @OA\Property(property="product_id", type="integer", example=101, description="Product ID"),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22, description="Vendor ID"),
	 *                     @OA\Property(property="quantity", type="integer", example=5, description="Product quantity"),
	 *                     @OA\Property(property="shipping_charge", type="number", example=50.00, description="Product Shipping Charge"),
	 *                     @OA\Property(
	 *                         property="accessory_item_ids",
	 *                         type="array",
	 *                         description="Array of accessory item IDs",
	 *                         @OA\Items(type="integer", example=50)
	 *                     ),
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		/* Parse boolean fields */
		$booleanFields = ['is_lift_gate', 'is_residential_address', 'is_inside_delivery', 'is_new_customer', 'pay_with_cheque'];

		foreach ($booleanFields as $field) {
			if ($request->has($field)) {
				$request->merge([$field => filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN)]);
			}
		}

		/* Parse products JSON string to array if needed */
		if ($request->has('products') && is_string($request->products)) {
			$productsString = trim($request->products);
			/* Wrap single object in array brackets */
			if (strpos($productsString, '{') === 0 && strpos($productsString, '[') !== 0) {
				$productsString = '[' . $productsString . ']';
			}
			$request->merge(['products' => json_decode($productsString, true)]);
		}

		/* Validate request */
		$request->validate([
			'customer_id' => 'required|integer|exists:customers,id',
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'is_lift_gate' => 'nullable|boolean',
			'is_residential_address' => 'nullable|boolean',
			'is_inside_delivery' => 'nullable|boolean',
			'is_new_customer' => 'nullable|boolean',
			'pay_with_cheque' => 'nullable|boolean',
			'tax_percentage' => 'required|numeric|min:0',
			'additional_amount_name' => 'nullable|required_with:additional_amount_price|string|max:255',
			'additional_amount_price' => 'nullable|required_with:additional_amount_name|numeric|min:0',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.shipping_charge' => 'required|numeric|min:0',
			'products.*.accessory_item_ids' => 'nullable|array',
			'products.*.accessory_item_ids.*' => 'integer|exists:accessory_items,id',
		]);

		/* Verify address belongs to customer and get country margin */
		$address = CustomerAddress::with('relatedCountry:id,name,margin')
		->where('customer_id', $request->customer_id)
		->find($request->customer_address_id);

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}

		$margin = $address->relatedCountry->margin ?? 0;

		/* Get customer and calculate amounts */
		$customer = Customer::find($request->customer_id);
		$amountCalculations = $this->calculateAmount($request, $customer->is_tax_free, margin: $margin);

		DB::beginTransaction();

		try {
			/* Get existing cart or prepare for new one */
			$customerCart = CustomerCart::where('customer_id', $request->customer_id)->first();

			/* Prepare cart data */
			$cartData = [
				'customer_address_id' => $request->customer_address_id,
				'additional_amount_name' => $request->additional_amount_name,
				'additional_amount_price' => $request->additional_amount_price,
				'amount' => $amountCalculations['subtotal'],
				'pay_with_cheque' => $amountCalculations['pay_with_cheque'],
				'is_lift_gate' => $request->boolean('is_lift_gate'),
				'is_residential_address' => $request->boolean('is_residential_address'),
				'is_inside_delivery' => $request->boolean('is_inside_delivery'),
				'shipping_charge' => $amountCalculations['shipping_charge'],
				'tax_percentage' => $amountCalculations['tax_percentage'],
				'tax_amount' => $amountCalculations['tax_amount'],
				'total_amount' => $amountCalculations['grand_total'],
				'total_products' => $amountCalculations['total_products'],
				'updated_by' => auth()->id(),
			];

			if ($customerCart) {
				/* Update existing cart */
				$customerCart->update($cartData);
			} else {
				/* Generate reference number for new cart */
				$latestReferenceNumber = CustomerCart::orderBy('id', 'desc')->value('reference_number');

				$referenceNumber = $latestReferenceNumber && is_numeric($latestReferenceNumber)
				? (int) $latestReferenceNumber + 1
				: (in_array(config('app.website'), ['US', 'US_T']) ? 10001 : (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101));

				/* Create new cart */
				$customerCart = CustomerCart::create(array_merge($cartData, [
					'customer_id' => $request->customer_id,
					'reference_number' => $referenceNumber,
					'created_by' => auth()->id(),
				]));
			}

			/* Delete existing cart products and accessory charges */
			$existingCartProductIds = CustomerCartProduct::where('customer_cart_id', $customerCart->id)
			->pluck('id')
			->toArray();

			if (!empty($existingCartProductIds)) {
				/* Delete accessory charges first */
				AccessoryCharge::where('relation_type', CustomerCartProduct::class)
				->whereIn('relation_id', $existingCartProductIds)
				->delete();
			}

			/* Delete cart products */
			CustomerCartProduct::where('customer_cart_id', $customerCart->id)->delete();

			/* Insert cart products with accessory charges */
			foreach ($amountCalculations['product_details'] as $product) {
				$productAmount = $product['quantity'] * $product['unit_price'];

				$cartProduct = CustomerCartProduct::create([
					'customer_cart_id' => $customerCart->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'amount' => $productAmount,
					'accessory_item_charge' => $product['accessory_item_charge'],
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $productAmount + $product['shipping_charge'] + $product['accessory_item_charge'],
				]);


				/* Save accessory charges if present */
				if (!empty($product['accessoryItems'])) {
					foreach ($product['accessoryItems'] as $accessoryItem) {
						$cartProduct->accessoryCharges()->create([
							'accessory_item_id' => $accessoryItem['id'],
							'amount' => $accessoryItem['price'] * $product['quantity'],
							'created_at' => now(),
						]);
					}
				}
			}

			/* Handle new customer password generation */
			$isNewCustomer = $request->boolean('is_new_customer');
			$randomPassword = null;

			if ($isNewCustomer) {
				$randomPassword = Str::random(8);
				$customer->update(['password' => Hash::make($randomPassword)]);
			}

			DB::commit();

			/* Dispatch cart creation email job */
			$batch = Bus::batch([])->name('Cart Creation By Backend')->dispatch();
			$batch->options['queue'] = config('app.website') . '_CART_ADD';
			$batch->add(new CartCreationMailJob([
				'recordId' => $customerCart->id,
				'randomPassword' => $randomPassword,
				'isNewCustomer' => $isNewCustomer,
			]));

			return response()->json([
				'success' => true,
				'message' => 'Customer cart created successfully',
				'data' => $customerCart,
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create customer cart: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/carts/{id}",
	 *     summary="Get cart details by cart id",
	 *     tags={"Carts"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Cart ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Cart details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function show($id)
	{
		$cart = CustomerCart::with($this->getCartRelationships())->find($id);

		if (!$cart) {
			return response()->json([
				'success' => false,
				'message' => 'Cart not found.'
			], 404);
		}

		$formattedCart = $this->formatCartResponse($cart);

		return response()->json([
			'success' => true,
			'data' => $formattedCart
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/carts/customer/{customer_id}",
	 *     summary="Get cart details by customer id",
	 *     tags={"Carts"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="customer_id",
	 *         in="path",
	 *         description="Customer ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Cart details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function fetchByCustomerId($customer_id)
	{
		$cart = CustomerCart::with($this->getCartRelationships())
		->where('customer_id', $customer_id)
		->first();

		if (!$cart) {
			return response()->json([
				'success' => false,
				'message' => 'Cart not found for this customer.'
			], 404);
		}

		$formattedCart = $this->formatCartResponse($cart);

		return response()->json([
			'success' => true,
			'data' => $formattedCart
		], 200);
	}

	/* Get cart relationships for eager loading */
	private function getCartRelationships()
	{
		return [
			'customer:id,name,email,type,country_code,mobile_number',
			'customerAddress:id,address,city,country',
			'customerAddress.relatedCountry:id,name,margin',
			'customerCartProducts:id,customer_cart_id,product_id,vendor_id,quantity,unit_price,amount,accessory_item_charge,shipping_charge,total_amount',
			'customerCartProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
			'customerCartProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
			'customerCartProducts.accessoryCharges.accessoryItem.accessory:id,name',
			'customerCartProducts.product:id,name,images,sku,brand_id,barcode',
			'customerCartProducts.product.brand:id,name',
			'creator:id,first_name,last_name',
			'updator:id,first_name,last_name',
		];
	}

	/* Format cart response with calculated totals */
	private function formatCartResponse($cart)
	{
		/* Pre-load vendor suppliers in ONE query to avoid N+1 */
		// if ($cart->customerCartProducts->isNotEmpty()) {
		// 	$vendorSuppliers = ProductSupplier::where(function($query) use ($cart) {
		// 		foreach ($cart->customerCartProducts as $cp) {
		// 			$query->orWhere(function($q) use ($cp) {
		// 				$q->where('product_id', $cp->product_id)
		// 				->where('vendor_id', $cp->vendor_id);
		// 			});
		// 		}
		// 	})
		// 	->select('id', 'product_id', 'vendor_id', 'price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy')
		// 	->get()
		// 	->keyBy(fn($item) => $item->product_id . '_' . $item->vendor_id);

		// 	/* Attach to each cart product */
		// 	foreach ($cart->customerCartProducts as $cp) {
		// 		$key = $cp->product_id . '_' . $cp->vendor_id;
		// 		$cp->setRelation('vendorProductSupplier', $vendorSuppliers->get($key));
		// 	}
		// }

		/* Add created/updated by names */
		$margin = $cart->customerAddress->relatedCountry->margin ?? 0;

		$cart->base_currency = (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$');
		$cart->created_by_name = $cart->creator->name ?? null;
		$cart->updated_by_name = $cart->updator->name ?? null;
		unset($cart->creator, $cart->updator);

		/* Initialize cart totals for dynamic calculation */
		$cartSubtotal = 0;
		$cartTotalShipping = 0;
		$cartTotalAccessories = 0;

		/* Process each cart product */
		foreach ($cart->customerCartProducts as $cartProduct) {
			$product = $cartProduct->product;

			if ($product) {
				/* Parse product images */
				$product->images = is_array($product->images)
				? $product->images
				: (json_decode($product->images, true) ?: []);

				$product->brand_name = $product->brand->name ?? null;
				unset($product->brand);
			}

			/* Get vendor product supplier details */
			$supplier = optional($cartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);

			if ($supplier) {
				/* Calculate actual unit price */
				$unitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price'])
				? $supplier['sale_price']
				: $supplier['price'];

				$unitPriceWithMargin = $unitPrice + (in_array(config('app.website'), ['UAE', 'UAE_T', 'US_T'])  ? ($unitPrice * ($margin / 100)) : 0);

				/* Recalculate amounts based on current supplier prices */
				$amount = $cartProduct->quantity * $unitPriceWithMargin;
				$totalAmount = $amount + $cartProduct->shipping_charge + ($cartProduct->accessory_item_charge ?? 0);

				/* Update cart product with calculated values */
				$cartProduct->unit_price = $unitPriceWithMargin;
				$cartProduct->amount = $amount;
				$cartProduct->total_amount = number_format($totalAmount, 2, '.', '');

				/* Add to cart totals */
				$cartSubtotal += $amount;
				$cartTotalShipping += $cartProduct->shipping_charge;
				$cartTotalAccessories += $cartProduct->accessory_item_charge ?? 0;
				$cartProduct->product_supplier = $supplier;
			}

			/* Format accessory charges */
			if ($cartProduct->accessoryCharges->isNotEmpty()) {
				$cartProduct->accessory_charges = $cartProduct->accessoryCharges->map(function ($charge) {
					return [
						'id' => $charge->id,
						'accessory_item_id' => $charge->accessory_item_id,
						'accessory_item_name' => $charge->accessoryItem->name ?? null,
						'accessory_item_price' => $charge->accessoryItem->price ?? null,
						'product_accessory_id' => $charge->accessoryItem->product_accessory_id ?? null,
						'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
						'amount' => $charge->amount,
					];
				});
				unset($cartProduct->accessoryCharges);
			} else {
				$cartProduct->accessory_charges = [];
				unset($cartProduct->accessoryCharges);
			}
		}

		/* Calculate cart totals dynamically */
		$liftGateCharge = $cart->is_lift_gate ? 75 : 0;
		$residentialCharge = $cart->is_residential_address ? 199 : 0;
		$insideDeliveryCharge = $cart->is_inside_delivery ? 249 : 0;
		$additionalAmount = $cart->additional_amount_price ?? 0;

		/* Calculate amount before tax */
		$amountBeforeTax = $cartSubtotal + $cartTotalShipping + $cartTotalAccessories + $liftGateCharge + $residentialCharge + $insideDeliveryCharge + $additionalAmount;

		/* Calculate tax */
		$taxPercentage = $cart->tax_percentage ?? 0;
		$taxAmount = ($amountBeforeTax * $taxPercentage) / 100;

		/* Calculate final total */
		$totalAmount = $amountBeforeTax + $taxAmount;

		/* Update cart with calculated values */
		$cart->amount = $cartSubtotal;
		$cart->shipping_charge = $cartTotalShipping;
		$cart->tax_percentage = $taxPercentage;
		$cart->tax_amount = $taxAmount;
		$cart->total_amount = $totalAmount;
		$cart->additional_amount_price = $additionalAmount;

		return $cart;
	}

	/**
	 * @OA\Delete(
	 *     path="/api/carts/{id}",
	 *     summary="Delete a cart",
	 *     tags={"Carts"},
	 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Deleted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$cart = CustomerCart::find($id);

		if (!$cart) {
			return response()->json([
				'success' => false,
				'message' => 'Cart not found',
			], 404);
		}

		DB::beginTransaction();

		try {
			/* Delete accessory charges for all cart products */
			$cartProductIds = $cart->customerCartProducts->pluck('id')->toArray();

			if (!empty($cartProductIds)) {
				AccessoryCharge::where('relation_type', CustomerCartProduct::class)
				->whereIn('relation_id', $cartProductIds)
				->delete();
			}

			/* Delete cart products */
			$cart->customerCartProducts()->delete();

			/* Delete the cart */
			$cart->delete();

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Cart deleted successfully',
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to delete cart: ' . $e->getMessage()
			], 500);
		}
	}
}
