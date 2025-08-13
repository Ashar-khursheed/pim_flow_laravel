<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\OrderTracking;

use App\Models\FrontEnd\Payment;
use App\Models\FrontEnd\Shipment;
use App\Models\FrontEnd\ShipmentProduct;
use App\Models\FrontEnd\CustomerAddress;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Jobs\Order\OrderPlacedMailJob;
use App\Jobs\Order\OrderConfirmationMailJob;
use App\Jobs\Order\OutDeliveryMailJob;
use App\Jobs\Order\OrderDeliveredMailJob;
use App\Jobs\Order\OrderCancelledMailJob;
use App\Jobs\Order\PartialOrderCancelledMailJob;

use App\Notifications\Orders\PartialOrderCancelledMail;

class OrderController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/orders",
	 *     summary="Get all orders with pagination and filters",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="status", in="query", description="Filter by order status.", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="payment_status", in="query", description="Filter by payment status.", @OA\Schema(type="string", enum={"Paid", "Unpaid", "Partially Paid"})),
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "order_number", "customer_name", "shipping_charge", "total_amount", "total_products", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Orders retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	// public function index(Request $request)
	// {
	// 	if ($request->filled('from_date') && $request->filled('to_date')) {
	// 		$from = $request->from_date . ' 00:00:00';
	// 		$to = $request->to_date . ' 23:59:59';

	// 		$recordsQuery = Order::query();
	// 		/* Filter by status */
	// 		if ($request->has('status')) {
	// 			$recordsQuery->where('status', $request->status);
	// 		}
	// 		$recordsQuery = $recordsQuery->whereBetween('created_at', [$from, $to])->pluck('id');

	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => __('msg_rec_list'),
	// 			'data' => $recordsQuery,
	// 		]);
	// 	}

	// 	$searchableColumns = ['id', 'order_number', 'customer_name'];
	// 	$sortableColumns = array_merge($searchableColumns, ['shipping_charge', 'total_amount', 'total_products', 'created_at', 'updated_at']);

	// 	$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
	// 	$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

	// 	/* Check if pagination requested */
	// 	$recordsQuery = Order::query();

	// 	/* Filter by status */
	// 	if ($request->has('status')) {
	// 		$recordsQuery->where('status', $request->status);
	// 	}

	// 	if ($request->filled('page') && $request->filled('length')) {

	// 		/* Join if customer_name is involved in search or sort */
	// 		if ($sortBy === 'customer_name' || ($request->filled('global') && in_array('customer_name', $searchableColumns))) {
	// 			$recordsQuery->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
	// 			$recordsQuery->select('orders.*');
	// 		}

	// 		/* Eager load relationships */
	// 		$recordsQuery->with([
	// 			'customer:id,name',
	// 			'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status',
	// 			'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
	// 			'orderProducts.product.brand:id,name',
	// 			'orderProducts.product.currency:id,symbol',
	// 			'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at',
	// 			'shipments',
	// 			'creator',
	// 			'updator',
	// 		    'nofraudResponse' // 👈 add this line

	// 		]);

	// 		/* Filter by payment status */
	// 		if ($request->has('payment_status')) {
	// 			switch ($request->payment_status) {
	// 				case 'Paid':
	// 				$recordsQuery->whereColumn('orders.paid_amount', '>=', 'orders.total_amount');
	// 				break;
	// 				case 'Unpaid':
	// 				$recordsQuery->where('orders.paid_amount', 0);
	// 				break;
	// 				case 'Partially Paid':
	// 				$recordsQuery->where('orders.paid_amount', '>', 0)
	// 				->whereColumn('orders.paid_amount', '<', 'orders.total_amount');
	// 				break;
	// 			}
	// 		}

	// 		/* Global search */
	// 		if ($request->filled('global')) {
	// 			$search = $request->input('global');
	// 			$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
	// 				foreach ($searchableColumns as $col) {
	// 					if ($col === 'customer_name') {
	// 						$q->orWhereHas('customer', function ($sub) use ($search) {
	// 							$sub->where('name', 'like', '%' . $search . '%');
	// 						});
	// 					} else {
	// 						$q->orWhere("orders.$col", 'like', '%' . $search . '%');
	// 					}
	// 				}
	// 			});
	// 		}

	// 		/* Sorting */
	// 		if ($sortBy === 'customer_name') {
	// 			$recordsQuery->orderBy('customers.name', $sortDir);
	// 		} else {
	// 			$recordsQuery->orderBy("orders.$sortBy", $sortDir);
	// 		}

	// 		/* Pagination */
	// 		$length = (int) $request->input('length');
	// 		$page = (int) $request->input('page');

	// 		$totalRecords = (clone $recordsQuery)->count();
	// 		$totalPages = (int) ceil($totalRecords / $length);

	// 		if ($page > $totalPages && $totalPages > 0) {
	// 			$page = 1;
	// 		}

	// 		$records = $recordsQuery
	// 		->offset(($page - 1) * $length)
	// 		->limit($length)
	// 		->get();

	// 		/* Transform results */
	// 		$records->transform(function ($record) {
	// 			$record->customer_name = $record->customer->name ?? null;
	// 			$record->created_by = $record->creator->name ?? null;
	// 			$record->updated_by = $record->updator->name ?? null;

	// 			unset($record->creator, $record->updator);

	// 			/* Process each product in order products */
	// 			foreach ($record->orderProducts as $orderProduct) {
	// 				$product = $orderProduct->product;
	// 				if ($product) {
	// 					$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
	// 					$product->brand_name = $product->brand->name ?? null;
	// 					$product->currency_symbol = $product->currency->symbol ?? null;
	// 					unset($product->brand, $product->currency);
	// 				}
	// 				$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)->only(['price', 'sale_price', 'delivery_days', 'return_policy']);
	// 				$orderProduct->expectedShippingDate = $orderProduct->product_supplier
	// 				? getDateRange($record->created_at, $orderProduct->product_supplier['delivery_days'])
	// 				: null;

	// 				/* Format numeric values to 2 decimal places */
	// 				foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
	// 					if (isset($quoteProduct->$key)) {
	// 						$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
	// 					}
	// 				}
	// 			}

	// 			foreach (['shipping_charge', 'amount', 'tax_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
	// 				if (isset($record->$key)) {
	// 					$record->$key = number_format($record->$key, 2, '.', '');
	// 				}
	// 			}

	// 			return $record;
	// 		});
	// 	} else {
	// 		/* No pagination: just fetch id and order_number */
	// 		$records = $recordsQuery->orderBy('order_number', 'asc')->get(['id', 'order_number']);
	// 		$totalRecords = $records->count();
	// 		$totalPages = 1;
	// 	}

	// 	return response()->json([
	// 		'success' => true,
	// 		'message' => __('msg_rec_list'),
	// 		'data' => $records,
	// 		'total_pages' => $totalPages,
	// 		'total_records' => $totalRecords,
	// 	]);
	// }
	public function index(Request $request)
	{
		if ($request->filled('from_date') && $request->filled('to_date')) {
			$from = $request->from_date . ' 00:00:00';
			$to = $request->to_date . ' 23:59:59';

			$recordsQuery = Order::query();

			if ($request->has('status')) {
				$recordsQuery->where('status', $request->status);
			}

			$recordsQuery = $recordsQuery->whereBetween('created_at', [$from, $to])->pluck('id');

			return response()->json([
				'success' => true,
				'message' => __('msg_rec_list'),
				'data' => $recordsQuery,
			]);
		}

		$searchableColumns = ['id', 'order_number', 'customer_name'];
		$sortableColumns = array_merge($searchableColumns, ['shipping_charge', 'total_amount', 'total_products', 'created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Order::query();

		if ($request->has('status')) {
			$recordsQuery->where('status', $request->status);
		}

		if ($request->filled('page') && $request->filled('length')) {

			if ($sortBy === 'customer_name' || ($request->filled('global') && in_array('customer_name', $searchableColumns))) {
				$recordsQuery->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
				$recordsQuery->select('orders.*');
			}

			$recordsQuery->with([
				'customer:id,name',
				'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status',
				'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'orderProducts.product.brand:id,name',
				'orderProducts.product.currency:id,symbol',
				'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at',
				'shipments',
				'creator',
				'updator',
				'nofraudResponse',
				'utm'
			]);

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

			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						if ($col === 'customer_name') {
							$q->orWhereHas('customer', function ($sub) use ($search) {
								$sub->where('name', 'like', '%' . $search . '%');
							});
						} else {
							$q->orWhere("orders.$col", 'like', '%' . $search . '%');
						}
					}
				});
			}

			if ($sortBy === 'customer_name') {
				$recordsQuery->orderBy('customers.name', $sortDir);
			} else {
				$recordsQuery->orderBy("orders.$sortBy", $sortDir);
			}

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

			$records->transform(function ($record) {
				$record->customer_name = $record->customer->name ?? null;
				$record->created_by = $record->creator->name ?? null;
				$record->updated_by = $record->updator->name ?? null;

				$response = $record->nofraudResponse->response ?? null;

				if (is_string($response)) {
					$data = json_decode($response, true);
				} elseif (is_array($response)) {
					$data = $response;
				} else {
					$data = [];
				}

				$record->nofraud_decision = $data['decision'] ?? null;
				unset($record->nofraudResponse);


				unset($record->creator, $record->updator);

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

					foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
						if (isset($orderProduct->$key)) {
							$orderProduct->$key = number_format($orderProduct->$key, 2, '.', '');
						}
					}
				}

				foreach (['shipping_charge', 'amount', 'tax_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
					if (isset($record->$key)) {
						$record->$key = number_format($record->$key, 2, '.', '');
					}
				}

				return $record;
			});
		} else {
			$records = $recordsQuery->orderBy('order_number', 'asc')->get(['id', 'order_number']);
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
	 *     path="/api/orders",
	 *     summary="Create a new order",
	 *     tags={"Orders"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"customer_id", "customer_address_id", "shipping_charge", "products"},
	 *             @OA\Property(property="customer_id", type="integer", example=1),
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
			'customer_id' => 'required|integer|exists:customers,id',
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

		$address = CustomerAddress::where('id', $request->customer_address_id)->where('customer_id', $request->customer_id)->first();

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
				'customer_id' => $request->customer_id,
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
				'created_by' => auth()->id(),
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

			DB::commit();

			$batch = Bus::batch([])->before(function (Batch $batch) {

			})->catch(function (Batch $batch, Throwable $e) {

			})->finally(function (Batch $batch) {

			})->name('Order Place')->dispatch();

			$batch->options['queue'] = config('app.website') . '_ORD_PLC';
			$batch->add(new OrderPlacedMailJob([
				'recordId' => $order->id
			]));

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

				/* Format numeric values to 2 decimal places */
				foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
					if (isset($quoteProduct->$key)) {
						$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
					}
				}
			}

			foreach (['shipping_charge', 'amount', 'tax_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
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
	 *     path="/api/orders/{id}",
	 *     summary="Get order details",
	 *     tags={"Orders"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Order ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Order details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function show($id)
	{
		$order = Order::find($id);

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
			'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at,payment_method',
			'shipments',
			'creator',
			'updator',
			'tracking',
			'nofraudResponse',
			'utm'
		]);

		/* Mutate the data for each order product */
		$order->created_by = $order->creator->name ?? null;
		$order->updated_by = $order->updator->name ?? null;
		unset($record->creator, $record->updator);

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

			$orderProduct -> nofraudResponse->response ?? null;
			$orderProduct-> nofraud_decision = $data['decision'] ?? null;
			unset($orderProduct->nofraudResponse);

			/* Format numeric values to 2 decimal places */
			foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
				if (isset($quoteProduct->$key)) {
					$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
				}
			}
		}

		foreach (['shipping_charge', 'amount', 'tax_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
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
	 *     path="/api/orders/{id}",
	 *     summary="Update an existing order (if not yet confirmed)",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Order ID", @OA\Schema(type="integer")),
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
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $orderId)
	{
		$order = Order::with('orderProducts')->find($orderId);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => 'Order not found'
			], 404);
		}

		$allowedStatuses = [
			'Pending', 'Confirmed', 'Supplier Delivery', 'International', 'Export', 'On hold', 'Ready to ship'
		];

		if (!in_array($order->status, $allowedStatuses)) {
			return response()->json([
				'success' => false,
				'message' => 'This order has already been shipped or delivered. You can no longer update it.'
			], 400);
		}

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

		$customerId = $order->customer_id;

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

			$taxAmount = round($orderAmount * ($request->tax_percentage / 100), 2);
			$totalAmount = $orderAmount + $taxAmount + $orderShipping;
			$paidAmount = $request->paid_amount ?? 0;
			$pendingAmount = $totalAmount - $paidAmount;

			$order->update([
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
				'pending_amount' => $pendingAmount
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
					'amount' => $total,
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $total + $product['shipping_charge'],
					'status' => 'Pending',
				]);
			}

			OrderTracking::create([
				'order_id' => $order->id,
				'status' => 'Order Updated By Backend Panel',
				'description' => 'Order has been successfully updated',
			]);

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

				/* Format numeric values to 2 decimal places */
				foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
					if (isset($quoteProduct->$key)) {
						$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
					}
				}
			}

			foreach (['shipping_charge', 'amount', 'tax_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
				if (isset($order->$key)) {
					$order->$key = number_format($order->$key, 2, '.', '');
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
	 *     path="/api/orders/{id}/status",
	 *     summary="Update order status",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="id", in="path", description="Order ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"status"},
	 *             @OA\Property(property="status", type="string"),
	 *             @OA\Property(property="notes", type="string")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Order status updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function updateStatus(Request $request, $id)
	{
		$order = Order::find($id);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => "Order not found."
			]);
		}

		$request->validate([
			'status' => 'required|string|in:Pending,Confirmed,Supplier Delivery,International,Export,On hold,Ready to ship,Pickups,Out for delivery,Delivered,Re-Attempt,Returned,Cancelled',
			'notes' => 'nullable|string'
		]);

		/* Validate shipment and quantity for delivery-related statuses */
		$deliveryStatuses = ['Pickups', 'Out for delivery', 'Delivered'];

		if (in_array($request->status, $deliveryStatuses)) {

			/* Check if order has any shipments */
			if (!$order->shipments()->exists()) {
				return response()->json([
					'success' => false,
					'message' => "Cannot mark order as {$request->status} because no shipments are available."
				]);
			}

			/* Calculate total quantity from shipmentProducts */
			$totalShippedQuantity = 0;

			foreach ($order->shipments as $shipment) {
				foreach ($shipment->shipmentProducts as $shipmentProduct) {
					$totalShippedQuantity += $shipmentProduct->quantity;
				}
			}

			if ($totalShippedQuantity !== $order->total_products) {
				return response()->json([
					'success' => false,
					'message' => "Cannot mark order as {$request->status} because total shipped quantity ({$totalShippedQuantity}) does not match total ordered products ({$order->total_products})."
				]);
			}
		}

		$oldStatus = $order->status;

		/* Update order and products */
		$order->update([
			'status' => $request->status,
		]);

		$order->orderProducts()->where('status', '!=', 'Cancelled')->update(['status' => $request->status]);

		/* Add tracking */
		OrderTracking::create([
			'order_id' => $order->id,
			'status' => $request->status,
			'description' => $request->notes ?? "Order status changed from {$oldStatus} to {$request->status}",
		]);

		if (in_array($request->status, ['Confirmed', 'Out for delivery', 'Delivered', 'Cancelled'])) {
			$batch = Bus::batch([])->before(function (Batch $batch) {

			})->catch(function (Batch $batch, Throwable $e) {

			})->finally(function (Batch $batch) {

			})->name('Order Mails')->dispatch();

			if ($request->status == 'Confirmed') {
				$batch->options['queue'] = config('app.website') . '_ORD_CNF';
				$batch->add(new OrderConfirmationMailJob([
					'recordId' => $order->id
				]));
			}

			if ($request->status == 'Out for delivery') {
				$batch->options['queue'] = config('app.website') . '_ORD_OUT';
				$batch->add(new OutDeliveryMailJob([
					'recordId' => $order->id
				]));
			}

			if ($request->status == 'Delivered') {
				$batch->options['queue'] = config('app.website') . '_ORD_DLVR';
				$batch->add(new OrderDeliveredMailJob([
					'recordId' => $order->id
				]));
			}

			if ($request->status == 'Cancelled') {
				$batch->options['queue'] = config('app.website') . '_ORD_CNCL';
				$batch->add(new OrderCancelledMailJob([
					'recordId' => $order->id
				]));
			}
		}

		return response()->json([
			'success' => true,
			'message' => 'Order status updated successfully',
			'data' => $order->fresh(['tracking'])
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/orders/{orderId}/products/{orderProductId}/status",
	 *     summary="Update specific item status",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="orderId", in="path", description="Order ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="orderProductId", in="path", description="Order product ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"status"},
	 *             @OA\Property(property="status", type="string"),
	 *             @OA\Property(property="notes", type="string")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Product status updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function updateProductStatus(Request $request, $orderId, $orderProductId)
	{
		$order = Order::find($orderId);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => "Order not found."
			]);
		}

		$orderProduct = $order->orderProducts()->find($orderProductId);

		if (!$orderProduct) {
			return response()->json([
				'success' => false,
				'message' => "Product not found in the order."
			]);
		}

		/* Prevent status update before order is confirmed */
		if ($order->status === 'Pending') {
			return response()->json([
				'success' => false,
				'message' => "Product status cannot be changed until the order is confirmed."
			]);
		}

		$request->validate([
			'status' => 'required|string|in:Supplier Delivery,International,Export,On hold,Ready to ship,Pickups,Partially Pickups,Out for delivery,Partially Out for delivery,Delivered,Partially Delivered,Re-Attempt,Returned,Cancelled,Out of Stock',
			'notes' => 'nullable|string'
		]);

		$fullStatuses = ['Pickups', 'Out for delivery', 'Delivered'];
		$partialStatuses = ['Partially Pickups', 'Partially Out for delivery', 'Partially Delivered'];

		if (in_array($request->status, array_merge($fullStatuses, $partialStatuses))) {

			$shipmentProducts = $orderProduct->shipmentProducts;

			if ($shipmentProducts->isEmpty()) {
				return response()->json([
					'success' => false,
					'message' => "Cannot mark product as '{$request->status}' because it has no shipment records."
				]);
			}

			$totalShipped = 0;
			foreach ($shipmentProducts as $shipmentProduct) {
				$totalShipped += $shipmentProduct->quantity;
			}

			if (in_array($request->status, $fullStatuses)) {
				if ($totalShipped !== $orderProduct->quantity) {
					return response()->json([
						'success' => false,
						'message' => "Cannot mark product as '{$request->status}' because shipped quantity ({$totalShipped}) does not match ordered quantity ({$orderProduct->quantity})."
					]);
				}
			} elseif (in_array($request->status, $partialStatuses)) {
				if ($totalShipped <= 0 || $totalShipped >= $orderProduct->quantity) {
					return response()->json([
						'success' => false,
						'message' => "Cannot mark product as '{$request->status}' because shipped quantity ({$totalShipped}) must be greater than 0 and less than ordered quantity ({$orderProduct->quantity})."
					]);
				}
			}
		}

		$oldStatus = $orderProduct->status;
		$orderProduct->status = $request->status;
		$orderProduct->save();

		/* Get all product statuses from this order */
		$productStatuses = $order->orderProducts()->pluck('status')->toArray();

		/* Check if all product statuses are the same */
		$allSame = count(array_unique($productStatuses)) === 1;

		if ($allSame) {
			$order->status = $productStatuses[0];
		} elseif (in_array('Delivered', $productStatuses) || in_array('Partially Delivered', $productStatuses)) {
			$order->status = 'Partially Delivered';
		}

		$order->save();

		/* Add tracking entry */
		OrderTracking::create([
			'order_id' => $order->id,
			'status' => "Product Status Updated",
			'description' => $request->notes ?? "Product '{$orderProduct->name}' status changed from {$oldStatus} to {$request->status}",
			'metadata' => [
				'product_id' => $orderProduct->id,
				'product_name' => $orderProduct->name,
				'old_status' => $oldStatus,
				'new_status' => $request->status
			]
		]);

		if ($request->status == 'Cancelled') {
			$batch = Bus::batch([])->before(function (Batch $batch) {

			})->catch(function (Batch $batch, Throwable $e) {

			})->finally(function (Batch $batch) {

			})->name('Order Mails')->dispatch();

			$batch->options['queue'] = config('app.website') . '_ORD_PART_CNCL';
			$batch->add(new PartialOrderCancelledMailJob([
				'recordId' => $orderProduct->id,
				'reason' => $request->notes,
			]));
		}

		return response()->json([
			'success' => true,
			'message' => 'Product status updated successfully',
			'data' => $orderProduct->fresh()
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/orders/{id}/shipments",
	 *     summary="Create a shipment for an order (supports partial delivery)",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="id", in="path", description="Order ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"products"},
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"order_product_id", "quantity"},
	 *                     @OA\Property(property="order_product_id", type="integer"),
	 *                     @OA\Property(property="quantity", type="integer")
	 *                 )
	 *             ),
	 *             @OA\Property(property="tracking_number", type="string"),
	 *             @OA\Property(property="carrier", type="string"),
	 *             @OA\Property(property="notes", type="string"),
	 *             @OA\Property(property="estimated_delivery_date", type="string", format="date", example="2025-07-09")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Shipment created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function createShipment(Request $request, $id)
	{
		/* Fetch order with related products */
		$order = Order::with('orderProducts')->find($id);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => 'Order not found.'
			], 404);
		}

		/* Prevent shipment creation before the order is confirmed */
		if ($order->status === 'Pending') {
			return response()->json([
				'success' => false,
				'message' => 'Shipment cannot be created while the order is still pending confirmation.'
			]);
		}

		/* Allow shipment creation only when the order is "Ready to ship" */
		// if ($order->status !== 'Ready to ship') {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => 'Shipment can only be created when the order status is "Ready to ship".'
		// 	]);
		// }

		/* Validate input */
		$request->validate([
			'products' => 'required|array|min:1',
			'products.*.order_product_id' => 'required|integer|exists:order_products,id',
			'products.*.quantity' => 'required|integer|min:1',
			'tracking_number' => 'nullable|string|max:255',
			'carrier' => 'nullable|string|max:255',
			'notes' => 'nullable|string|max:500',
		]);

		DB::beginTransaction();

		try {
			/* Create shipment record */
			$shipment = Shipment::create([
				'order_id' => $order->id,
				'shipment_number' => 'SHP-' . strtoupper(Str::random(8)),
				'tracking_number' => $request->tracking_number,
				'carrier' => $request->carrier,
				'notes' => $request->notes,
				'estimated_delivery_date' => $request->estimated_delivery_date
			]);

			/* Process each product */
			foreach ($request->products as $productData) {
				$orderProduct = OrderProduct::where('id', $productData['order_product_id'])
				->where('order_id', $order->id)
				->firstOrFail();

				if ($productData['quantity'] > $orderProduct->remaining_quantity) {
					throw new \Exception("Cannot ship more than remaining quantity for product ID {$orderProduct->id}");
				}

				/* Create shipment product */
				ShipmentProduct::create([
					'shipment_id' => $shipment->id,
					'order_product_id' => $orderProduct->id,
					'quantity' => $productData['quantity'],
				]);

				/* Update remaining quantity */
				$orderProduct->shipped_quantity += $productData['quantity'];
				$orderProduct->remaining_quantity -= $productData['quantity'];
				$orderProduct->save();
			}

			/* Update order delivery status */
			$order->save();

			/* Add tracking entry */
			OrderTracking::create([
				'order_id' => $order->id,
				'status' => $order->status,
				'description' => $request->notes ?? 'Shipment created with tracking number: ' . $request->tracking_number,
			]);

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Shipment created successfully',
				'data' => $shipment->load('shipmentProducts.orderProduct')
			], 201);
		} catch (\Exception $e) {
			DB::rollBack();
			return response()->json([
				'success' => false,
				'message' => 'Failed to create shipment: ' . $e->getMessage()
			], 500);
		}
	}
}