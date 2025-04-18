<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="Review",
 *     type="object",
 *     title="Review",
 *     description="Review Model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="customer_name", type="string", example="John Doe"),
 *     @OA\Property(property="customer_email", type="string", example="john@example.com"),
 *     @OA\Property(property="product_id", type="integer", example=10),
 *     @OA\Property(property="star", type="integer", example=5),
 *     @OA\Property(property="comment", type="string", example="Great product!"),
 *     @OA\Property(property="status", type="string", example="published"),
 *     @OA\Property(property="images", type="array", @OA\Items(type="string", example="https://s3.amazonaws.com/bucket/review1.jpg")),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-03-13T12:34:56Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-03-14T12:34:56Z")
 * )
 */

class ReviewController extends Controller
{
        /**
         * @OA\Get(
         *     path="/api/reviews",
         *     summary="Get all reviews",
         *     tags={"Reviews"},
         *     security={{"bearerAuth":{}}},
         *     @OA\Response(
         *         response=200,
         *         description="List of reviews",
         *         @OA\JsonContent(
         *             type="array",
         *             @OA\Items(ref="#/components/schemas/Review")
         *         )
         *     )
         * )
         */

    public function index()
    {
        if (!auth()->user()->can('list review')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        return response()->json(Review::all(), 200);
    }

    /**
     * @OA\Post(
     *     path="/api/reviews",
     *     summary="Create a new review",
     *      security={{"bearerAuth":{}}},
     *     tags={"Reviews"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"customer_name", "customer_email", "product_id", "star", "comment"},
     *                 @OA\Property(property="customer_name", type="string", example="John Doe"),
     *                 @OA\Property(property="customer_email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="star", type="integer", example=5),
     *                 @OA\Property(property="comment", type="string", example="Great product!"),
     *                 @OA\Property(property="status", type="string", example="published"),
     *                 @OA\Property(property="images[]", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Review created")
     * )
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('add review')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:191',
            'customer_email'=> 'required|email|max:191',
            'product_id'    => 'required|exists:ec_products,id',
            'star'          => 'required|integer|min:1|max:5',
            'comment'       => 'required|string',
            'status'        => 'nullable|string|max:60',
            'images.*'      => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',

        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('production/reviews', 's3');
                $imagePaths[] = Storage::disk('s3')->url($path);
            }
        }

        $review = Review::create([
            'customer_name' => $request->customer_name,
            'customer_email'=> $request->customer_email,
            'product_id'    => $request->product_id,
            'star'          => $request->star,
            'comment'       => $request->comment,
            'status'        => $request->status ?? 'published',
            'images'        => $imagePaths // Store as an array, not JSON string
        ]);

        return response()->json(['message' => 'Review added', 'review' => $review], 201);
    }


    /**
     * @OA\Get(
     *     path="/api/reviews/{id}",
     *     summary="Get a single review",
     *      security={{"bearerAuth":{}}},
     *     tags={"Reviews"},
     *     @OA\Parameter(name="id", in="path", required=true, description="Review ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review details"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function show($id)
    {
        if (!auth()->user()->can('view review')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }
        return response()->json($review, 200);
    }


            /**
         * @OA\Post(
         *     path="/api/reviews/{id}",
         *     summary="Update a review using POST with _method=PUT",
         *     tags={"Reviews"},
         *     security={{"bearerAuth":{}}},
         *     @OA\Parameter(
         *         name="id",
         *         in="path",
         *         description="ID of the review to update",
         *         required=true,
         *         @OA\Schema(type="integer", example=1)
         *     ),
         *      @OA\RequestBody(
            *         required=true,
            *         @OA\MediaType(
            *             mediaType="multipart/form-data",
            *             @OA\Schema(
            *                 required={"comment", "_method"},
            *                 @OA\Property(property="_method", type="string", example="PUT", description="Spoofing PUT request"),
            *                 @OA\Property(property="star", type="integer", minimum=1, maximum=5, example=4, description="Rating from 1 to 5"),
            *                 @OA\Property(property="comment", type="string", example="Great product!", description="Review comment"),
            *                 @OA\Property(property="status", type="string", example="published", description="Review status"),
            *                 @OA\Property(
            *                     property="images[]",
            *                     type="array",
            *                     @OA\Items(type="string", format="binary"),
            *                     description="Upload new images"
            *                 ),
            *                 @OA\Property(
            *                     property="delete_images",
            *                     type="array",
            *                     @OA\Items(type="string"),
            *                     description="List of image URLs to delete"
            *                 ),
            *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-03-13T12:00:00Z", description="Modify review creation time"),
            *             )
            *         )
            *     ),

         *     @OA\Response(
         *         response=200,
         *         description="Review updated successfully",
         *         @OA\JsonContent(
         *             type="object",
         *             @OA\Property(property="message", type="string", example="Review updated successfully"),
         *             @OA\Property(property="review", type="object")
         *         )
         *     ),
         *     @OA\Response(response=403, description="Unauthorized"),
         *     @OA\Response(response=422, description="Validation Error"),
         *     @OA\Response(response=404, description="Review Not Found")
         * )
         */

         public function update(Request $request, $id)
         {
        if (!auth()->user()->can('update review')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
             $review = Review::findOrFail($id);

             // Validate request
             $request->validate([
                 'star' => 'nullable|integer|min:1|max:5',
                 'comment' => 'required|string',
                 'status' => 'nullable|string|in:published,pending,rejected',
                 'images.*' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
                 'delete_images' => 'nullable|array',
                 'delete_images.*' => 'string',
                 'created_at' => 'nullable|date',
                 'customer_name' => 'nullable|string|max:191',
                 'customer_email' => 'nullable|email|max:191'
             ]);

             // Update fields
             $review->star = $request->input('star', $review->star);
             $review->comment = $request->input('comment');
             $review->status = $request->input('status', $review->status);
             $review->customer_name = $request->input('customer_name', $review->customer_name);
             $review->customer_email = $request->input('customer_email', $review->customer_email);

             // Ensure existing images are an array
             $existingImages = is_string($review->images) ? json_decode($review->images, true) ?? [] : [];

             // Remove selected images safely
             if ($request->filled('delete_images')) {
                 $deleteImages = $request->input('delete_images', []);

                 // Remove only if they exist in the array
                 $existingImages = array_values(array_filter($existingImages, function ($image) use ($deleteImages) {
                     return !in_array($image, $deleteImages);
                 }));
             }

             // Upload new images to S3 and append to existing images
             if ($request->hasFile('images')) {
                 foreach ($request->file('images') as $image) {
                     $path = $image->store('production/reviews', 's3'); // Upload to S3
                     $existingImages[] = Storage::disk('s3')->url($path); // Append new image URL
                 }
             }

             // Store updated images list as JSON (Fix double escaping issue)
             $review->images = json_encode(array_values($existingImages), JSON_UNESCAPED_SLASHES);

             // Allow modification of created_at only
             if ($request->has('created_at')) {
                 $review->created_at = $request->created_at;
             }

             $review->save();

             return response()->json([
                 'message' => 'Review updated successfully',
                 'review' => $review
             ]);
         }



    /**
     * @OA\Delete(
     *     path="/api/reviews/{id}",
     *     summary="Delete a review",
     *     tags={"Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Review ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review deleted"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('delete review')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $review->delete();
        return response()->json(['message' => 'Review deleted'], 200);
    }
}
