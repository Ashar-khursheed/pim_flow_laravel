<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use Illuminate\Http\Request;
use App\Models\FrontEnd\Discount;
use App\Models\Category;
use App\Models\Product;
class DiscountController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/discounts",
     *     tags={"Frontend-Discounts"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get applicable discounts for a specific product",
     *     description="Fetches active and available discounts for a given product based on product ID and its categories.",
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         required=true,
     *         description="ID of the product to fetch discounts for",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success response with applicable discounts or no discounts found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Summer Sale"),
     *                 @OA\Property(property="type", type="string", example="percentage"),
     *                 @OA\Property(property="value", type="number", format="float", example=10),
     *                 @OA\Property(property="start_date", type="string", format="date-time"),
     *                 @OA\Property(property="end_date", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="message", type="string", example="No discounts available for this product or its categories.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */

 public function getDiscountsForProduct(Request $request)
{
    \Log::info($request->all());

    // Accept either product_id or slug
    $productInput = $request->input('product_id');

    if (!$productInput) {
        return response()->json([
            'success' => false,
            'message' => 'Product identifier (ID or slug) is required.',
        ], 400);
    }

    // Resolve product ID from slug or use as-is if numeric
    if (is_numeric($productInput)) {
        $productId = (int) $productInput;
    } else {
        $product = Product::whereHas('seoUrl', function ($q) use ($productInput) {
            $q->where('url', $productInput);
        })->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found by slug.',
            ], 404);
        }

        $productId = $product->id;
    }

    // Fetch product-specific discounts
    $productDiscounts = Discount::whereHas('products', function ($query) use ($productId) {
        $query->where('product_id', $productId);
    })
    ->active()
    ->available()
    ->get();

    // Fetch categories the product belongs to
    $productCategories = Category::whereHas('products', function ($query) use ($productId) {
        $query->where('id', $productId);
    })->pluck('id');

    // Fetch category-level discounts
    $categoryDiscounts = Discount::whereHas('productCategories', function ($query) use ($productCategories) {
        $query->whereIn('product_category_id', $productCategories);
    })
    ->active()
    ->available()
    ->get();

    // Decide which discounts to return
    $discounts = $productDiscounts->isNotEmpty() ? $productDiscounts : $categoryDiscounts;

    if ($productDiscounts->isNotEmpty() && $categoryDiscounts->isNotEmpty()) {
        $discounts = $productDiscounts; // Prioritize product-specific discounts
    }

    if ($discounts->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No discounts available for this product or its categories.',
        ], 200);
    }

    return response()->json([
        'success' => true,
        'data' => $discounts,
    ]);
}

}
