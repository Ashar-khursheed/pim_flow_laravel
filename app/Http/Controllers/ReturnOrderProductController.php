<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\FrontEnd\ReturnOrderProduct;
use App\Models\FrontEnd\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReturnOrderProductController extends BaseController
{
	/**
	 * @OA\Put(
	 *     path="/api/return-products/{id}/inspect",
	 *     summary="Admin inspects and approves/rejects a return request",
	 *     tags={"Orders"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Return request ID", @OA\Schema(type="integer")),
	 *     @OA\RequestBody(required=true, @OA\JsonContent(
	 *         required={"status","comment"},
	 *         @OA\Property(property="status", type="string", enum={"inspected","approved","rejected"}, example="approved"),
	 *         @OA\Property(property="comment", type="string", example="Item in good condition, approving refund.")
	 *     )),
	 *     @OA\Response(response=200, description="Return inspected successfully")
	 * )
	 */
	public function inspectReturn(Request $request, $id)
	{
		$returnOrderProduct = ReturnOrderProduct::find($id);
		if (!$returnOrderProduct) {
			return response()->json([
				'success'=>false,
				'message'=>'Return request not found'
			]);
		}
		if (!in_array($request->status, ['inspected','approved','rejected'])) {
			return response()->json([
				'success'=>false,
				'message'=>'Invalid status'
			]);
		}
		$returnOrderProduct->status = $request->status;
		$returnOrderProduct->inspected_by = auth()->id();
		$returnOrderProduct->comment = $request->comment;
		$returnOrderProduct->updated_by = auth()->id();
		$returnOrderProduct->save();

		// Notify customer (example)
		// event(new \App\Events\ReturnStatusChanged($returnOrderProduct));

		return response()->json([
			'success'=>true,
			'message'=>'Return request updated',
			'data'=>$returnOrderProduct
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/return-products/{id}/refund",
	 *     summary="Finance confirms refund payment",
	 *     tags={"Orders"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Return request ID", @OA\Schema(type="integer")),
	 *     @OA\RequestBody(required=true, @OA\JsonContent(
	 *         required={"refund_status","refund_amount","refund_method"},
	 *         @OA\Property(property="refund_status", type="string", enum={"in_finance","refunded","refund_failed"}, example="refunded"),
	 *         @OA\Property(property="refund_amount", type="number", example=59.99),
	 *         @OA\Property(property="refund_method", type="string", example="UPI"),
	 *         @OA\Property(property="refund_date", type="string", format="date-time", example="2025-06-20T12:34:56Z")
	 *     )),
	 *     @OA\Response(response=200, description="Refund status updated")
	 * )
	 */
	public function refundReturn(Request $request, $id)
	{
		$returnOrderProduct = ReturnOrderProduct::find($id);
		if (!$returnOrderProduct) {
			return response()->json([
				'success'=>false,
				'message'=>'Return request not found'
			]);
		}
		$request->validate([
			'refund_status'=>'required|in:in_finance,refunded,refund_failed',
			'refund_amount'=>'required|numeric|min:0|max:'.$returnOrderProduct->orderProduct->unit_price * $returnOrderProduct->quantity,
			'refund_method'=>'required|string'
		]);

		$returnOrderProduct->refund_status = $request->refund_status;
		$returnOrderProduct->refund_amount = $request->refund_amount;
		$returnOrderProduct->refund_method = $request->refund_method;
		$returnOrderProduct->refund_date = now();
		$returnOrderProduct->updated_by = auth()->id();
		$returnOrderProduct->save();

		// Notify customer
		// event(new \App\Events\ReturnRefundProcessed($returnOrderProduct));

		return response()->json(['success'=>true,'message'=>'Refund status updated','data'=>$returnOrderProduct]);
	}
}
