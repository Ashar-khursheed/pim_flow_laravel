<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Product;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Auth;

class UserReviewController extends Controller
{
    /**
     * Get all reviews for the logged-in customer
     *
     * @return \Illuminate\Http\JsonResponse
     */

    /**
     * @OA\Get(
     *     path="/api/customer-reviews",
     *     summary="Get all reviews for the authenticated customer",
     *     tags={"Frontend-User Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of customer reviews"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="User not authenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No reviews found"
     *     )
     * )
     */

    public function getCustomerReviews()
    {
        $userId = Auth::id(); // Get the authenticated user

        if (!$userId) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        // Fetch reviews for the logged-in customer
        $reviews = Review::where('customer_id', $userId)->with('product')->orderBy('id', 'DESC')->get()->map(function ($record) {
            // Assuming $record->images already exists as an associative array
            $images = $record->images;

            // Generate URLs dynamically
            if ($images) {
                $record->imageUrls =  is_string($record->images)
                    ? json_decode($record->images, true) ?? []
                    : (is_array($record->images) ? $record->images : []);
            }
            return $record;
        });

        // Check if reviews exist
        if ($reviews->isEmpty()) {
            return response()->json(['message' => 'No reviews found for this user.'], 404);
        }

        // Return reviews with product data

        return response()->json([
            'success' => true,
            'data' => $reviews
        ], 200);
    }


    /**
     * @OA\Post(
     *     path="/api/add-customer-reviews",
     *     summary="Create a new review",
     *     tags={"Frontend-User Reviews"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"product_id","star","comment"},
     *
     *                 @OA\Property(
     *                     property="product_id",
     *                     type="integer",
     *                     example=1
     *                 ),
     *                 @OA\Property(
     *                     property="star",
     *                     type="integer",
     *                     minimum=1,
     *                     maximum=5,
     *                     example=5
     *                 ),
     *                 @OA\Property(
     *                     property="comment",
     *                     type="string",
     *                     example="Great product!"
     *                 ),
     *                 @OA\Property(
     *                     property="images[]",
     *                     type="array",
     *                     description="Review images",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Review added successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */


