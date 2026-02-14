<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;

use App\Models\FrontEnd\Order;
use App\Models\ChequeUpload;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\OrderTracking;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\Finance;
use App\Models\FrontEnd\FinancesPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Utm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use App\Models\FrontEnd\Wishlist;


use App\Jobs\Order\OrderPlacedMailJob;
use App\Jobs\Order\OrderReservedMailJob;
use App\Jobs\Order\OrderCancelledMailJob;

use App\Traits\CalculationTrait;

class OrderController extends BaseController
{
	use CalculationTrait;
	/**
	 * @OA\Get(
	 *     path="/api/frontend/orders",
	 *     summary="Get all orders with pagination and filters",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="status", in="query", description="Filter by order status.", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="payment_status", in="query", description="Filter by payment status.", @OA\Schema(type="string", enum={"Paid", "Unpaid", "Partially Paid"})),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "order_number", "shipping_charge", "total_amount", "total_products", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'order_number'];
		$sortableColumns = array_merge($searchableColumns, ['shipping_charge', 'total_amount', 'total_products', 'created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Order::where('customer_id', auth()->id());
		/* Check if pagination requested */
		if ($request->filled('page') && $request->filled('length')) {
			/* Eager load relationships */
			$recordsQuery->with([
				'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status,accessory_item_charge',
				'orderProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
				'orderProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
				'orderProducts.accessoryCharges.accessoryItem.accessory:id,name',
				'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'orderProducts.product.brand:id,name',
				'orderProducts.product.currency:id,symbol',
				'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at',
				'shipments',
			]);

			/* Filter by status */
			if ($request->has('status')) {
				$recordsQuery->where('orders.status', $request->status);
			}

			if ($request->has('from_date') && $request->has('to_date')) {
				$from = $request->from_date . ' 00:00:00';
				$to = $request->to_date . ' 23:59:59';
				$recordsQuery->whereBetween('orders.created_at', [$from, $to]);
			} elseif ($request->has('from_date')) {
				$from = $request->from_date . ' 00:00:00';
				$recordsQuery->where('orders.created_at', '>=', $from);
			} elseif ($request->has('to_date')) {
				$to = $request->to_date . ' 23:59:59';
				$recordsQuery->where('orders.created_at', '<=', $to);
			}

			/* Filter by payment status */
			if ($request->has('payment_status')) {
				switch ($request->payment_status) {
					case 'Paid':
					$recordsQuery->whereColumn('orders.paid_amount', '>=', 'orders.total_amount');
					break;
					case 'Unpaid':
					$recordsQuery->where('orders.paid_amount', 0);
					break;
					case 'Partially Paid':
					$recordsQuery->where('orders.paid_amount', '>', 0)
					->whereColumn('orders.paid_amount', '<', 'orders.total_amount');
					break;
				}
			}

			/* Global search */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						$q->orWhere("orders.$col", 'like', '%' . $search . '%');
					}
				});
			}

			/* Sorting */
			$recordsQuery->orderBy($sortBy, $sortDir);

			/* Pagination */
			$length = (int) $request->input('length');
			$page = (int) $request->input('page');

			$totalRecords = (clone $recordsQuery)->count();
			$totalPages = (int) ceil($totalRecords / $length);

			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery
			->offset(($page - 1) * $length)
			->limit($length)
			->get();

			/* Transform results */
			$records->transform(function ($record) {
				/* Process each product in order products */
				foreach ($record->orderProducts as $orderProduct) {
					$product = $orderProduct->product;
					if ($product) {
						$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
						$product->brand_name = $product->brand->name ?? null;
						$product->currency_symbol = $product->currency->symbol ?? null;
						unset($product->brand, $product->currency);
					}
					$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);
					$orderProduct->expectedShippingDate = $orderProduct->product_supplier
					? getDateRange($record->created_at, $orderProduct->product_supplier['delivery_days'])
					: null;

					if ($orderProduct->accessoryCharges) {
						$orderProduct->accessory_charges = $orderProduct->accessoryCharges->map(function ($charge) {
							return [
								'id' => $charge->id,
								'accessory_item_id' => $charge->accessory_item_id,
								'accessory_item_name' => $charge->accessoryItem->name ?? null,
								'accessory_item_price' => $charge->accessoryItem->price ?? null,
								'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
								'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
								'amount' => $charge->amount,
							];
						});

						unset($orderProduct->accessoryCharges);
					}
				}
				foreach (['amount', 'tax_amount', 'discount', 'additional_discount_amount', 'cheque_discount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
					if (isset($record->$key)) {
						$record->$key = number_format($record->$key, 2, '.', '');
					}
				}

				return $record;
			});
		} else {
			/* No pagination: just fetch id and order_number */
			$records = Order::orderBy('order_number', 'asc')->get(['id', 'order_number']);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

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
	 *     path="/api/frontend/orders",
	 *     summary="Create a new order",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"customer_address_id", "tax_percentage", "products"},
	 *                 @OA\Property(property="customer_address_id", type="integer", example=1, description="Customer address ID"),
	 *                 @OA\Property(property="is_lift_gate", type="boolean", example=true, description="Lift gate required"),
	 *                 @OA\Property(property="is_residential_address", type="boolean", example=true, description="Residential address"),
	 *                 @OA\Property(property="is_inside_delivery", type="boolean", example=true, description="Inside delivery required"),
	 *                 @OA\Property(property="tax_percentage", type="number", format="float", example=5, description="Tax percentage"),
	 *                 @OA\Property(property="ship_all_at_once", type="boolean", example=true, description="Ship all items together"),
	 *                 @OA\Property(property="separate_deliveries", type="boolean", example=false, description="Separate deliveries"),
	 *
	 *                 @OA\Property(property="additional_amount_name", type="string", example="Accessory 1", description="Additional amount name"),
	 *                 @OA\Property(property="additional_amount_price", type="number", format="float", example=100, description="Additional amount price"),
	 *
	 *                 @OA\Property(property="coupon_id", type="integer", example=1, description="Coupon ID"),
	 *                 @OA\Property(property="discount", type="number", format="float", example=200, description="Discount amount"),
	 *
	 *                 @OA\Property(property="additional_discount_option", type="boolean", example=true, description="Additional Discount Option"),
	 *                 @OA\Property(property="additional_discount_reason", type="string", example="Bulk order discount", description="Reason for additional discount"),
	 *                 @OA\Property(property="additional_discount_type", type="string", enum={"fixed", "percentage"}, example="percentage"),
	 *                 @OA\Property(property="additional_discount_percentage", type="number", format="float", example=10.50, description="Additional discount percentage"),
	 *                 @OA\Property(property="additional_discount_amount", type="number", format="float", example=50.00, description="Additional discount amount"),
	 *
	 *                 @OA\Property(property="payment_mode", type="string", enum={"Stripe", "Check Payment", "Ascentium Financing", "Approve Financing", "Resolve Financing", "Net Terms"}, example="Check Payment", description="Payment mode"),
	 *                 @OA\Property(property="pay_with_cheque", type="boolean", example=false, description="Pay with cheque"),
	 *                 @OA\Property(property="cheque_img", type="string", format="binary", description="Cheque image (jpeg, png, webp only, max 5 MB)"),
	 *                 @OA\Property(property="cheque_img_back", type="string", format="binary", description="Cheque image (jpeg, png, webp only, max 5 MB)"),
	 *                 @OA\Property(property="cheque_img_url", type="string", example="https://example.com/image.png"),
	 *                 @OA\Property(property="cheque_img_back_url", type="string", example="https://example.com/image.png"),
	 *
	 *                 @OA\Property(property="is_cod", type="boolean", example=false, description="Cash on delivery"),
	 *                 @OA\Property(property="is_reserved", type="boolean", example=false, description="Reserved order"),
	 *                 @OA\Property(property="is_payment", type="boolean", example=false, description="Payment gateway"),
	 *                 @OA\Property(property="is_ccavenue", type="boolean", example=false, description="ccavenue payment"),
	 *                 @OA\Property(property="is_squarePayment", type="boolean", example=false, description="Square payment"),
	 *                 @OA\Property(property="is_customer_pickup", type="boolean", example=false, description="Customer pickup"),
	 *                 @OA\Property(
	 *                     property="products",
	 *                     type="array",
	 *                     description="Array of products to order",
	 *                     @OA\Items(
	 *                         required={"product_id", "vendor_id", "quantity", "shipping_charge"},
	 *                         @OA\Property(property="product_id", type="integer", example=101, description="Product ID"),
	 *                         @OA\Property(property="vendor_id", type="integer", example=22, description="Vendor ID"),
	 *                         @OA\Property(property="quantity", type="integer", example=5, description="Product quantity"),
	 *                         @OA\Property(property="shipping_charge", type="number", example=50.00, description="Product Shipping Charge"),
	 *                         @OA\Property(
	 *                             property="accessory_item_ids",
	 *                             type="array",
	 *                             description="Array of accessory item IDs",
	 *                             @OA\Items(type="integer", example=50)
	 *                         )
	 *                     )
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
		/* Parse boolean strings to actual booleans */
		$booleanFields = [
			'is_lift_gate',
			'is_residential_address',
			'is_inside_delivery',
			'ship_all_at_once',
			'separate_deliveries',
			'is_cod',
			'pay_with_cheque',
			'additional_discount_option',
			'is_reserved',
			'is_payment',
			'is_ccavenue',
			'is_squarePayment',
			'is_customer_pickup'
		];

		/* Parse products JSON string to array */
		foreach ($booleanFields as $field) {
			if ($request->has($field)) {
				$request->merge([
					$field => filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN)
				]);
			}
		}
		if ($request->has('products') && is_string($request->products)) {
			$productsString = $request->products;
			if (strpos(trim($productsString), '{') === 0 && strpos(trim($productsString), '[') !== 0) {
				$productsString = '[' . $productsString . ']';
			}
			$products = json_decode($productsString, true);
			$request->merge(['products' => $products]);
		}

		$request->validate([
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'is_lift_gate' => 'nullable|boolean',
			'is_residential_address' => 'nullable|boolean',
			'is_inside_delivery' => 'nullable|boolean',
			'tax_percentage' => 'required|numeric|min:0',
			'ship_all_at_once' => 'nullable|boolean',
			'separate_deliveries' => 'nullable|boolean',

			'additional_amount_name' => 'nullable|required_with:additional_amount_price|string|max:255',
			'additional_amount_price' => 'nullable|required_with:additional_amount_name|numeric|min:0',

			'coupon_id' => 'nullable|integer',
			'discount' => 'nullable|numeric|min:0',

			'payment_mode' => 'nullable|in:CCAvenue,Stripe,Check Payment,Ascentium Financing,Approve Financing,Resolve Financing,Net Terms',
			'pay_with_cheque' => 'nullable|boolean',
			'cheque_img' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
			'cheque_img_back' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
			'cheque_img_url' => 'nullable|string',
			'cheque_img_back_url' => 'nullable|string',

			'additional_discount_option' => 'nullable|boolean',
			'additional_discount_reason' => 'nullable|string|max:255',
			'additional_discount_type' => 'nullable|in:fixed,percentage',
			'additional_discount_percentage' => 'nullable|numeric|min:0|max:100|required_if:additional_discount_type,percentage',
			'additional_discount_amount' => 'nullable|numeric|min:0|required_if:additional_discount_type,fixed',

			'is_cod' => 'nullable|boolean',
			'is_reserved' => 'nullable|boolean',
			'is_payment' => 'nullable|boolean',
			'is_ccavenue' => 'nullable|boolean',
			'is_squarePayment' => 'nullable|boolean',
			'is_customer_pickup' => 'nullable|boolean',

			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.shipping_charge' => 'required|numeric|min:0',
			'products.*.accessory_item_ids' => 'nullable|array',
			'products.*.accessory_item_ids.*' => 'integer|exists:accessory_items,id',
		]);

		$customerId = auth()->id();
		$address = CustomerAddress::where('id', $request->customer_address_id)->where('customer_id', $customerId)->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}

		$customer = auth()->user();
		$amountCalculations = $this->calculateAmount($request, $customer->is_tax_free, null, true);

		DB::beginTransaction();
		try {
			/* Get the latest order by ID (most recent) */
			$latestOrder = Order::orderBy('order_number', 'desc')->first();

			/* Generate the next order number */
			if ($latestOrder && is_numeric($latestOrder->order_number)) {
				$orderNumber = (int) $latestOrder->order_number + 1;
			} else {
				$orderNumber = in_array(config('app.website'), ['US', 'US_T']) ? 10001 : (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101);
			}

			$order = Order::create([
				'order_number' => $orderNumber,
				'customer_id' => $customerId,
				'customer_address_id' => $request->customer_address_id,

				'is_lift_gate' => $request->boolean('is_lift_gate'),
				'is_residential_address' => $request->boolean('is_residential_address'),
				'is_inside_delivery' => $request->boolean('is_inside_delivery'),
				'amount' => $amountCalculations['subtotal'],

				'additional_amount_name' => $request->additional_amount_name ?? null,
				'additional_amount_price' => $request->additional_amount_price ?? null,
				'additional_amount_details' => $request->additional_amount_details ?? null,  /* ✅ Added */

				'coupon_id' => $request->coupon_id ?? null,
				'discount' => $amountCalculations['discount'],

				'additional_discount_reason' => $amountCalculations['additional_discount_reason'],
				'additional_discount_type' => $amountCalculations['additional_discount_type'],
				'additional_discount_percentage' => $amountCalculations['additional_discount_percentage'],
				'additional_discount_amount' => $amountCalculations['additional_discount_amount'],

				'payment_mode' => $request->payment_mode ?? null,
				'pay_with_cheque' => $amountCalculations['pay_with_cheque'],
				'cheque_discount_percentage' => $amountCalculations['cheque_discount_percentage'],
				'cheque_discount' => $amountCalculations['cheque_discount'],
				'cheque_img' => $amountCalculations['cheque_img'],
				'cheque_img_back' => $amountCalculations['cheque_img_back'],

				'tax_percentage' => $amountCalculations['tax_percentage'],
				'tax_amount' => $amountCalculations['tax_amount'],
				'shipping_charge' => $amountCalculations['shipping_charge'],

				'total_amount' => $amountCalculations['grand_total'],
				'total_products' => $amountCalculations['total_products'],
				'ship_all_at_once' => $request->get('ship_all_at_once', true),
				'separate_deliveries' => $request->get('separate_deliveries', false),
				'pending_amount' => $amountCalculations['grand_total'],
				'status' => 'Pending',

				'is_reserved' => $request->boolean('is_reserved'),
				'is_payment' => $request->boolean('is_payment'),
				'is_ccavenue' => $request->boolean('is_ccavenue'),
				'is_squarePayment' => $request->boolean('is_squarePayment'),
				'is_customer_pickup' => $request->boolean('is_customer_pickup'),
				'is_cod' => $request->boolean('is_cod'),

				'created_by' => 0,
				'utm_id' => $request->utm_id,
			]);

			foreach ($amountCalculations['product_details'] as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				$orderProduct = OrderProduct::create([
					'order_id' => $order->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'shipped_quantity' => 0,
					'remaining_quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'amount' => $total,
					'accessory_item_charge' => $product['accessory_item_charge'],
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $total + $product['shipping_charge'] + $product['accessory_item_charge'],
					'status' => 'Pending',
				]);

				foreach ($product['accessoryItems'] as $accessoryItem) {
					$orderProduct->accessoryCharges()->create([
						'accessory_item_id' => $accessoryItem['id'],
						'amount' => $accessoryItem['price'] * $product['quantity'],
						'created_at' => now(),
					]);
				}
			}

			OrderTracking::create([
				'order_id' => $order->id,
				'status' => 'Order Created By Customer',
				'description' => 'Order has been successfully created',
			]);

			/* Remove all customer's carts along with their products */
			$order->customer->customerCarts->each(function ($cart) {
				$cart->customerCartProducts()->delete();
				$cart->delete();
			});

			DB::commit();

			if ($request->boolean('pay_with_cheque')) {
				$batch = Bus::batch([])->name("Order Placed by Customer (CHECK) - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_RES';
				$batch->add(new OrderReservedMailJob([
					'recordId' => $order->id
				]));
			} else if ($request->boolean('is_cod')) {
				$batch = Bus::batch([])->name("Order Placed by Customer (COD) - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_PLC';
				$batch->add(new OrderPlacedMailJob([
					'recordId' => $order->id
				]));
			}

			return response()->json([
				'success' => true,
				'message' => 'Order created successfully',
				'data' => $order
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create order: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/orders/{id}",
	 *     summary="Get order details",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Order ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$order = Order::where('customer_id', auth()->id())
		->where('id', $id)
		->first();

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => "Order not found."
			]);
		}

		/* Load relationships */
		$order->load([
			'customer:id,name,email,type,country_code,mobile_number',
			'customerAddress',
			'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status,accessory_item_charge',
			'orderProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
			'orderProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
			'orderProducts.accessoryCharges.accessoryItem.accessory:id,name',
			'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			'orderProducts.product.brand:id,name',
			'orderProducts.product.currency:id,symbol',
			'orderProducts.product.seoProductUrl:id,relational_id,relational_type,url',
			'orderProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'orderProducts.product.warrantyAttribute',
			'tracking',
			'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at'
		]);

		/* Mutate the data for each order product */
		foreach ($order->orderProducts as $orderProduct) {
			$product = $orderProduct->product;
			if ($product) {
				$product->images = is_array($product->images)
				? $product->images
				: (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);

				$product->brand_name = $product->brand->name ?? null;
				$product->currency_symbol = $product->currency->symbol ?? null;
				$product->warranty = $product->warrantyAttribute->attribute_value ?? null;
				$product->category_url = method_exists($product, 'category_url')
				? $product->category_url()
				: null;

				$product->parent_category_url = method_exists($product, 'parent_category_url')
				? $product->parent_category_url()
				: null;

				$product->url = $product->seoProductUrl->url ?? null;

				unset($product->brand, $product->currency);
			}

			$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)
			->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);

			$orderProduct->expectedShippingDate = $orderProduct->product_supplier
			? getDateRange($order->created_at, $orderProduct->product_supplier['delivery_days'])
			: null;

			if ($orderProduct->accessoryCharges) {
				$orderProduct->accessory_charges = $orderProduct->accessoryCharges->map(function ($charge) {
					return [
						'id' => $charge->id,
						'accessory_item_id' => $charge->accessory_item_id,
						'accessory_item_name' => $charge->accessoryItem->name ?? null,
						'accessory_item_price' => $charge->accessoryItem->price ?? null,
						'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
						'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
						'amount' => $charge->amount,
					];
				});

				unset($orderProduct->accessoryCharges);
			}
		}

		if (
			$order->status === 'Delivered' &&
			$orderProduct->product_supplier &&
			isset($orderProduct->product_supplier['return_policy'])
		) {
			$returnDays = (int) $orderProduct->product_supplier['return_policy'];
			$deliveryDate = \Carbon\Carbon::parse($order->updated_at);
			$returnUntil = $deliveryDate->copy()->addDays($returnDays);

			$orderProduct->is_returnable = now()->lte($returnUntil) ? 'yes' : 'no';
		} else {
			$orderProduct->is_returnable = 'yes';
		}

		foreach (['amount', 'tax_amount', 'discount', 'additional_discount_amount', 'cheque_discount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
			if (isset($order->$key)) {
				$order->$key = number_format($order->$key, 2, '.', '');
			}
		}

		return response()->json([
			'success' => true,
			'data' => $order
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/frontend/orders/{id}/status",
	 *     summary="Update order status",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(name="id", in="path", description="Order ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"status"},
	 *             @OA\Property(property="status", type="string"),
	 *             @OA\Property(property="notes", type="string")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Order status updated successfully"),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function updateStatus(Request $request, $id)
	{
		$request->validate([
			'status' => 'required|string|in:Cancelled',
			'notes' => 'required|string'
		]);

		$order = Order::find($id);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => "Order not found."
			]);
		}

		$allowedStatuses = [
			'Pending',
			'Confirmed',
			'Supplier Delivery',
			'International',
			'Export',
			'On hold',
			'Ready to ship'
		];

		if (!in_array($order->status, $allowedStatuses)) {
			return response()->json([
				'success' => false,
				'message' => 'This order has already been shipped, delivered, or cancelled. You can no longer cancel it.'
			], 400);
		}

		$order->update([
			'status' => $request->status,
		]);
		$order->orderProducts()->update(['status' => $request->status]);

		if ($request->status == 'Cancelled') {
			$batch = Bus::batch([])->name("Order Cancelled by Customer - #{$order->order_number}")->dispatch();
			$batch->options['queue'] = config('app.website') . '_ORD_CNCL';
			$batch->add(new OrderCancelledMailJob([
				'recordId' => $order->id
			]));
		}

		/* dd tracking entry */
		OrderTracking::create([
			'order_id' => $order->id,
			'status' => 'Cancelled by customer',
			'description' => $request->notes,
		]);

		return response()->json([
			'success' => true,
			'message' => 'Order cancelled successfully.',
			'data' => $order
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/orders/buy-it-again",
	 *     summary="Get products from last 5 delivered orders to buy again",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Products retrieved from previous delivered orders successfully"
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No delivered orders found with products"
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function buyItAgain(Request $request)
	{
		// Get authenticated customer
		$customerId = auth()->id();

		if (!$customerId) {
			return response()->json([
				'success' => false,
				'message' => 'User not authenticated.'
			], 401);
		}

		// Fetch last 5 delivered orders with products
		$deliveredOrders = Order::where('customer_id', $customerId)
		->whereIn('status', ['Delivered', 'Cancelled'])
		->orderByDesc('created_at')
		->take(5)
		->with([
			'orderProducts.product.productSuppliers',
			'orderProducts.product.currency',
			'orderProducts.product.brand'
		])
		->get();

		$addedProducts = collect();

		foreach ($deliveredOrders as $order) {
			foreach ($order->orderProducts as $orderProduct) {
				$product = $orderProduct->product;
				if (!$product) {
					continue;
				}

				// find a vendor_id if available
				$vendorId = $product->productSuppliers->first()->vendor_id ?? null;

				// build a request like addToCart expects
				$cartRequest = new Request([
					'product_id' => $product->id,
					'quantity'   => $orderProduct->quantity,
					'vendor_id'  => $vendorId,
				]);

				// call your CartController function
				app(\App\Http\Controllers\FrontEnd\CartController::class)->addToCart($cartRequest);

				$addedProducts->push([
					'product_id' => $product->id,
					'name'       => $product->name,
					'quantity'   => $orderProduct->quantity,
					'unit_price' => $orderProduct->unit_price,
					'brand_name' => $product->brand->name ?? null,
				]);
			}
		}

		if ($addedProducts->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No delivered orders found or no valid products available to buy again.'
			], 404);
		}

		return response()->json([
			'success' => true,
			'message' => 'Products added to your cart successfully.',
			'data'    => $addedProducts,
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/orders/tracking",
	 *     summary="Track order by order ID",
	 *     tags={"FrontEnd-Orders"},
	 * 		security={{"bearerAuth":{}}},
	 *     @OA\Parameter(name="order_number", in="query", required=true, description="Order number to track", @OA\Schema(type="string", example=12345)),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function orderTracking(Request $request)
	{
		$request->validate([
			'order_number' => 'required|string|exists:orders,order_number',
		]);

		$order = Order::where('order_number', $request->order_number)->first();

		$order->load([
			'customer:id,name,email,type,country_code,mobile_number',
			'customerDefaultAddress',
			'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status,accessory_item_charge',
			'orderProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
			'orderProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
			'orderProducts.accessoryCharges.accessoryItem.accessory:id,name',
			'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			'orderProducts.product.brand:id,name',
			'orderProducts.product.currency:id,symbol',
			'orderProducts.product.seoProductUrl:id,relational_id,relational_type,url',
			'orderProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'orderProducts.product.warrantyAttribute',
			'tracking',
			'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at'
		]);

		/* Mutate the data for each order product */
		foreach ($order->orderProducts as $orderProduct) {
			$product = $orderProduct->product;
			if ($product) {
				$product->images = is_array($product->images)
				? $product->images
				: (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);

				$product->brand_name = $product->brand->name ?? null;
				$product->currency_symbol = $product->currency->symbol ?? null;
				$product->warranty = $product->warrantyAttribute->attribute_value ?? null;
				$product->category_url = method_exists($product, 'category_url')
				? $product->category_url()
				: null;

				$product->parent_category_url = method_exists($product, 'parent_category_url')
				? $product->parent_category_url()
				: null;

				$product->url = $product->seoProductUrl->url ?? null;

				unset($product->brand, $product->currency);
			}

			$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)
			->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);

			$orderProduct->expectedShippingDate = $orderProduct->product_supplier
			? getDateRange($order->created_at, $orderProduct->product_supplier['delivery_days'])
			: null;

			if ($orderProduct->accessoryCharges) {
				$orderProduct->accessory_charges = $orderProduct->accessoryCharges->map(function ($charge) {
					return [
						'id' => $charge->id,
						'accessory_item_id' => $charge->accessory_item_id,
						'accessory_item_name' => $charge->accessoryItem->name ?? null,
						'accessory_item_price' => $charge->accessoryItem->price ?? null,
						'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
						'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
						'amount' => $charge->amount,
					];
				});

				unset($orderProduct->accessoryCharges);
			}
		}

		if (
			$order->status === 'Delivered' &&
			$orderProduct->product_supplier &&
			isset($orderProduct->product_supplier['return_policy'])
		) {
			$returnDays = (int) $orderProduct->product_supplier['return_policy'];
			$deliveryDate = \Carbon\Carbon::parse($order->updated_at);
			$returnUntil = $deliveryDate->copy()->addDays($returnDays);

			$orderProduct->is_returnable = now()->lte($returnUntil) ? 'yes' : 'no';
		} else {
			$orderProduct->is_returnable = 'yes';
		}

		foreach (['amount', 'tax_amount', 'discount', 'additional_discount_amount', 'cheque_discount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
			if (isset($order->$key)) {
				$order->$key = number_format($order->$key, 2, '.', '');
			}
		}

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => 'Order not found or access denied.',
				'data' => null,
			], 404);
		}

		return response()->json([
			'success' => true,
			'message' => 'Order with tracking info retrieved',
			'data' => $order,
		]);
	}

	// /**
	//  * @OA\Post(
	//  *     path="/api/frontend/compress-image-check",
	//  *     summary="Upload and compress cheque images",
	//  *     tags={"CompressImage"},
	//  *     security={{"bearerAuth":{}}},
	//  *
	//  *     @OA\RequestBody(
	//  *         required=true,
	//  *         @OA\MediaType(
	//  *             mediaType="multipart/form-data",
	//  *             @OA\Schema(
	//  *                 type="object",
	//  *                 required={"cheque_img","cheque_img_back"},
	//  *                 @OA\Property(
	//  *                     property="cheque_img",
	//  *                     type="string",
	//  *                     format="binary",
	//  *                     description="Cheque front image"
	//  *                 ),
	//  *                 @OA\Property(
	//  *                     property="cheque_img_back",
	//  *                     type="string",
	//  *                     format="binary",
	//  *                     description="Cheque back image"
	//  *                 )
	//  *             )
	//  *         )
	//  *     ),
	//  *
	//  *     @OA\Response(
	//  *         response=200,
	//  *         description="Cheque images uploaded and compressed successfully"
	//  *     ),
	//  *     @OA\Response(
	//  *         response=422,
	//  *         description="Validation failed"
	//  *     ),
	//  *     @OA\Response(
	//  *         response=401,
	//  *         description="Unauthorized"
	//  *     )
	//  * )
	//  */
	// public function compressImage(Request $request)
	// {

	// 	$validator = Validator::make($request->all(), [
	// 		'cheque_img'       => 'required|image|mimes:jpg,jpeg,png,webp|max:12240',
	// 		'cheque_img_back'  => 'required|image|mimes:jpg,jpeg,png,webp|max:12240',
	// 	]);

	// 	if ($validator->fails()) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Validation failed',
	// 			'errors'  => $validator->errors(),
	// 		], 422);
	// 	}


	// 	$path = env('STORAGE_ENV') . '/customer/orders';

	// 	$chequeFront = compressImageToS3(
	// 		$request,
	// 		'cheque_img',
	// 		$path
	// 	);

	// 	$chequeBack = compressImageToS3(
	// 		$request,
	// 		'cheque_img_back',
	// 		$path
	// 	);
	// 	// Upload failed check
	// 	if (!$chequeFront || !$chequeBack) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Image upload failed',
	// 		], 200);
	// 	}


	// 	return response()->json([
	// 		'success' => true,
	// 		'message' => 'Cheque images uploaded successfully',
	// 		'data' => [
	// 			'cheque_img'      => $chequeFront,
	// 			'cheque_img_back' => $chequeBack,
	// 		],
	// 	], 200);
	// }

	/**
	 * @OA\Post(
	 *     path="/api/frontend/save-cheque-upload",
	 *     operationId="saveChequeUpload",
	 *     tags={"Cheque Upload"},
	 *     summary="Upload cheque front and back images",
	 *     description="Uploads front and back cheque images and saves them against a session",
	 *
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"cheque_img","cheque_img_back"},
	 *                 @OA\Property(
	 *                     property="cheque_img",
	 *                     type="string",
	 *                     format="binary",
	 *                     description="Front side of cheque image (jpg, jpeg, png, webp)"
	 *                 ),
	 *                 @OA\Property(
	 *                     property="cheque_img_back",
	 *                     type="string",
	 *                     format="binary",
	 *                     description="Back side of cheque image (jpg, jpeg, png, webp)"
	 *                 ),
	 *                 @OA\Property(
	 *                     property="session_id",
	 *                     type="string",
	 *                     example="sess_123456",
	 *                     description="Optional session identifier"
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Cheque uploaded successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function saveChequeUpload(Request $request)
	{
		$request->validate([
			'cheque_img' => 'required|image|mimes:jpg,jpeg,png,webp|max:15360',
			'cheque_img_back' => 'required|image|mimes:jpg,jpeg,png,webp|max:15360',
			'session_id' => 'nullable|string|max:255',
		]);

		$chequeImg = uploadImageToWebpS3FromFile(
			$request,
			'cheque_img',
			env('STORAGE_ENV') . '/customer/orders'
		);

		$chequeImgBack = uploadImageToWebpS3FromFile(
			$request,
			'cheque_img_back',
			env('STORAGE_ENV') . '/customer/orders'
		);

		if (!$chequeImg || !$chequeImgBack) {
			return response()->json([
				'success' => false,
				'message' => 'Image upload failed',
			]);
		}

		$data = ChequeUpload::create([
			'cheque_img' => $chequeImg,
			'cheque_img_back' => $chequeImgBack,
			'session_id' => $request->session_id,
		]);

		return response()->json([
			'success' => true,
			'message' => 'Cheque images uploaded and saved successfully',
			'data' => $data,
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/get-cheque-uploads",
	 *     operationId="getChequeUploadsBySession",
	 *     tags={"Cheque Upload"},
	 *     summary="Get cheque uploads by session ID",
	 *     description="Returns all cheque uploads related to a session ID",
	 *     @OA\Parameter(
	 *         name="session_id",
	 *         in="query",
	 *         required=true,
	 *         description="Session identifier",
	 *         @OA\Schema(type="string", example="sess_123456")
	 *     ),
	 *     @OA\Response(response=200, description="Cheque uploads retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function getChequeUploadsBySession(Request $request)
	{
		$request->validate([
			'session_id' => 'required|string|max:255',
		]);

		$record = ChequeUpload::where('session_id', $request->session_id)->orderBy('created_at', 'desc')->first();

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => "No cheque uploads found for this session."
			]);
		}

		return response()->json([
			'success' => true,
			'message' => 'Cheque uploads retrieved successfully',
			'data' => $record,
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/user-stats",
	 *     operationId="getUserStats",
	 *     tags={"Frontend Orders"},
	 *     summary="Get total orders, wishlist count, and net term amount for authenticated user",
	 *     description="Returns total orders, total wishlist items, and total net term amount for the logged-in user",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="object",
	 *                 @OA\Property(property="total_orders", type="integer", example=10),
	 *                 @OA\Property(property="total_wishlist", type="integer", example=5),
	 *                 @OA\Property(property="total_net_term_amount", type="string", example="1234.56")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthenticated"
	 *     )
	 * )
	 */
	public function userStats(Request $request)
	{
		$userId = auth()->id();

		// Total orders
		$totalOrders = Order::where('customer_id', $userId)->count();

		// Total wishlist items
		$totalWishlist = DB::table('ec_wish_lists')
		->where('customer_id', $userId)
		->count();

		// Total net term amount from finances
		$totalNetTermAmount = DB::table('finances')
		->where('customer_id', $userId)
		->sum('available_credit_amount');

		return response()->json([
			'success' => true,
			'data' => [
				'total_orders' => $totalOrders,
				'total_wishlist' => $totalWishlist,
				'total_net_term_amount' => number_format($totalNetTermAmount, 2, '.', ''),
			]
		]);
	}
}
