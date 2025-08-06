<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\ReturnOrderProduct;

class ReportController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/report/orders",
	 *     summary="Get order report within a date range",
	 *     tags={"Reports"},
	 *     @OA\Parameter(name="from_date", in="query", required=true, @OA\Schema(type="string", format="date"), description="Start date (YYYY-MM-DD)"),
	 *     @OA\Parameter(name="to_date", in="query", required=true, @OA\Schema(type="string", format="date"), description="End date (YYYY-MM-DD)"),
	 *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'from_date' => 'required|date',
			'to_date' => 'required|date',
		]);

		$from = $request->from_date . ' 00:00:00';
		$to = $request->to_date . ' 23:59:59';

		$orderQuery = Order::query();
		$returnProductQuery = ReturnOrderProduct::query();

		/* Filter by status if provided */
		if ($request->filled('status')) {
			$orderQuery->where('status', $request->status);
			$returnProductQuery->where('status', $request->status);
		}

		/* Filter by date range */
		$orderQuery->whereBetween('created_at', [$from, $to]);
		$returnProductQuery->whereBetween('created_at', [$from, $to]);

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
}