    public function createReview(Request $request)
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.'
            ], 401);
        }

        // ✅ Validation
        $request->validate([
            'product_id' => 'required|exists:ec_products,id',
            'star'       => 'required|integer|min:1|max:5',
            'comment'    => 'required|string',
            'images'     => 'nullable|array',
            'images.*'   => 'image|mimes:jpeg,png,jpg,webp|max:10240', // 10MB
        ]);

        // ✅ Prevent duplicate review
        $existingReview = Review::where('customer_id', $userId)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => true,
                'message' => 'You have already submitted a review for this product.',
                'review'  => $existingReview,
            ], 200);
        }

        try {
            $uploadedImages = [];

            $path = env('STORAGE_ENV') . '/production/review';
            // ✅ Upload & compress images
            if ($request->hasFile('images') && is_array($request->file('images'))) {

                foreach ($request->file('images') as $key => $imageFile) {

                    if (!$imageFile->isValid()) {
                        continue;
                    }
                    $tempRequest = new \Illuminate\Http\Request();
                    $tempRequest->files->set('review_image_single', $imageFile);

                    $url = compressImageToS3(
                        $tempRequest,
                        'review_image_single',
                        $path
                    );

                    if ($url) {
                        $uploadedImages[] = $url;
                    }
                }
            }

            $images = json_encode($uploadedImages);
            // ✅ Create review
            $review = Review::create([
                'customer_id'   => $userId,
                'customer_name' => Auth::user()->name,
                'product_id'    => $request->product_id,
                'star'          => $request->star,
                'comment'       => $request->comment,
                'status'        => 'published',
                'images'        => !empty($images) ? $images : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Review added successfully',
                'review'  => array_merge(
                    $review->toArray(),
                    ['image_urls' => $uploadedImages]
                )
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error occurred: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/customer-reviews-update/{id}",
     *     summary="Update an existing review",
     *     tags={"Frontend-User Reviews"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Review ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="star",
     *                     type="integer",
     *                     minimum=1,
     *                     maximum=5,
     *                     example=4
     *                 ),
     *                 @OA\Property(
     *                     property="comment",
     *                     type="string",
     *                     example="Updated comment."
     *                 ),
     *                 @OA\Property(
     *                     property="images[]",
     *                     type="array",
     *                     description="Updated review images",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Review updated successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="User not authenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Review not found or unauthorized"
     *     )
     * )
     */

    public function updateReview(Request $request, $id)
    {
        $userId = Auth::id(); // Get the authenticated user's ID

        if (!$userId) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        // Find the review by ID
        $review = Review::where('id', $id)->where('customer_id', $userId)->first();

        if (!$review) {
            return response()->json(['message' => 'Review not found or unauthorized.'], 404);
        }

        // Validate incoming request
        $request->validate([
            'star' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $dataToUpdate = $request->only(['star', 'comment']);
        $images = [];
        $imageUrls = [];
        $uploadedImages = [];
        $path = env('STORAGE_ENV') . '/production/review';

        // ✅ Upload & compress images
        if ($request->hasFile('images') && is_array($request->file('images'))) {

            foreach ($request->file('images') as $key => $imageFile) {

                if (!$imageFile->isValid()) {
                    continue;
                }
                $tempRequest = new \Illuminate\Http\Request();
                $tempRequest->files->set('review_image_single', $imageFile);

                $url = compressImageToS3(
                    $tempRequest,
                    'review_image_single',
                    $path
                );

                if ($url) {
                    $uploadedImages[] = $url;
                }
            }
        }

        $images = json_encode($uploadedImages);


        if (!empty($images)) {
            $dataToUpdate['images'] = $images;
        }

        $review->update($dataToUpdate);

        if ($review) {
            $reviewData = $review->toArray();
            $reviewData['image_urls'] = $images;

            return response()->json([
                'message' => 'Review updated successfully',
                'success' => true,
                'review' => $reviewData,
            ], 201);
        }
    }

    /**
     * Delete a specific review
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */

    /**SEOManagement
     * @OA\Delete(
     *     path="/api/customer-reviews-delete/{id}",
     *     summary="Delete a review",
     *     tags={"Frontend-User Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Review ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="User not authenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Review not found or unauthorized"
     *     )
     * )
     */
    public function deleteReview($id)
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        // Find the review by ID
        $review = Review::where('id', $id)->where('customer_id', $userId)->first();

        if (!$review) {
            return response()->json(['message' => 'Review not found or unauthorized.'], 404);
        }

        // Delete the review
        $review->delete();

        return response()->json(['message' => 'Review deleted successfully.']);
    }



    /**
     * @OA\Get(
     *     path="/api/frontend/product-reviews",
     *     summary="Get product reviews with filtering, sorting, and pagination",
     *     description="Returns paginated product reviews along with star counts and average rating.",
     *     operationId="getProductReviews",
     *     tags={"Frontend-User Reviews"},
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="ID of the product to get reviews for",
     *         required=true,
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Parameter(
     *         name="star",
     *         in="query",
     *         description="Filter reviews by star rating (1 to 5)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={1,2,3,4,5}, example=5)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort reviews by star rating: 'highest' or 'lowest'. Default sorts by latest created.",
     *         required=false,
     *         @OA\Schema(type="string", enum={"highest","lowest"}, example="highest")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of reviews per page for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with paginated reviews and metadata",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="reviews",
     *                     type="object",
     *                     description="Paginated list of reviews",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(
     *                         property="data", 
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=456),
     *                             @OA\Property(property="product_id", type="integer", example=123),
     *                             @OA\Property(property="star", type="integer", example=5),
     *                             @OA\Property(property="comment", type="string", example="Great product!"),
     *                             @OA\Property(
     *                                 property="images", 
     *                                 type="array",
     *                                 @OA\Items(type="string", format="uri", example="https://example.com/image1.jpg")
     *                             ),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-06-01T12:34:56Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-06-01T12:34:56Z")
     *                         )
     *                     ),
     *                     @OA\Property(property="last_page", type="integer", example=3),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=45)
     *                 ),
     *                 @OA\Property(property="total_reviews", type="integer", example=45),
     *                 @OA\Property(
     *                     property="star_counts",
     *                     type="object",
     *                     @OA\Property(property="1_star", type="integer", example=5),
     *                     @OA\Property(property="2_star", type="integer", example=3),
     *                     @OA\Property(property="3_star", type="integer", example=7),
     *                     @OA\Property(property="4_star", type="integer", example=10),
     *                     @OA\Property(property="5_star", type="integer", example=20)
     *                 ),
     *                 @OA\Property(property="average_rating", type="number", format="float", example=4.25),
     *                 @OA\Property(property="product_id", type="integer", example=123)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid parameters")
     *         )
     *     )
     * )
     */
    // public function getProductReviews(Request $request)
    // {
    //     \Log::info($request->all());

    //     $productId = $request->input('product_id');

    //     $totalReviews = Review::where('product_id', $productId)->count();

    //     $query = Review::query();

    //     if ($request->has('star')) {
    //         $star = $request->input('star');
    //         $query->where('star', $star);
    //     }

    //     if ($request->has('product_id')) {
    //         if (!Product::where('id', $productId)->exists()) {
    //             return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
    //         }

    //         $query->where('product_id', $productId);
    //     }

    //     if ($request->has('sort')) {
    //         if ($request->input('sort') === 'highest') {
    //             $query->orderBy('star', 'desc');
    //         } elseif ($request->input('sort') === 'lowest') {
    //             $query->orderBy('star', 'asc');
    //         }
    //     } else {
    //         $query->orderBy('created_at', 'desc');
    //     }

    //     $reviews = $query->paginate($request->input('per_page', 15));

    //     // Just ensure images is an array, no URL transformation
    //     $reviews->getCollection()->transform(function ($review) {
    //         if (!empty($review->images) && !is_array($review->images)) {
    //             $review->images = json_decode($review->images, true) ?: [];
    //         }
    //         return $review;
    //     });

    //     $starCounts = [
    //         '1_star' => Review::where('star', 1)->where('product_id', $productId)->count(),
    //         '2_star' => Review::where('star', 2)->where('product_id', $productId)->count(),
    //         '3_star' => Review::where('star', 3)->where('product_id', $productId)->count(),
    //         '4_star' => Review::where('star', 4)->where('product_id', $productId)->count(),
    //         '5_star' => Review::where('star', 5)->where('product_id', $productId)->count(),
    //     ];

    //     $averageRating = Review::where('product_id', $productId)->avg('star');

    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'reviews' => $reviews,
    //             'total_reviews' => $totalReviews,
    //             'star_counts' => $starCounts,
    //             'average_rating' => round($averageRating, 2),
    //             'product_id' => $productId,
    //         ],
    //     ]);
    // }
    public function getProductReviews(Request $request)
    {
        \Log::info($request->all());

        $input = $request->input('product_id');

        if (!$input) {
            return response()->json(['success' => false, 'message' => 'Product ID or slug is required.'], 400);
        }

        // Resolve product ID from slug if needed
        if (is_numeric($input)) {
            $productId = (int) $input;
        } else {
            $product = Product::whereHas('seoUrl', function ($q) use ($input) {
                $q->where('url', $input);
            })->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
            }

            $productId = $product->id;
        }

        $totalReviews = Review::where('product_id', $productId)->count();

        $query = Review::query()->where('product_id', $productId);

        if ($request->has('star')) {
            $query->where('star', $request->input('star'));
        }

        if ($request->input('sort') === 'highest') {
            $query->orderBy('star', 'desc');
        } elseif ($request->input('sort') === 'lowest') {
            $query->orderBy('star', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $reviews = $query->paginate($request->input('per_page', 15));

        // Normalize review images
        $reviews->getCollection()->transform(function ($review) {
            if (!empty($review->images) && !is_array($review->images)) {
                $review->images = json_decode($review->images, true) ?: [];
            }
            return $review;
        });

        // Star counts
        $starCounts = [];
        for ($i = 1; $i <= 5; $i++) {
            $starCounts["{$i}_star"] = Review::where('star', $i)->where('product_id', $productId)->count();
        }

        $averageRating = Review::where('product_id', $productId)->avg('star');

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews,
                'total_reviews' => $totalReviews,
                'star_counts' => $starCounts,
                'average_rating' => round($averageRating, 2),
                'product_id' => $productId,
            ],
        ]);
    }
}
