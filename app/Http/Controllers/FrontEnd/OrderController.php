<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\OrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
			$recordsQuery->with(['orderProducts:id,order_id,product_id,vendor_id,quantity',
				'orderProducts.product:id,name,images,sku,brand_id,price,sale_price,product_type,barcode,warranty_information,brand_id',
				'orderProducts.product.brand:id,name', 'payments', 'shipments']);

			/* Filter by status */
			if ($request->has('status')) {
				$recordsQuery->where('orders.status', $request->status);
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
						/* Decode image JSON only if it's a string */
						if (is_string($product->images)) {
							$product->images = json_decode($product->images, true);
						}

						/* Replace brand relation with just brand_name */
						$product->brand_name = $product->brand->name ?? null;
						unset($product->brand);
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
	 *             required={"customer_id", "customer_address_id", "shipping_charge", "products"},
	 *             @OA\Property(property="customer_id", type="integer", example=1),
	 *             @OA\Property(property="customer_address_id", type="integer", example="1"),
	 *             @OA\Property(property="shipping_charge", type="number", format="float", example=50.00),
	 *             @OA\Property(property="ship_all_at_once", type="boolean", example=true),
	 *             @OA\Property(property="separate_deliveries", type="boolean", example=false),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity", "unit_price"},
	 *                     @OA\Property(property="product_id", type="integer", example=101),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=5),
	 *                     @OA\Property(property="unit_price", type="number", format="float", example=199.99)
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
			'shipping_charge' => 'required|numeric|min:0',
			'ship_all_at_once' => 'nullable|boolean',
			'separate_deliveries' => 'nullable|boolean',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.unit_price' => 'required|numeric|min:0',
		]);

		DB::beginTransaction();

		try {
			$totalProducts = 0;
			$totalAmount = 0;

			foreach ($request->products as $product) {
				$totalProducts += $product['quantity'];
				$totalAmount += $product['quantity'] * $product['unit_price'];
			}

			$order = Order::create([
				'order_number' => 'ORD-' . strtoupper(Str::random(8)),
				'customer_id' => auth()->id() ?? $request->customer_id,
				'customer_address_id' => $request->customer_address_id,
				'shipping_charge' => $request->shipping_charge,
				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'ship_all_at_once' => $request->get('ship_all_at_once', true),
				'separate_deliveries' => $request->get('separate_deliveries', false),
				'is_paid' => false,
				'paid_amount' => 0,
				'pending_amount' => $totalAmount,
				'status' => 'Pending',
				'created_by' => 0,
				'updated_by' => null,
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
					'total_amount' => $total,
					'status' => 'Pending',
				]);
			}

			OrderTracking::create([
				'order_id' => $order->id,
				'status' => 'Order Created',
				'description' => 'Order has been successfully created',
			]);

			DB::commit();

			/* Load relationships */
			$order->load([
				'orderProducts:id,order_id,product_id,vendor_id,quantity',
				'orderProducts.product:id,name,images,sku,brand_id,price,sale_price,product_type,barcode,warranty_information,brand_id',
				'orderProducts.product.brand:id,name',
				'tracking'
			]);

			/* Mutate the data for each order product */
			foreach ($order->orderProducts as $orderProduct) {
				$product = $orderProduct->product;

				if ($product) {
					/* Decode images JSON string */
					$product->images = json_decode($product->images);

					/* Replace brand relation with brand_name */
					if ($product->brand) {
						$product->brand_name = $product->brand->name;
					}

					unset($product->brand); /* Remove full brand object */
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
		$order = Order::find($id);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => "Order not found."
			]);
		}

		/* Load relationships */
		$order->load([
			'orderProducts:id,order_id,product_id,vendor_id,quantity',
			'orderProducts.product:id,name,images,sku,brand_id,price,sale_price,product_type,barcode,warranty_information,brand_id',
			'orderProducts.product.brand:id,name',
			'tracking'
		]);

		/* Mutate the data for each order product */
		foreach ($order->orderProducts as $orderProduct) {
			$product = $orderProduct->product;

			if ($product) {
				/* Decode images JSON string */
				$product->images = json_decode($product->images);

				/* Replace brand relation with brand_name */
				if ($product->brand) {
					$product->brand_name = $product->brand->name;
				}

				unset($product->brand); /* Remove full brand object */
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
	 *     summary="Update an existing order (if not yet shipped)",
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
			'Pending', 'Confirmed', 'Supplier Delivery', 'International',
			'Export', 'On hold', 'Ready to ship'
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
				'message' => 'This order has already been shipped or delivered. You can no longer update it.'
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
				'status' => 'Order Updated',
				'description' => 'Order has been successfully updated',
			]);

			DB::commit();

			/* Reload updated order data */
			$order->load([
				'orderProducts:id,order_id,product_id,vendor_id,quantity',
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

}