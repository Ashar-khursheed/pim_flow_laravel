<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

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
			'customerCartProducts:id,customer_cart_id,product_id,vendor_id,quantity',
			'customerCartProducts.product:id,name,images,sku,currency_id,barcode',
			'customerCartProducts.product.currency:id,symbol',
			'creator:id,username',
		]);

		if ($request->has('from_date') && $request->has('to_date')) {
			$from = $request->from_date . ' 00:00:00';
			$to = $request->to_date . ' 23:59:59';
			$recordsQuery->whereBetween('customer_carts.created_at', [$from, $to]);
		} elseif ($request->has('from_date')) {
			$from = $request->from_date . ' 00:00:00';
			$recordsQuery->where('customer_carts.created_at', '>=', $from);
		} elseif ($request->has('to_date')) {
			$to = $request->to_date . ' 23:59:59';
			$recordsQuery->where('customer_carts.created_at', '<=', $to);
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

		if ($request->has('from_admin_panel')) {
			$fromAdminPanel = $request->boolean('from_admin_panel');

			if ($fromAdminPanel) {
				$recordsQuery->where("customer_carts.created_by", ">", 0);
			}
		}

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

		/* Check if pagination requested */
		if ($request->filled('page') && $request->filled('length')) {
			/* Pagination */
			$length = (int) $request->input('length');
			$page = (int) $request->input('page');
			$totalPages = (int) ceil($totalRecords / $length);

			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}
		} else {
			/* No pagination: just fetch id and reference number */
			$page = 1;
			$length = $totalRecords;
			$totalPages = 1;
		}

		if ($onlyTotalAmount) {
			$records =(clone $recordsQuery)->sum('total_amount');
		} elseif ($onlyID) {
			$records =(clone $recordsQuery)->pluck('id');
		} else {
			$records = $recordsQuery
			->offset(($page - 1) * $length)
			->limit($length)
			->get(['id', 'reference_number', 'customer_id', 'is_lift_gate', 'is_residential_address', 'is_inside_delivery', 'total_amount', 'total_products', 'created_by', 'created_at']);

			/* Transform results */
			$records->transform(function ($record) {
				/* Process each product in customer cart products */
				$totalProducts = 0;
				$cartAmount = 0;
				$cartShipping = 0;
				$cartProducts = [];

				foreach ($record->customerCartProducts as $customerCartProduct) {
					$product = $customerCartProduct->product;
					if (!$product) continue;

					/* Decode images if stored as JSON string */
					$images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
					$image = $images[0] ?? null;

					$supplier = optional($customerCartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge']);

					$unitPrice = 0;
					$shippingCharge = 0;
					if ($supplier) {
						$unitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price']) ? $supplier['sale_price'] : $supplier['price'];
						$shippingCharge = $supplier['shipping_charge'] ?? 0;
					}

					$quantity = $customerCartProduct->quantity ?? 0;
					$subTotal = $quantity * $unitPrice;

					$totalProducts += $quantity;
					$cartAmount += $subTotal;
					$cartShipping += $shippingCharge;

					/* Push product data */
					$cartProducts[] = [
						'product_id'      => $customerCartProduct->product_id,
						'vendor_id'       => $customerCartProduct->vendor_id,
						'image'           => $image,
						'name'            => $product->name,
						'currency_symbol' => $product->currency->symbol ?? null,
						'unit_price'      => number_format($unitPrice, 2, '.', ''),
						'quantity'        => $quantity,
						'sub_total'       => number_format($subTotal, 2, '.', ''),
						'shipping_charge' => number_format($shippingCharge, 2, '.', ''),
					];
				}

				/* Add surcharges */
				if ($record->is_lift_gate) {
					$cartAmount += 75;
				}
				if ($record->is_residential_address) {
					$cartAmount += 199;
				}
				if ($record->is_inside_delivery) {
					$cartAmount += 249;
				}

				/* Tax calculations */
				$taxPercentage = $record->tax_percentage ?? 0;
				$taxAmount = round(($cartAmount * $taxPercentage) / 100, 2);

				/* Website-specific shipping rules */
				if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
					$cartShipping = ($cartAmount + $taxAmount) < 500 ? 30 : 0;
				}

				$totalAmount = $cartAmount + $taxAmount + $cartShipping;

				/* Prepare cart summary */
				$record = [
					'id'                     => $record->id,
					'reference_number'       => $record->reference_number,
					'customer'               => $record->customer,
					'is_lift_gate'           => $record->is_lift_gate,
					'is_residential_address' => $record->is_residential_address,
					'is_inside_delivery'     => $record->is_inside_delivery,
					'amount'                 => number_format($cartAmount, 2, '.', ''),
					'tax_amount'             => number_format($taxAmount, 2, '.', ''),
					'shipping_charge'        => number_format($cartShipping, 2, '.', ''),
					'total_amount'           => number_format($totalAmount, 2, '.', ''),
					'total_products'         => $totalProducts,
					'products'               => $cartProducts,
					'creator'                => $record->creator,
					'created_at'             => $record->created_at,
				];

				return $record;
			});
		}

		return response()->json([
			'success' => true,
			'message' => $onlyTotalAmount ? 'Total amount' : __('msg_rec_list'),
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
	 *                     @OA\Property(property="product_id", type="integer", example=101),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=5),
	 *                     @OA\Property(property="shipping_charge", type="number", example=50.00, description="Product Shipping Charge"),
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
		$request->validate([
			'customer_id' => 'required|integer|exists:customers,id',
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'is_lift_gate' => 'nullable|boolean',
			'is_residential_address' => 'nullable|boolean',
			'is_inside_delivery' => 'nullable|boolean',
			'is_new_customer' => 'nullable|boolean',
			'pay_with_cheque' => 'nullable|boolean',
			'tax_percentage' => 'required|numeric|min:0',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.shipping_charge' => 'required|numeric|min:0',
			'additional_amount_name' => 'nullable|required_with:additional_amount_price|string|max:255',
			'additional_amount_price' => 'nullable|required_with:additional_amount_name|numeric|min:0',
		]);

		$address = CustomerAddress::where('id', $request->customer_address_id)
		->where('customer_id', $request->customer_id)
		->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}

		// $specificShipping = in_array(config('app.website'), ['US', 'US_T']) ? ($address->state === 'Texas' ? 99 : 199) : 0;

		/* Collect all product supplier details in one go */
		$productDetails = [];
		foreach ($request->products as $product) {
			$fetchedDetail = productSupplierDetail($product['product_id'], $product['vendor_id']);
			if (!$fetchedDetail) {
				throw new \Exception("Product supplier not found for Product {$product['product_id']} & Vendor {$product['vendor_id']}");
			}

			// $charge = empty($fetchedDetail->shipping_charge) ? $specificShipping : $fetchedDetail->shipping_charge;
			// $shipping = $request->boolean('is_customer_pickup') ? 0 : ($charge * $product['quantity']);

			$productDetails[] = [
				'product_id' => $product['product_id'],
				'vendor_id' => $product['vendor_id'],
				'quantity' => $product['quantity'],
				'unit_price' => $fetchedDetail->unit_price,
				// 'shipping_charge' => $shipping,
				'shipping_charge' => $product['shipping_charge'],
			];
		}

		$payWithCheque = $request->boolean('pay_with_cheque', false);
		$totalProducts = 0;
		$cartAmount = 0;
		$cartShipping = 0;
		foreach ($productDetails as $product) {
			$totalProducts += $product['quantity'];
			$cartAmount += $product['quantity'] * $product['unit_price'];
			$cartShipping += $product['shipping_charge'];
		}

		if (!empty($request->additional_amount_price)) {
			$cartAmount += (float) $request->additional_amount_price;
		}

		$cartAmount += $request->boolean('is_lift_gate') ? 75 : 0;
		$cartAmount += $request->boolean('is_residential_address') ? 199 : 0;
		$cartAmount += $request->boolean('is_inside_delivery') ? 249 : 0;

		$customer = Customer::find($request->customer_id);
		$taxPercentage = $customer->is_tax_free ? 0 : $request->tax_percentage;

		if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
			$taxAmount = round($cartAmount * ($taxPercentage / 100), 2);
			$cartShipping = ($cartAmount + $taxAmount) < 500 ? 30 : 0;
		} elseif (in_array(config('app.website'), ['US', 'US_T'])) {
			$taxableAmount = $cartAmount + $cartShipping;
			$taxAmount = round($taxableAmount * ($taxPercentage / 100), 2);
		} else {
			$taxAmount = round($cartAmount * ($taxPercentage / 100), 2);
		}
		$totalAmount = $cartAmount + $taxAmount + $cartShipping;


		DB::beginTransaction();

		try {
			/* Get the latest cart by ID (most recent) */
			$latestCart = CustomerCart::orderBy('id', 'desc')->first();

			/* Get the latest cart by ID (most recent) */
			$customerCart = CustomerCart::firstOrNew([
				'customer_id' => $request->customer_id
			]);

			if (!$customerCart->exists) {
				/* New record → generate reference number */
				if ($latestCart && is_numeric($latestCart->reference_number)) {
					$referenceNumber = (int) $latestCart->reference_number + 1;
				} else {
					$referenceNumber = in_array(config('app.website'), ['US', 'US_T']) ? 10001 : (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101);
				}

				$customerCart->reference_number = $referenceNumber;
				$customerCart->created_by = auth()->id();
			}

			/* Always update these fields */
			$customerCart->customer_address_id = $request->customer_address_id;
			$customerCart->shipping_charge = $cartShipping;
			$customerCart->is_lift_gate = $request->is_lift_gate;
			$customerCart->is_residential_address = $request->is_residential_address;
			$customerCart->is_inside_delivery = $request->is_inside_delivery;
			$customerCart->pay_with_cheque = $payWithCheque;
			$customerCart->amount = $cartAmount;
			$customerCart->tax_percentage = $taxPercentage;
			$customerCart->tax_amount = $taxAmount;
			$customerCart->total_amount = $totalAmount;
			$customerCart->total_products = $totalProducts;
			$customerCart->updated_by = auth()->id();
			$customerCart->additional_amount_name = $request->additional_amount_name ?? null;
			$customerCart->additional_amount_price = $request->additional_amount_price ?? null;

			$customerCart->save();

			/* Delete existing products and re-insert */
			CustomerCartProduct::where('customer_cart_id', $customerCart->id)->delete();

			foreach ($productDetails as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				CustomerCartProduct::create([
					'customer_cart_id' => $customerCart->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'amount' => $total,
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $total + $product['shipping_charge'],
				]);
			}

			$isNewCustomer = $request->boolean('is_new_customer');
			$randomPassword = null;
			if ($isNewCustomer) {
				$randomPassword = Str::random(8);
				$hashedPassword = Hash::make($randomPassword);
				$customerCart->customer->update(['password' => $hashedPassword]);
			}

			DB::commit();

			$batch = Bus::batch([])->name('Cart Creation By Backend')->dispatch();

			$batch->options['queue'] = config('app.website') . '_CART_ADD';
			$batch->add(new CartCreationMailJob([
				'recordId' => $customerCart->id,
				'randomPassword' => $randomPassword,
				'isNewCustomer' => $isNewCustomer,
			]));

			$cartProducts = [];
			foreach ($customerCart->customerCartProducts as $customerCartProduct) {
				$product = $customerCartProduct->product;
				if (!$product) continue;

				/* Decode images if stored as JSON string */
				$images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
				$image = $images[0] ?? null;

				$supplier = optional($customerCartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge']);

				$unitPrice = 0;
				$shippingCharge = 0;
				if ($supplier) {
					$unitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price']) ? $supplier['sale_price'] : $supplier['price'];
					$shippingCharge = $supplier['shipping_charge'] ?? 0;
				}

				$quantity = $customerCartProduct->quantity ?? 0;
				$subTotal = $quantity * $unitPrice;

				/* Push product data */
				$cartProducts[] = [
					'product_id'      => $customerCartProduct->product_id,
					'vendor_id'       => $customerCartProduct->vendor_id,
					'name'            => $product->name,
					'image'           => $image,
					'sku'             => $product->sku,
					'currency_symbol' => $product->currency->symbol ?? null,
					'quantity'        => $quantity,
					'unit_price'      => number_format($unitPrice, 2, '.', ''),
					'sub_total'       => number_format($subTotal, 2, '.', ''),
					'shipping_charge' => number_format($shippingCharge, 2, '.', ''),
				];
			}

			/* Prepare cart summary */
			$carts = [
				'reference_number'       => $customerCart->reference_number,
				'address'                => $customerCart->customerAddress,
				'is_lift_gate'           => $customerCart->is_lift_gate,
				'is_residential_address' => $customerCart->is_residential_address,
				'additional_amount_name' => $customerCart->additional_amount_name,
				'additional_amount_price' => $customerCart->additional_amount_price,
				'is_inside_delivery'     => $customerCart->is_inside_delivery,
				'shipping_charge'        => number_format($cartShipping, 2, '.', ''),
				'amount'                 => number_format($cartAmount, 2, '.', ''),
				'tax_percentage'         => $taxPercentage,
				'tax_amount'             => number_format($taxAmount, 2, '.', ''),
				'total_amount'           => number_format($totalAmount, 2, '.', ''),
				'total_products'         => $totalProducts,
				'products'               => $cartProducts,
			];

			return response()->json([
				'success' => true,
				'message' => 'Customer cart created successfully',
				'data' => $carts,
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
	 *     path="/api/carts/fetch/{id}",
	 *     summary="Get cart details by id",
	 *     tags={"Carts"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Cart ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function fetchByID($id)
	{
		$record = CustomerCart::with([
			'customer:id,name,email,country_code,mobile_number',
			'customerAddress',
			'customerCartProducts:id,customer_cart_id,product_id,vendor_id,quantity',
			'customerCartProducts.product:id,name,images,sku,currency_id,barcode',
			'customerCartProducts.product.currency:id,symbol',
			'creator:id,username',
		])
		->find($id);

		if (!$record) {
			return response()->json([
				'success' => false,
				'data' => __("err_exist")
			]);
		}

		/* Process each product in customer cart products */
		$totalProducts = 0;
		$cartAmount = 0;
		$cartShipping = 0;
		$cartProducts = [];

		foreach ($record->customerCartProducts as $customerCartProduct) {
			$product = $customerCartProduct->product;
			if (!$product) continue;

			/* Decode images if stored as JSON string */
			$images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
			$image = $images[0] ?? null;

			$supplier = optional($customerCartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge']);

			$unitPrice = 0;
			$shippingCharge = 0;
			if ($supplier) {
				$unitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price']) ? $supplier['sale_price'] : $supplier['price'];
				$shippingCharge = $supplier['shipping_charge'] ?? 0;
			}

			$quantity = $customerCartProduct->quantity ?? 0;
			$subTotal = $quantity * $unitPrice;

			$totalProducts += $quantity;
			$cartAmount += $subTotal;
			$cartShipping += $shippingCharge;

			/* Push product data */
			$cartProducts[] = [
				'product_id'      => $customerCartProduct->product_id,
				'vendor_id'       => $customerCartProduct->vendor_id,
				'image'           => $image,
				'name'            => $product->name,
				'currency_symbol' => $product->currency->symbol ?? null,
				'unit_price'      => number_format($unitPrice, 2, '.', ''),
				'quantity'        => $quantity,
				'sub_total'       => number_format($subTotal, 2, '.', ''),
				'shipping_charge' => number_format($shippingCharge, 2, '.', ''),
			];
		}

		/* Add surcharges */
		if ($record->is_lift_gate) {
			$cartAmount += 75;
		}
		if ($record->is_residential_address) {
			$cartAmount += 199;
		}

		/* Tax calculations */
		$taxPercentage = $record->tax_percentage ?? 0;
		$taxAmount = round(($cartAmount * $taxPercentage) / 100, 2);

		/* Website-specific shipping rules */
		if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
			$cartShipping = ($cartAmount + $taxAmount) < 500 ? 30 : 0;
		}

		$totalAmount = $cartAmount + $taxAmount + $cartShipping;

		/* Prepare cart summary */
		$cart = [
			'id'                     => $record->id,
			'reference_number'       => $record->reference_number,
			'customer'               => $record->customer,
			'customer_address'       => $record->customerAddress,
			'is_lift_gate'           => $record->is_lift_gate,
			'is_residential_address' => $record->is_residential_address,
			'amount'                 => number_format($cartAmount, 2, '.', ''),
			'tax_percentage'         => number_format($taxPercentage, 2, '.', ''),
			'tax_amount'             => number_format($taxAmount, 2, '.', ''),
			'shipping_charge'        => number_format($cartShipping, 2, '.', ''),
			'total_amount'           => number_format($totalAmount, 2, '.', ''),
			'total_products'         => $totalProducts,
			'products'               => $cartProducts,
			'creator'                => $record->creator,
			'created_at'             => $record->created_at,
		];

		return response()->json([
			'success' => true,
			'data' => $cart
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/carts/{customer_id}",
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
	 *     @OA\Response(response=200, description="Retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function show($customer_id)
	{
		$customerCart = CustomerCart::where('customer_id', $customer_id)
		->with([
			'customerAddress',
			'customerCartProducts.product.currency:id,symbol',
		])
		->first();

		if (!$customerCart) {
			return response()->json([
				'success' => true,
				'data' => []
			]);
		}

		$totalProducts = 0;
		$cartAmount = 0;
		$cartShipping = 0;
		$cartProducts = [];

		// foreach ($customerCart->customerCartProducts as $customerCartProduct) {
		// 	$product = $customerCartProduct->product;
		// 	if (!$product) continue;

		// 	/* Decode images if stored as JSON string */
		// 	$images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
		// 	$image = $images[0] ?? null;

		// 	$supplier = optional($customerCartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge']);

		// 	$unitPrice = 0;
		// 	$shippingCharge = 0;
		// 	if ($supplier) {
		// 		$unitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price']) ? $supplier['sale_price'] : $supplier['price'];
		// 		$shippingCharge = $supplier['shipping_charge'] ?? 0;
		// 	}

		// 	$quantity = $customerCartProduct->quantity ?? 0;
		// 	$subTotal = $quantity * $unitPrice;

		// 	$totalProducts += $quantity;
		// 	$cartAmount += $subTotal;
		// 	$cartShipping += $shippingCharge;

		// 	/* Push product data */
		// 	$cartProducts[] = [
		// 		'product_id'      => $customerCartProduct->product_id,
		// 		'vendor_id'       => $customerCartProduct->vendor_id,
		// 		'name'            => $product->name,
		// 		'image'           => $image,
		// 		'sku'             => $product->sku,
		// 		'currency_symbol' => $product->currency->symbol ?? null,
		// 		'quantity'        => $quantity,
		// 		'unit_price'      => number_format($unitPrice, 2, '.', ''),
		// 		'sub_total'       => number_format($subTotal, 2, '.', ''),
		// 		'shipping_charge' => number_format($shippingCharge, 2, '.', ''),
		// 	];
		// }
		foreach ($customerCart->customerCartProducts as $customerCartProduct) {
			$product = $customerCartProduct->product;
			if (!$product) continue;

			$images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
			$image = $images[0] ?? null;

			$supplier = optional($customerCartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge']);

			$unitPrice = 0;
			$shippingCharge = 0;
			if ($supplier) {
				$unitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price'])
				? $supplier['sale_price']
				: $supplier['price'];

				$shippingCharge = $supplier['shipping_charge'] ?? 0;
			}

			// ============================================
			// 🔥 ADD YOUR NEW US / US_T SHIPPING LOGIC HERE
			// ============================================
			if (in_array(config('app.website'), ['US', 'US_T'])) {

				$state = $customerCart->customerAddress->state ?? null;

				if (!$customerCart->is_customer_pickup) {
					if ($state === 'Texas') {
						$shippingCharge = ($shippingCharge > 0) ? $shippingCharge : 99;
					} else {
						$shippingCharge = ($shippingCharge > 0) ? $shippingCharge : 199;
					}
				} else {
					$shippingCharge = 0;
				}
			}
			// ============================================

			$quantity = $customerCartProduct->quantity ?? 0;
			$subTotal = $quantity * $unitPrice;

			$totalProducts += $quantity;
			$cartAmount += $subTotal;
			$cartShipping += $shippingCharge;

			$cartProducts[] = [
				'product_id'      => $customerCartProduct->product_id,
				'vendor_id'       => $customerCartProduct->vendor_id,
				'name'            => $product->name,
				'image'           => $image,
				'sku'             => $product->sku,
				'currency_symbol' => $product->currency->symbol ?? null,
				'quantity'        => $quantity,
				'unit_price'      => number_format($unitPrice, 2, '.', ''),
				'sub_total'       => number_format($subTotal, 2, '.', ''),
				'shipping_charge' => number_format($shippingCharge, 2, '.', ''),
			];
		}


		/* Add surcharges */
		if ($customerCart->is_lift_gate) {
			$cartAmount += 75;
		}
		if ($customerCart->is_residential_address) {
			$cartAmount += 199;
		}
		if ($customerCart->is_inside_delivery) {
			$cartAmount += 250;
		}
		if ($customerCart->additional_amount_price) {
			$cartAmount += $customerCart->additional_amount_price;
		}

		/* Tax calculations */
		$taxPercentage = $customerCart->tax_percentage ?? 0;
		$taxAmount = round(($cartAmount * $taxPercentage) / 100, 2);

		/* Website-specific shipping rules */
		if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
			$cartShipping = ($cartAmount + $taxAmount) < 500 ? 30 : 0;
		}

		$totalAmount = $cartAmount + $taxAmount + $cartShipping;

		/* Prepare cart summary */
		$carts = [
			'reference_number'       => $customerCart->reference_number,
			'address'                => $customerCart->customerAddress,
			'is_lift_gate'           => $customerCart->is_lift_gate,
			'is_residential_address' => $customerCart->is_residential_address,
			'is_inside_delivery'     => $customerCart->is_inside_delivery,
			'shipping_charge'        => number_format($cartShipping, 2, '.', ''),
			'amount'                 => number_format($cartAmount, 2, '.', ''),
			'tax_percentage'         => $taxPercentage,
			'tax_amount'             => number_format($taxAmount, 2, '.', ''),
			'total_amount'           => number_format($totalAmount, 2, '.', ''),
			'total_products'         => $totalProducts,
			'additional_amount_name' => $customerCart->additional_amount_name,
			'additional_amount_price' => $customerCart->additional_amount_price,
			'products'               => $cartProducts,
		];

		return response()->json([
			'success' => true,
			'data' => $carts
		]);
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, CustomerCart $customerCart)
	{
		//
	}

	/**
	 * @OA\Delete(
	 *     path="/api/carts/{customer_id}",
	 *     summary="Delete a cart",
	 *     tags={"Carts"},
	 *     @OA\Parameter(name="customer_id", in="path", required=true, @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Deleted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($customer_id)
	{
		$customer = Customer::find($customer_id);

		if (!$customer) {
			return response()->json([
				'success' => false,
				'message' => 'Customer not found',
			], 404);
		}

		$customer->customerCarts->each(function ($cart) {
			$cart->customerCartProducts()->delete();
			$cart->delete();
		});

		return response()->json([
			'success' => true,
			'message' => 'All carts deleted successfully for customer',
		]);
	}
}
