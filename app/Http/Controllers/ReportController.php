<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\Customer;
use App\Models\Utm;
use App\Models\FrontEnd\ReturnOrderProduct;

class ReportController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/report/orders",
	 *     summary="Get order report within a date range",
	 *     tags={"Reports"},
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date"), description="Start date (YYYY-MM-DD)"),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date"), description="End date (YYYY-MM-DD)"),
	 *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'from_date' => 'nullable|date',
			'to_date'   => 'nullable|date',
			'status'    => 'nullable|string'
		]);

		$orderQuery = Order::query()
			->where('is_reserved', 0)
			->where('status', '!=', 'Cancelled'); // ✅ exclude cancelled

		$returnProductQuery = ReturnOrderProduct::query()
			->where('status', '!=', 'Cancelled'); // ✅ exclude cancelled

		/* Filter by status if provided */
		if ($request->filled('status')) {
			$orderQuery->where('status', $request->status);
			$returnProductQuery->where('status', $request->status);
		}

		/* Filter by date range */
		if ($request->filled('from_date') || $request->filled('to_date')) {
			$from = $request->from_date ? $request->from_date . ' 00:00:00' : '1970-01-01 00:00:00';
			$to   = $request->to_date   ? $request->to_date   . ' 23:59:59' : now()->endOfDay()->toDateTimeString();

			$orderQuery->whereBetween('created_at', [$from, $to]);
			$returnProductQuery->whereBetween('created_at', [$from, $to]);
		}

		/* Aggregations */
		$orderCount = $orderQuery->count();
		$totalAmount = $orderQuery->sum('total_amount');
		$totalPaid = $orderQuery->sum('paid_amount');
		$totalPending = $orderQuery->sum('pending_amount');
		$totalReturnProductCount = $returnProductQuery->sum('quantity');

		/* Response data */
		$data = [
			'order_count' => $orderCount,
			'total_amount' => $totalAmount,
			'total_paid' => $totalPaid,
			'total_pending' => $totalPending,
			'total_return_product_count' => $totalReturnProductCount,
		];

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $data,
		]);
	}


	/**
	 * @OA\Get(
	 *     path="/api/report/stats/reserved",
	 *     summary="Get order report within a date range",
	 *     tags={"Reports"},
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date"), description="Start date (YYYY-MM-DD)"),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date"), description="End date (YYYY-MM-DD)"),
	 *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function reservedOrders(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'from_date' => 'nullable|date',
			'to_date'   => 'nullable|date',
			'status'    => 'nullable|string'
		]);

		$orderQuery = Order::query()->where('is_reserved', 1); // ✅ only reserved orders
		$returnProductQuery = ReturnOrderProduct::query();

		/* Filter by status if provided */
		if ($request->filled('status')) {
			$orderQuery->where('status', $request->status);
			$returnProductQuery->where('status', $request->status);
		}

		/* Filter by date range */
		if ($request->filled('from_date') || $request->filled('to_date')) {
			$from = $request->from_date ? $request->from_date . ' 00:00:00' : '1970-01-01 00:00:00';
			$to   = $request->to_date   ? $request->to_date   . ' 23:59:59' : now()->endOfDay()->toDateTimeString();

			$orderQuery->whereBetween('created_at', [$from, $to]);
			$returnProductQuery->whereBetween('created_at', [$from, $to]);
		}

		/* Aggregations */
		$orderCount = $orderQuery->count();
		$totalAmount = $orderQuery->sum('total_amount');
		$totalPaid = $orderQuery->sum('paid_amount');
		$totalPending = $orderQuery->sum('pending_amount');
		$totalReturnProductCount = $returnProductQuery->sum('quantity');

		/* Response data */
		$data = [
			'order_count' => $orderCount,
			'total_amount' => $totalAmount,
			'total_paid' => $totalPaid,
			'total_pending' => $totalPending,
			'total_return_product_count' => $totalReturnProductCount,
		];

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $data,
		]);
	}


	/**
	 * @OA\Get(
	 *     path="/api/report/utms",
	 *     summary="Get utm report within a date range",
	 *     tags={"Reports"},
	 *     @OA\Parameter(name="utm_campaign", in="query", required=true, @OA\Schema(type="string")),
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date"), description="Start date (YYYY-MM-DD)"),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date"), description="End date (YYYY-MM-DD)"),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function indexUtms(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'utm_campaign' => 'required|string',
			'from_date' => 'nullable|date',
			'to_date'   => 'nullable|date',
		]);

		/* Base query */
		$recordsQuery = Utm::with('orders')->where('utm_campaign', $request->utm_campaign);

		/* Filter by date range */
		if ($request->filled('from_date') || $request->filled('to_date')) {
			$from = $request->from_date ? $request->from_date . ' 00:00:00' : '1970-01-01 00:00:00';
			$to   = $request->to_date   ? $request->to_date   . ' 23:59:59' : now()->endOfDay()->toDateTimeString();

			$recordsQuery->whereBetween('created_at', [$from, $to]);
		}

		$utms = $recordsQuery->get();

		/* Aggregations */
		$utmCount = $utms->count();
		$allOrders = $utms->flatMap->orders;

		$orderCount = $allOrders->count();
		$totalOrderAmount = $allOrders->sum('total_amount');
		$actualTotalOrderAmount = $allOrders->where('status', '!=', 'Cancelled')->sum('total_amount');
		$conversionRate = $utmCount > 0 ? ($orderCount / $utmCount) * 100 : 0;

		/* Customer-level breakdown */
		$customers = $allOrders
		->groupBy('customer_id')
		->map(function ($orders) {
			$customer = $orders->first()->customer;
			return [
				'order_numbers' => $orders->pluck('order_number')->values(),
				'id' => $customer?->id,
				'name' => $customer?->name,
				'total_orders' => $orders->count(),
				'total_order_value' => $orders->sum('total_amount'),
				'total_order_value_without_cancelled' => $orders->where('status', '!=', 'Cancelled')->sum('total_amount'),
			];
		})
		->values();

		$products = $allOrders->where('status', '!=', 'Cancelled')->flatMap->orderProducts->groupBy('product_id')->map(function ($orderProducts) {
			$product = $orderProducts->first()->product;
			return [
				'product_name' => $product?->name,
				'sold_count'   => $orderProducts->sum('quantity'),
			];
		})
		->sortByDesc('sold_count')
		->values();

		/* Response data */
		$data = [
			'utm_count' => $utmCount,
			'order_count' => $orderCount,
			'total_order_amount' => $totalOrderAmount,
			'actual_total_order_amount' => $actualTotalOrderAmount,
			'conversion_rate' => round($conversionRate, 2) . '%',
			'customers' => $customers,
			'products' => $products,
		];

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $data,
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/report/customer-utms",
	 *     summary="Get customer utm report within a date range",
	 *     tags={"Reports"},
	 *     @OA\Parameter(name="utm_campaign", in="query", required=true, @OA\Schema(type="string")),
	 *     @OA\Parameter(name="customer_id", in="query", required=true, @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date"), description="Start date (YYYY-MM-DD)"),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date"), description="End date (YYYY-MM-DD)"),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function indexCustomerUtms(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'customer_id'   => 'required|integer|exists:customers,id',
			'utm_campaign'  => 'required|string',
			'from_date'     => 'nullable|date',
			'to_date'       => 'nullable|date',
		]);

		/* Base query */
		$recordsQuery = Utm::with(['orders.orderProducts.product'])
		->where('utm_campaign', $request->utm_campaign);

		/* Filter by date range */
		if ($request->filled('from_date') || $request->filled('to_date')) {
			$from = $request->from_date ? $request->from_date . ' 00:00:00' : '1970-01-01 00:00:00';
			$to   = $request->to_date   ? $request->to_date   . ' 23:59:59' : now()->endOfDay()->toDateTimeString();

			$recordsQuery->whereBetween('created_at', [$from, $to]);
		}

		$utms = $recordsQuery->get();

		/* All orders for given customer */
		$orders = $utms->flatMap->orders->where('customer_id', $request->customer_id);

		/* Aggregations */
		$totalOrderAmount = $orders->sum('total_amount');
		$actualTotalOrderAmount = $orders->where('status', '!=', 'Cancelled')->sum('total_amount');
		$totalOrderCountWithoutCancelled = $orders->where('status', '!=', 'Cancelled')->count();

		/* Customer details */
		$customer = Customer::with('customerAddress')->find($request->customer_id);
		$customerCreatedAt = $customer->created_at->format('Y-m-d H:i:s');
		$customerDefaultAddress = $customer->customerAddress->where('is_default', 1)->first();
		$emailSubscribed = $customer->newsLetter ? 'Yes' : 'No';

		/* Orders detail */
		$orderDetails = $orders->map(function ($order) {
			/* Payment status */
			if ($order->paid_amount >= $order->total_amount) {
				$orderPaymentStatus = 'Paid';
			} elseif ($order->paid_amount == 0) {
				$orderPaymentStatus = 'Unpaid';
			} else {
				$orderPaymentStatus = 'Partially Paid';
			}

			/* Order products */
			$orderProducts = $order->orderProducts->map(function ($orderProduct) {
				$vendorProductSupplier = $orderProduct->getVendorProductSupplierAttribute();
				return [
					'product_name'   => $vendorProductSupplier?->name ?? $orderProduct->product?->name,
					'quantity'       => $orderProduct->quantity,
					'amount'         => $orderProduct->amount,
					'shipping_charge'=> $orderProduct->shipping_charge,
					'total_amount'   => $orderProduct->total_amount,
				];
			});

			return [
				'order_number'                => $order->order_number,
				'created_at'                  => $order->created_at->format('Y-m-d H:i:s'),
				'order_payment_status'        => $orderPaymentStatus,
				'order_amount'                => $order->total_amount,
				'order_products'              => $orderProducts,
			];
		})->sortByDesc('created_at')->values();

		/* Response data */
		$data = [
			'stats' => [
				'total_order_amount' => $totalOrderAmount,
				'actual_total_order_amount' => $actualTotalOrderAmount,
				'total_order_count_without_cancelled' => $totalOrderCountWithoutCancelled,
				'created_at' => $customerCreatedAt,
			],
			'orders' => $orderDetails,
			'customer' => [
				'name'         => $customer->name,
				'email'        => $customer->email,
				'country_code' => $customer->country_code,
				'mobile_number'=> $customer->mobile_number,
				'default_address' => $customerDefaultAddress,
				'email_subscribed' => $emailSubscribed,
			],
		];

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $data,
		]);
	}
}
