<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use App\Models\FrontEnd\ReturnOrderProduct;
use App\Models\FrontEnd\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
	 *                 @OA\Property(property="product_images", type="array", @OA\Items(type="string", format="binary")),
	 *                 @OA\Property(property="product_videos", type="array", @OA\Items(type="string", format="binary"))
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

		if ($orderProduct->status !== 'Delivered') {
			return response()->json([
				'success' => false,
				'message' => 'Only delivered products can be returned.'
			]);
		}

		$request->validate([
			'quantity' => 'required|integer|min:1|max:' . $orderProduct->shipped_quantity,
			'reason' => 'required|string',
			'description' => 'nullable|string',
			'product_images' => 'nullable|array',
			'product_images.*' => ['sometimes', 'file', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
			'product_videos' => 'nullable|array',
			'product_videos.*' => ['sometimes', 'file', 'mimes:mp4,mov,avi,webm', 'max:10240'],
		]);

		/* Upload media files and convert to array of URLs */
		$productImages = [];

		if ($request->hasFile('product_images')) {
			foreach ($request->file('product_images') as $index => $imageFile) {
				/*
				|------------------------------------------------------------
				| We need to temporarily rebind each image file to a Request
				| input key for the helper to process it correctly.
				|------------------------------------------------------------
				*/
				$tempRequest = new \Illuminate\Http\Request();
				$tempRequest->files->set('product_image_single', $imageFile);

				$uploadedUrl = uploadImageToWebpS3FromFile($tempRequest, 'product_image_single', env('STORAGE_ENV') . '/returns/images');

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
			'product_images' => $productImages,
			'product_videos' => $productVideos,
			'status' => 'requested',
		]);

		return response()->json([
			'success' => true,
			'message' => 'Return request created successfully',
			'data' => $return
		]);
	}
}
