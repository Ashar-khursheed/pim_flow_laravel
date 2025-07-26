<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\OrderTracking;
use App\Models\FrontEnd\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Notifications\Orders\OrderPlacedMail;
use App\Notifications\Orders\OrderCancelledMail;

class OrderController extends BaseController
{
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
				'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status',
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
					$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)->only(['price', 'sale_price', 'delivery_days', 'return_policy']);
					$orderProduct->expectedShippingDate = $orderProduct->product_supplier
					? getDateRange($record->created_at, $orderProduct->product_supplier['delivery_days'])
					: null;
				}
				foreach (['amount', 'tax_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
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
	 *         @OA\JsonContent(
	 *             required={"customer_address_id", "tax_percentage", "products"},
	 *             @OA\Property(property="customer_address_id", type="integer", example="1"),
	 *             @OA\Property(property="tax_percentage", type="number", example=5),
	 *             @OA\Property(property="ship_all_at_once", type="boolean", example=true),
	 *             @OA\Property(property="separate_deliveries", type="boolean", example=false),
	 *             @OA\Property(property="paid_amount", type="number", format="float", example=199.99),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity", "unit_price", "shipping_charge"},
	 *                     @OA\Property(property="product_id", type="integer", example=101),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=5),
	 *                     @OA\Property(property="unit_price", type="number", format="float", example=199.99),
	 *                     @OA\Property(property="shipping_charge", type="number", format="float", example=50.00)
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
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'tax_percentage' => 'required|numeric|min:0',
			'ship_all_at_once' => 'nullable|boolean',
			'separate_deliveries' => 'nullable|boolean',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.unit_price' => 'required|numeric|min:0',
			'products.*.shipping_charge' => 'required|numeric|min:0',
		]);

		$customerId = auth()->id();

		$address = CustomerAddress::where('id', $request->customer_address_id)->where('customer_id', $customerId)->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}

		DB::beginTransaction();

		try {
			$totalProducts = 0;
			$orderAmount = 0;
			$orderShipping = 0;

			foreach ($request->products as $product) {
				$totalProducts += $product['quantity'];
				$orderAmount += $product['quantity'] * $product['unit_price'];
				$orderShipping += $product['shipping_charge'];
			}
			/* Get the latest order by ID (most recent) */
			$latestOrder = Order::orderBy('id', 'desc')->first();

			/* Generate the next order number */
			if ($latestOrder && is_numeric($latestOrder->order_number)) {
				$orderNumber = (int) $latestOrder->order_number + 1;
			} else {
				$website = config('app.website');
				$orderNumber = $website === 'US' ? 10001 : ($website === 'UAE' ? 1001 : 101);
			}

			$taxAmount = round($orderAmount * ($request->tax_percentage / 100), 2);
			$totalAmount = $orderAmount + $taxAmount + $orderShipping;
			$paidAmount = $request->paid_amount ?? 0;
			$pendingAmount = $totalAmount - $paidAmount;

			$order = Order::create([
				'order_number' => $orderNumber,
				'customer_id' => $customerId,
				'customer_address_id' => $request->customer_address_id,
				'shipping_charge' => $orderShipping,
				'amount' => $orderAmount,
				'tax_percentage' => $request->tax_percentage,
				'tax_amount' => $taxAmount,
				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'ship_all_at_once' => $request->get('ship_all_at_once', true),
				'separate_deliveries' => $request->get('separate_deliveries', false),
				'paid_amount' => $paidAmount,
				'is_paid' => $pendingAmount <= 0,
				'pending_amount' => $pendingAmount,
				'status' => 'Pending',
				'created_by' => 0,
			]);

			foreach ($request->products as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				OrderProduct::create([
					'order_id' => $order->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'shipped_quantity' => 0,
					'remaining_quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'amount' => $total,
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $total + $product['shipping_charge'],
					'status' => 'Pending',
				]);
			}

			OrderTracking::create([
				'order_id' => $order->id,
				'status' => 'Order Created',
				'description' => 'Order has been successfully created',
			]);

			auth()->user()->notify(new OrderPlacedMail($order));

			DB::commit();

			/* Load relationships */
			$order->load([
				'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status',
				'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'orderProducts.product.brand:id,name',
				'orderProducts.product.currency:id,symbol',
				'tracking',
				'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at'
			]);

			/* Mutate the data for each order product */
			foreach ($order->orderProducts as $orderProduct) {
				$product = $orderProduct->product;
				if ($product) {
					$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
					$product->brand_name = $product->brand->name ?? null;
					$product->currency_symbol = $product->currency->symbol ?? null;
					unset($product->brand, $product->currency);
				}
				$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)->only(['price', 'sale_price', 'delivery_days', 'return_policy']);
				$orderProduct->expectedShippingDate = $orderProduct->product_supplier
				? getDateRange($order->created_at, $orderProduct->product_supplier['delivery_days'])
				: null;
			}

			foreach (['amount', 'tax_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
				if (isset($order->$key)) {
					$order->$key = number_format($order->$key, 2, '.', '');
				}
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
		$order = Order::where('customer_id', auth()->id())->where('id', $id)->first();
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
			'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status',
			'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			'orderProducts.product.brand:id,name',
			'orderProducts.product.currency:id,symbol',
			'orderProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'tracking',
			'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at'
		]);

		/* Mutate the data for each order product */
		foreach ($order->orderProducts as $orderProduct) {
			$product = $orderProduct->product;
			if ($product) {
				$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
				$product->brand_name = $product->brand->name ?? null;
				$product->currency_symbol = $product->currency->symbol ?? null;
				unset($product->brand, $product->currency);
			}
			$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)->only(['price', 'sale_price', 'delivery_days', 'return_policy']);
			$orderProduct->expectedShippingDate = $orderProduct->product_supplier
			? getDateRange($order->created_at, $orderProduct->product_supplier['delivery_days'])
			: null;
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

		foreach (['amount', 'tax_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
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
	 *     path="/api/frontend/orders/{id}",
	 *     summary="Update an existing order (if not yet confirmed)",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Order ID", @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"customer_address_id", "shipping_charge", "products"},
	 *             @OA\Property(property="customer_address_id", type="integer", example="4"),
	 *             @OA\Property(property="shipping_charge", type="number", format="float", example=75.00),
	 *             @OA\Property(property="ship_all_at_once", type="boolean", example=false),
	 *             @OA\Property(property="separate_deliveries", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity", "unit_price"},
	 *                     @OA\Property(property="product_id", type="integer", example=101),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=3),
	 *                     @OA\Property(property="unit_price", type="number", format="float", example=249.99)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $orderId)
	{
		$allowedStatuses = [
			'Pending'
		];

		$order = Order::with('orderProducts')->find($orderId);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => 'Order not found'
			], 404);
		}

		if (!in_array($order->status, $allowedStatuses)) {
			return response()->json([
				'success' => false,
				'message' => 'This order has already been confirmed or processed and cannot be updated.'
			], 400);
		}

		$request->validate([
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'shipping_charge' => 'required|numeric|min:0',
			'ship_all_at_once' => 'nullable|boolean',
			'separate_deliveries' => 'nullable|boolean',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.unit_price' => 'required|numeric|min:0',
		]);

		$customerId = auth()->id();

		$address = CustomerAddress::where('id', $request->customer_address_id)->where('customer_id', $customerId)->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}

		DB::beginTransaction();

		try {
			$totalProducts = 0;
			$totalAmount = 0;

			foreach ($request->products as $product) {
				$totalProducts += $product['quantity'];
				$totalAmount += $product['quantity'] * $product['unit_price'];
			}

			$order->update([
				'customer_address_id' => $request->customer_address_id,
				'shipping_charge' => $request->shipping_charge,
				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'ship_all_at_once' => $request->get('ship_all_at_once', true),
				'separate_deliveries' => $request->get('separate_deliveries', false),
				'pending_amount' => $totalAmount,
			]);

			/* Delete existing products and re-insert */
			OrderProduct::where('order_id', $order->id)->delete();

			foreach ($request->products as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				OrderProduct::create([
					'order_id' => $order->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'shipped_quantity' => 0,
					'remaining_quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'total_amount' => $total,
					'status' => 'Pending',
				]);
			}

			OrderTracking::create([
				'order_id' => $order->id,
				'status' => 'Order Updated By Customer',
				'description' => 'Order has been successfully updated',
			]);

			DB::commit();

			/* Reload updated order data */
			$order->load([
				'orderProducts:id,order_id,product_id,vendor_id,quantity,status',
				'orderProducts.product:id,name,images,sku,brand_id,price,sale_price,product_type,barcode,warranty_information,brand_id',
				'orderProducts.product.brand:id,name',
				'tracking'
			]);

			/* Mutate */
			foreach ($order->orderProducts as $orderProduct) {
				$product = $orderProduct->product;

				if ($product) {
					$product->images = json_decode($product->images);
					if ($product->brand) {
						$product->brand_name = $product->brand->name;
					}
					unset($product->brand);
				}
			}

			return response()->json([
				'success' => true,
				'message' => 'Order updated successfully',
				'data' => $order
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to update order: ' . $e->getMessage()
			], 500);
		}
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
			'Pending', 'Confirmed', 'Supplier Delivery', 'International',
			'Export', 'On hold', 'Ready to ship'
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
			$order->customer->notify(new OrderCancelledMail($order));
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
	public function buyItAgain()
	{
		$customer = auth()->user();

		$deliveredOrders = $customer->orders()->where('status', 'Delivered')->orderByDesc('created_at')->take(5)->with(['orderProducts.product'])->get();

		$products = collect();

		foreach ($deliveredOrders as $order) {
			foreach ($order->orderProducts as $orderProduct) {
				if ($orderProduct->product) {
					$images = null;
					if (is_string($orderProduct->product->images)) {
						$images = json_decode($orderProduct->product->images, true);
					} elseif (is_array($orderProduct->product->images)) {
						$images = $orderProduct->product->images;
					}

					$products->push([
						'product_id' => $orderProduct->product->id,
						'name' => $orderProduct->product->name,
						'quantity' => $orderProduct->quantity,
						'unit_price' => $orderProduct->unit_price,
						'images' => $images,
						'brand_name' => $orderProduct->product->brand->name ?? null,

					]);
				}
			}
		}

		if ($products->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No delivered orders found or no valid products available to buy again.'
			], 404);
		}

		return response()->json([
			'success' => true,
			'message' => 'Products retrieved from your previous orders.',
			'data' => $products
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/orders/tracking",
	 *     summary="Track order by order ID",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(name="order_number", in="query", required=true, description="Order number to track", @OA\Schema(type="string", example=12345)),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function orderTracking(Request $request)
	{
		$request->validate([
			'order_number' => 'required|string|exists:orders,order_number',
		]);

		$order = Order::with(['tracking'])->where('order_number', $request->order_number)->first();

		if (!$orderTracking) {
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
}