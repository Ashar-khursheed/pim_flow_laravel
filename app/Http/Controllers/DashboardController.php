<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\Order;
use App\Models\Category;

class DashboardController extends Controller
{
    /**
 * @OA\Get(
 *     path="/api/dashboard/stats",
 *     summary="Get dashboard statistics with optional range filter",
 *     tags={"Dashboard"},
 *     @OA\Parameter(
 *         name="range",
 *         in="query",
 *         description="Time range: 15_days, 30_days, 2_months, 3_months, 6_months, lifetime",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Dashboard statistics fetched successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="range", type="string", example="30_days"),
 *             @OA\Property(property="products_count", type="integer", example=35),
 *             @OA\Property(property="orders_count", type="integer", example=55),
 *             @OA\Property(property="categories_count", type="integer", example=5),
 *             @OA\Property(property="published_products", type="integer", example=20),
 *             @OA\Property(property="draft_products", type="integer", example=15)
 *         )
 *     ),
 *     security={{"bearerAuth":{}}}
 * )
 */
public function stats(Request $request)
{
    $range = $request->query('range', 'lifetime');

    $now = now();

    $ranges = [
        '15_days' => $now->copy()->subDays(15),
        '30_days' => $now->copy()->subDays(30),
        '2_months' => $now->copy()->subMonths(2),
        '3_months' => $now->copy()->subMonths(3),
        '6_months' => $now->copy()->subMonths(6),
    ];

    if ($range !== 'lifetime' && isset($ranges[$range])) {
        $date = $ranges[$range];

        $productsCount = \App\Models\Product::where('created_at', '>=', $date)->count();
        $ordersCount = \App\Models\Order::where('created_at', '>=', $date)->count();
        $categoriesCount = \App\Models\Category::where('created_at', '>=', $date)->count();
        $publishedProducts = \App\Models\Product::where('status', 'published')->where('created_at', '>=', $date)->count();
        $draftProducts = \App\Models\Product::where('status', 'draft')->where('created_at', '>=', $date)->count();
    } else {
        // Lifetime counts
        $productsCount = \App\Models\Product::count();
        $ordersCount = \App\Models\Order::count();
        $categoriesCount = \App\Models\Category::count();
        $publishedProducts = \App\Models\Product::where('status', 'published')->count();
        $draftProducts = \App\Models\Product::where('status', 'draft')->count();
    }

    return response()->json([
        'range' => $range,
        'products_count' => $productsCount,
        'orders_count' => $ordersCount,
        'categories_count' => $categoriesCount,
        'published_products' => $publishedProducts,
        'draft_products' => $draftProducts,
    ]);
}
}