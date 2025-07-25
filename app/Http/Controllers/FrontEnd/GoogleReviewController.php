<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GoogleReviewController extends Controller
{
    protected $apiKey;
    protected $defaultPlaceId;

    public function __construct()
    {
        $this->apiKey = config('services.google_place.key');
        $this->defaultPlaceId = config('services.google_place.place_id');
    }
        /**
     * @OA\Get(
     *     path="/api/frontend/google-reviews",
     *     summary="Get Google Reviews for a Place",
     *     description="Fetch reviews, rating breakdown, and filter by star ratings from Google Places API.",
     *     operationId="getGoogleReviews",
     *     tags={"Google Reviews"},
     *     @OA\Parameter(
     *         name="place_id",
     *         in="query",
     *         description="Google Place ID (default from .env if not provided)",
     *         required=false,
     *         @OA\Schema(type="string", example="ChIJa4N_zmppXz4R-Oi2cjnr2iE")
     *     ),
     *     @OA\Parameter(
     *         name="stars",
     *         in="query",
     *         description="Filter reviews by star rating (1-5)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=5, example=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful Response",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Some Business"),
     *             @OA\Property(property="rating", type="number", format="float", example=4.3),
     *             @OA\Property(property="total_reviews", type="integer", example=145),
     *             @OA\Property(
     *                 property="rating_breakdown",
     *                 type="object",
     *                 @OA\Property(property="5", type="integer", example=80),
     *                 @OA\Property(property="4", type="integer", example=30),
     *                 @OA\Property(property="3", type="integer", example=20),
     *                 @OA\Property(property="2", type="integer", example=10),
     *                 @OA\Property(property="1", type="integer", example=5)
     *             ),
     *             @OA\Property(property="filtered_star", type="integer", nullable=true, example=5),
     *             @OA\Property(
     *                 property="reviews",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="author_name", type="string", example="John Doe"),
     *                     @OA\Property(property="rating", type="number", example=5),
     *                     @OA\Property(property="text", type="string", example="Excellent service!"),
     *                     @OA\Property(property="relative_time_description", type="string", example="2 weeks ago")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Missing API key or place ID / Google API error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Missing API key or place ID.")
     *         )
     *     )
     * )
     */


    public function getReviews(Request $request)
    {
        $placeId = $request->query('place_id', $this->defaultPlaceId);
        $filterStars = $request->query('stars');

        if (!$placeId || !$this->apiKey) {
            return response()->json([
                'error' => 'Missing API key or place ID.'
            ], 400);
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'name,rating,user_ratings_total,reviews',
            'key' => $this->apiKey,
        ]);

        $data = $response->json();

        if (!isset($data['result'])) {
            return response()->json([
                'error' => 'Could not fetch reviews',
                'details' => $data,
            ], 400);
        }

        $reviews = $data['result']['reviews'] ?? [];

        $ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($reviews as $review) {
            $stars = (int) round($review['rating']);
            if (isset($ratingBreakdown[$stars])) {
                $ratingBreakdown[$stars]++;
            }
        }

        if ($filterStars && in_array((int)$filterStars, [1, 2, 3, 4, 5])) {
            $reviews = array_filter($reviews, function ($review) use ($filterStars) {
                return (int) round($review['rating']) === (int)$filterStars;
            });
            $reviews = array_values($reviews);
        }

        return response()->json([
            'name' => $data['result']['name'] ?? null,
            'rating' => $data['result']['rating'] ?? null,
            'total_reviews' => $data['result']['user_ratings_total'] ?? 0,
            'rating_breakdown' => $ratingBreakdown,
            'filtered_star' => $filterStars ? (int)$filterStars : null,
            'reviews' => $reviews,
        ]);
    }
}
