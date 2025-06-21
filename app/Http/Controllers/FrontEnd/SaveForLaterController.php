<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;
use App\Models\FrontEnd\Cart;
use App\Models\FrontEnd\SaveForLater;
use App\Models\Product;


class SaveForLaterController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/save-for-later",
	 *     summary="Move a product from cart to Save for Later",
	 *     tags={"Frontend-Save For Later"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id"},
	 *             @OA\Property(property="product_id", type="integer", example=123)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product has been moved to Save for Later",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product has been moved to Save for Later.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found in cart",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product not found in cart.")
	 *         )
	 *     )
	 * )
	 */

	public function saveForLater(Request $request)
	{
		$request->validate([
			'product_id' => 'required|exists:ec_products,id',
		]);

		$userId = auth()->id();

		 // Check if customer is authenticated
		if (!$userId) {
			return response()->json([
				'message' => 'Customer not authenticated.',
			], 401);
		}

		 // Check if product is in the cart
		$cartItem = Cart::where('user_id', $userId)
		->where('product_id', $request->product_id)
		->first();

		if (!$cartItem) {
			return response()->json([
				'message' => 'Product not found in cart.'
			], 404);
		}


		 // Move to save for later
		SaveForLater::updateOrCreate(
			[
				'user_id' => $userId,
				'product_id' => $request->product_id,
			],
			[
				'quantity' => $cartItem->quantity,
			]
		);

		$cartItem->delete();

		return response()->json([
			'message' => 'Product has been moved to Save for Later.',
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/save-for-later",
	 *     summary="Get all products saved for later by the user",
	 *     tags={"Frontend-Save For Later"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of saved products",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Saved for Later Products retrieved successfully."),
	 *             @OA\Property(property="product", type="array", @OA\Items(
	 *                 @OA\Property(property="id", type="integer", example=123),
	 *                 @OA\Property(property="name", type="string", example="Sample Product"),
	 *                 @OA\Property(property="price", type="number", format="float", example=99.99),
	 *                 @OA\Property(property="currency_title", type="string", example="$"),
	 *                 @OA\Property(property="total_reviews", type="integer", example=10),
	 *                 @OA\Property(property="avg_rating", type="number", format="float", example=4.5)
	 *             ))
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No products saved for later",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="No products saved for later.")
	 *         )
	 *     )
	 * )
	 */
	public function showSaveForLater(Request $request)
	{
		// Get the logged-in user
		$userId = auth()->id();

		// Fetch all saved products for the user
		$savedProducts = SaveForLater::where('user_id',  $user)
									->with('product')  // Assuming `product` is the relationship
									->get();

									if ($savedProducts->isEmpty()) {
										return response()->json([
											'message' => 'No products saved for later.'
										], 404);
									}

		// Return the saved products data
									$productsData = $savedProducts->map(function ($item) {
			$product = $item->product; // Get the product

			// Calculate the total reviews and average rating
			$totalReviews = $product->reviews->count();
			$avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
			$product->total_reviews = $totalReviews;
			$product->avg_rating = $avgRating;

			// Add currency details
			if ($product->currency) {
				$product->currency_title = $product->currency->is_prefix_symbol
				? $product->currency->title
				: $product->price . ' ' . $product->currency->title;
			} else {
				$product->currency_title = $product->price; // Fallback if no currency found
			}

			return $product; // Return the modified product data
		});

									return response()->json([
										'message' => 'Saved for Later Products retrieved successfully.',
										'product' => $productsData
									], 200);
    }

	
	/**
	 * @OA\Delete(
	 *     path="/api/frontend/save-for-later",
	 *     summary="Remove a product from Save for Later",
	 *     tags={"Frontend-Save For Later"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id"},
	 *             @OA\Property(property="product_id", type="integer", example=123)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product has been removed from Save for Later",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product has been removed from Save for Later.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found in Save for Later",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product not found in Save for Later.")
	 *         )
	 *     )
	 * )
	 */
	
	 public function removeFromSaveForLater($product_id)
	 {
		 // Optional: Check if the product exists in the products table
		 if (!\DB::table('ec_products')->where('id', $product_id)->exists()) {
			 return response()->json([
				 'message' => 'Product does not exist.'
			 ], 404);
		 }
	 
		 // Get the logged-in user ID
		 $userId = auth()->id();
	 
		 // Find the saved product
		 $savedProduct = SaveForLater::where('user_id', $userId)
			 ->where('product_id', $product_id)
			 ->first();
	 
		 if (!$savedProduct) {
			 return response()->json([
				 'message' => 'Product not found in Save for Later.'
			 ], 404);
		 }
	 
		 // Delete the record
		 $savedProduct->delete();
	 
		 return response()->json([
			 'message' => 'Product has been removed from Save for Later.'
		 ], 200);
	 }
	 
}
