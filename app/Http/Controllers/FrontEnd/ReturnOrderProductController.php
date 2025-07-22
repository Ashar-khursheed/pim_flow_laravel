<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use App\Models\FrontEnd\ReturnOrderProduct;
use App\Models\FrontEnd\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ReturnOrderProductController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/order-products/{order_product_id}/return",
	 *     summary="Create a return request for an order product",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(
	 *         name="order_product_id",
	 *         in="path",
	 *         required=true,
	 *         description="Order Product ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"quantity", "reason"},
	 *                 @OA\Property(property="quantity", type="integer", example=1),
	 *                 @OA\Property(property="reason", type="string", example="Defective product"),
	 *                 @OA\Property(property="description", type="string", example="Scratched screen"),
	 *                 @OA\Property(property="product_images[]", type="array", @OA\Items(type="string", format="binary")),
	 *                 @OA\Property(property="product_videos[]", type="array", @OA\Items(type="string", format="binary"))
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Return request created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request, $order_product_id)
	{
		$orderProduct = OrderProduct::find($order_product_id);

		if (!$orderProduct) {
			return response()->json([
				'success' => false,
				'message' => 'Order product not found.'
			]);
		}

		if ($orderProduct->status === 'Request Return' || $orderProduct->status === 'Partial Request Return') {
			return response()->json([
				'success' => false,
				'message' => 'Return request has already been initiated for this product.'
			]);
		}

		if ($orderProduct->status !== 'Delivered') {
			return response()->json([
				'success' => false,
				'message' => 'Only delivered products are eligible for return.'
			]);
		}

		// if ($orderProduct->status === 'Request Return') {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => 'A return request has already been initiated for this product.'
		// 	]);
		// }

		// if (!in_array($orderProduct->status, ['Delivered', 'Partial Request Return'])) {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => 'Only delivered products are eligible for return.'
		// 	]);
		// }

		$shippedQuantity = $orderProduct->shipmentProducts->sum('quantity');
		$returnedQuantity = $orderProduct->returnOrderProducts->sum('quantity');
		$remainingReturnable = $shippedQuantity - $returnedQuantity;

		if ($request->quantity > $remainingReturnable) {
			return response()->json([
				'success' => false,
				'message' => 'Return quantity exceeds the remaining delivered quantity.'
			]);
		}

		$request->validate([
			'quantity' => 'required|integer|min:1|max:' . $remainingReturnable,
			'reason' => 'required|string',
			'description' => 'nullable|string',
			'product_images' => 'nullable|array',
			'product_videos' => 'nullable|array',
			'product_images.*' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:2048',
			'product_videos.*' => 'nullable|file|mimes:mp4,mov,avi,webm|max:10240',
		]);

		/* Upload media files */
		$productImages = [];
		if ($request->hasFile('product_images')) {
			foreach ($request->file('product_images') as $index => $imageFile) {
				$tempRequest = new \Illuminate\Http\Request();
				$tempRequest->files->set('product_image_single', $imageFile);

				$uploadedUrl = uploadImageToWebpS3FromFile($tempRequest, 'product_image_single', env('STORAGE_ENV') . '/product-returns/images');

				if ($uploadedUrl) {
					$productImages[] = $uploadedUrl;
				}
			}
		}

		$productVideos = [];
		if ($request->hasFile('product_videos')) {
			foreach ($request->file('product_videos') as $video) {
				$productVideos[] = uploadFileToS3($video, env('STORAGE_ENV') . '/returns/videos');
			}
		}

		$return = ReturnOrderProduct::create([
			'refund_number' => 'R-' . strtoupper(Str::random(10)),
			'order_product_id' => $orderProduct->id,
			'quantity' => $request->quantity,
			'reason' => $request->reason,
			'description' => $request->description,
			'product_images' => json_encode($productImages),
			'product_videos' => json_encode($productVideos),
			'status' => 'requested',
		]);

		/* Update order product status */
		$totalRequested = $returnedQuantity + $request->quantity;
		if ($totalRequested >= $shippedQuantity) {
			$orderProduct->update(['status' => 'Request Return']);
		} else {
			$orderProduct->update(['status' => 'Partial Request Return']);
		}

		return response()->json([
			'success' => true,
			'message' => 'Return request created successfully.',
			'data' => $return
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/order-products/multiple-return",
	 *     summary="Create a return request for multiple order products",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"reason", "return_items"},
	 * 				   @OA\Property(property="return_items", type="array",
	 *                     @OA\Items(
	 *                         type="object",
	 *                         @OA\Property(property="order_product_id", type="integer", example=1),
	 *                         @OA\Property(property="quantity", type="integer", example=2)
	 *                     )
	 *                 ),
	 *                 @OA\Property(property="reason", type="string", example="Defective product"),
	 *                 @OA\Property(property="description", type="string", example="Screen is broken"),
	 *                 @OA\Property(property="product_images[]", type="array", @OA\Items(type="string", format="binary")),
	 *                 @OA\Property(property="product_videos[]", type="array", @OA\Items(type="string", format="binary"))
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Return request(s) created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function multipleReturn(Request $request)
	{
		$request->validate([
			'reason' => 'required|string',
			'description' => 'nullable|string',
			'return_items' => 'required|string',
			'product_images.*' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:2048',
			'product_videos.*' => 'nullable|file|mimes:mp4,mov,avi,webm|max:10240',
		]);

		$returnItems = json_decode($request->return_items, true);

		if (!is_array($returnItems) || empty($returnItems)) {
			return response()->json([
				'success' => false,
				'message' => 'Invalid or empty return_items.'
			], 422);
		}

		/* Validate all items before DB operations */
		foreach ($returnItems as $item) {
			$orderProductId = $item['order_product_id'] ?? null;
			$quantity = $item['quantity'] ?? 0;

			if (!$orderProductId || $quantity <= 0) {
				return response()->json([
					'success' => false,
					'message' => 'Invalid product ID or quantity.'
				], 422);
			}

			$orderProduct = OrderProduct::find($orderProductId);

			if (!$orderProduct) {
				return response()->json([
					'success' => false,
					'message' => "Order product ID $orderProductId not found."
				], 404);
			}

			if (!in_array($orderProduct->status, ['Delivered'])) {
				return response()->json([
					'success' => false,
					'message' => "Product has not been delivered yet."
				]);
			}

			if (in_array($orderProduct->status, ['Request Return', 'Partial Request Return'])) {
				return response()->json([
					'success' => false,
					'message' => "Return already initiated for Product."
				]);
			}

			$shippedQty = $orderProduct->shipmentProducts->sum('quantity');
			$returnedQty = $orderProduct->returnOrderProducts->sum('quantity');
			$remaining = $shippedQty - $returnedQty;

			if ($quantity > $remaining) {
				return response()->json([
					'success' => false,
					'message' => "Cannot return more than available quantity for {$orderProduct->product->name}."
				]);
			}
		}

		DB::beginTransaction();

		try {
			/* Upload product images */
			$productImages = [];
			if ($request->hasFile('product_images')) {
				foreach ($request->file('product_images') as $imageFile) {
					$tempRequest = new \Illuminate\Http\Request();
					$tempRequest->files->set('product_image_single', $imageFile);
					$uploadedUrl = uploadImageToWebpS3FromFile($tempRequest, 'product_image_single', env('STORAGE_ENV') . '/product-returns/images');
					if ($uploadedUrl) $productImages[] = $uploadedUrl;
				}
			}

			/* Upload product videos */
			$productVideos = [];
			if ($request->hasFile('product_videos')) {
				foreach ($request->file('product_videos') as $video) {
					$productVideos[] = uploadFileToS3($video, env('STORAGE_ENV') . '/returns/videos');
				}
			}

			/* Now apply DB changes */
			foreach ($returnItems as $item) {
				$orderProductId = $item['order_product_id'];
				$quantity = $item['quantity'];

				$orderProduct = OrderProduct::find($orderProductId);
				$shippedQty = $orderProduct->shipmentProducts->sum('quantity');
				$returnedQty = $orderProduct->returnOrderProducts->sum('quantity');
				$totalRequested = $returnedQty + $quantity;

				ReturnOrderProduct::create([
					'refund_number' => 'R-' . strtoupper(Str::random(10)),
					'order_product_id' => $orderProduct->id,
					'quantity' => $quantity,
					'reason' => $request->reason,
					'description' => $request->description,
					'product_images' => json_encode($productImages),
					'product_videos' => json_encode($productVideos),
					'status' => 'requested',
				]);

				/* Update order product status */
				$orderProduct->update([
					'status' => $totalRequested >= $shippedQty ? 'Request Return' : 'Partial Request Return'
				]);
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Return request(s) submitted successfully.'
			]);

		} catch (\Exception $e) {
			DB::rollBack();
			return response()->json([
				'success' => false,
				'message' => 'Something went wrong while processing the return request.',
				'error' => $e->getMessage()
			], 500);
		}
	}
}
