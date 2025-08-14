<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Models\Utm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class Utmcontroller extends Controller
{
/**
 * @OA\Get(
 *     path="/api/utms",
 *     security={{"bearerAuth":{}}},
 *     summary="Get UTM source and campaign counts",
 *     description="Returns counts of UTM records grouped by utm_source and utm_campaign. 
 *                  If no dates are provided, returns counts for all available records.",
 *     tags={"UTM Analytics"},
 *     @OA\Parameter(
 *         name="start_date",
 *         in="query",
 *         required=false,
 *         description="Filter records starting from this date (YYYY-MM-DD). If omitted, all records are included.",
 *         @OA\Schema(type="string", format="date", example="2025-08-01")
 *     ),
 *     @OA\Parameter(
 *         name="end_date",
 *         in="query",
 *         required=false,
 *         description="Filter records up to this date (YYYY-MM-DD). If omitted, all records are included.",
 *         @OA\Schema(type="string", format="date", example="2025-08-10")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="utm_source_count",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="utm_source", type="string", example="google"),
 *                     @OA\Property(property="total", type="integer", example=123)
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="utm_campaign_count",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="utm_campaign", type="string", example="summer_sale"),
 *                     @OA\Property(property="total", type="integer", example=98)
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid date format or bad request"
 *     )
 * )
 */
public function index(Request $request)
{
    $startDate = $request->input('start_date'); // format: Y-m-d
    $endDate   = $request->input('end_date');   // format: Y-m-d

    $query = Utm::query();

    if ($startDate && $endDate) {
        $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
    } elseif ($startDate) {
        $query->whereDate('created_at', $startDate);
    }

    $utmSourceCount = (clone $query)
        ->select('utm_source', DB::raw('COUNT(*) as total'))
        ->groupBy('utm_source')
        ->orderByDesc('total')
        ->get();

    $utmCampaignCount = (clone $query)
        ->select('utm_campaign', DB::raw('COUNT(*) as total'))
        ->groupBy('utm_campaign')
        ->orderByDesc('total')
        ->get();

    return response()->json([
        'utm_source_count'   => $utmSourceCount,
        'utm_campaign_count' => $utmCampaignCount,
    ]);
}

    /**
     * @OA\Get(
     *     path="/api/analytics/stats",
     *     summary="Get analytics statistics",
     *     description="Returns marketing and sales statistics including sessions, conversions, and sales with optional date filtering.",
     *     tags={"Analytics"},
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         required=false,
     *         description="Date range filter for statistics",
     *         @OA\Schema(
     *             type="string",
     *             enum={"today", "last_3_days", "last_7_days", "last_15_days", "last_30_days", "lifetime"},
     *             default="lifetime"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="filter", type="string", example="last_7_days"),
     *             @OA\Property(property="total_sessions", type="integer", example=520),
     *             @OA\Property(property="orders_from_utm", type="integer", example=180),
     *             @OA\Property(property="conversion_rate", type="number", format="float", example=34.62, description="Percentage"),
     *             @OA\Property(property="avg_order_value", type="number", format="float", example=82.50),
     *             @OA\Property(property="total_sales", type="number", format="float", example=14850.75),
     *             @OA\Property(property="sales_through_marketing", type="number", format="float", example=10400.40)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid filter parameter"
     *     )
     * )
     */
    public function stats(Request $request)
    {
        $filter = $request->query('filter', 'lifetime'); // default: lifetime

        // Date range based on filter
        $dateRanges = [
            'today' => [Carbon::today(), Carbon::now()],
            'last_3_days' => [Carbon::now()->subDays(2)->startOfDay(), Carbon::now()],
            'last_7_days' => [Carbon::now()->subDays(6)->startOfDay(), Carbon::now()],
            'last_15_days' => [Carbon::now()->subDays(14)->startOfDay(), Carbon::now()],
            'last_30_days' => [Carbon::now()->subDays(29)->startOfDay(), Carbon::now()],
            'lifetime' => [null, null]
        ];

        [$startDate, $endDate] = $dateRanges[$filter] ?? [null, null];

        // Apply date filter if not lifetime
        $utmsQuery = DB::table('utms');
        $ordersQuery = DB::table('orders');

        if ($startDate && $endDate) {
            $utmsQuery->whereBetween('created_at', [$startDate, $endDate]);
            $ordersQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Total sessions
        $totalSessions = $utmsQuery->count();

        // Orders from UTM
        $ordersFromUtm = (clone $ordersQuery)
            ->whereNotNull('utm_id')
            ->count();

        // Conversion %
        $conversionRate = $totalSessions > 0
            ? round(($ordersFromUtm / $totalSessions) * 100, 2)
            : 0;

        // Avg order value
        $avgOrderValue = (clone $ordersQuery)->avg('total_amount');

        // Total sales
        $totalSales = (clone $ordersQuery)->sum('total_amount');

        // Sales through marketing
        $salesThroughMarketing = (clone $ordersQuery)
            ->whereNotNull('utm_id')
            ->sum('total_amount');

        return response()->json([
            'filter' => $filter,
            'total_sessions' => $totalSessions,
            'orders_from_utm' => $ordersFromUtm,
            'conversion_rate' => $conversionRate,
            'avg_order_value' => round($avgOrderValue, 2),
            'total_sales' => round($totalSales, 2),
            'sales_through_marketing' => round($salesThroughMarketing, 2),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/utm-sources",
     *     summary="Get UTM source analytics or details for a specific source",
     *     description="When no utm_source is provided, returns aggregated stats for all sources. When utm_source is provided, returns detailed UTM rows for that source along with totals.",
     *     tags={"Analytics"},
     *     @OA\Parameter(
     *         name="utm_source",
     *         in="query",
     *         required=false,
     *         description="UTM source to filter by",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="UTM source analytics data",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    // public function utmSources(Request $request)
    // {
    //     $utmSource = $request->query('utm_source');

    //     if ($utmSource) {
    //         // Detailed data for one UTM source
    //         $utms = DB::table('utms')
    //             ->where('utm_source', $utmSource)
    //             ->get();

    //         $totalSessions = $utms->count();

    //         // Orders linked to this source
    //         $totalOrders = DB::table('orders')
    //             ->join('utms', 'orders.utm_id', '=', 'utms.id')
    //             ->where('utms.utm_source', $utmSource)
    //             ->count();

    //         $totalSales = DB::table('orders')
    //             ->join('utms', 'orders.utm_id', '=', 'utms.id')
    //             ->where('utms.utm_source', $utmSource)
    //             ->sum('orders.total_amount');

    //         $conversionRate = $totalSessions > 0
    //             ? round(($totalOrders / $totalSessions) * 100, 2)
    //             : 0;

    //         return response()->json([
    //             'utm_source' => $utmSource,
    //             'total_sessions' => $totalSessions,
    //             'total_orders' => $totalOrders,
    //             'total_sales' => round($totalSales, 2),
    //             'conversion_rate' => $conversionRate,
    //             'details' => $utms // full UTM records
    //         ]);
    //     }

    //     // Aggregated data for all sources
    //     $sources = DB::table('utms')
    //         ->select('utm_source', DB::raw('COUNT(*) as total_sessions'))
    //         ->groupBy('utm_source')
    //         ->get()
    //         ->map(function ($row) {
    //             $totalOrders = DB::table('orders')
    //                 ->join('utms', 'orders.utm_id', '=', 'utms.id')
    //                 ->where('utms.utm_source', $row->utm_source)
    //                 ->count();

    //             $totalSales = DB::table('orders')
    //                 ->join('utms', 'orders.utm_id', '=', 'utms.id')
    //                 ->where('utms.utm_source', $row->utm_source)
    //                 ->sum('orders.total_amount');

    //             $conversionRate = $row->total_sessions > 0
    //                 ? round(($totalOrders / $row->total_sessions) * 100, 2)
    //                 : 0;

    //             return [
    //                 'utm_source' => $row->utm_source,
    //                 'total_sessions' => $row->total_sessions,
    //                 'total_orders' => $totalOrders,
    //                 'total_sales' => round($totalSales, 2),
    //                 'conversion_rate' => $conversionRate
    //             ];
    //         });

    //     return response()->json($sources);
    // }
// public function utmSources(Request $request)
// {
//     $utmSource = $request->query('utm_source');

//     if ($utmSource) {
//         // Detailed data for one UTM source, grouped by medium and campaign
//         $utms = DB::table('utms')
//             ->where('utm_source', $utmSource)
//             ->select('utm_medium', 'utm_campaign', DB::raw('COUNT(*) as total_sessions'))
//             ->groupBy('utm_medium', 'utm_campaign')
//             ->get();

//         $result = $utms->map(function ($row) use ($utmSource) {
//             $totalOrders = DB::table('orders')
//                 ->join('utms', 'orders.utm_id', '=', 'utms.id')
//                 ->where('utms.utm_source', $utmSource)
//                 ->where('utms.utm_medium', $row->utm_medium)
//                 ->where('utms.utm_campaign', $row->utm_campaign)
//                 ->count();

//             $totalSales = DB::table('orders')
//                 ->join('utms', 'orders.utm_id', '=', 'utms.id')
//                 ->where('utms.utm_source', $utmSource)
//                 ->where('utms.utm_medium', $row->utm_medium)
//                 ->where('utms.utm_campaign', $row->utm_campaign)
//                 ->sum('orders.total_amount');

//             $conversionRate = $row->total_sessions > 0
//                 ? round(($totalOrders / $row->total_sessions) * 100, 2)
//                 : 0;

//             return [
//                 'utm_source' => $utmSource,
//                 'utm_type' => $this->detectSourceType($utmSource), // optional type helper
//                 'utm_medium' => $row->utm_medium,
//                 'utm_campaign' => $row->utm_campaign,
//                 'total_sessions' => $row->total_sessions,
//                 'total_orders' => $totalOrders,
//                 'total_sales' => round($totalSales, 2),
//                 'conversion_rate' => $conversionRate
//             ];
//         });

//         return response()->json($result);
//     }

//     // Aggregated data for all sources with medium and campaign breakdown
//     $sources = DB::table('utms')
//         ->select('utm_source', 'utm_medium', 'utm_campaign', DB::raw('COUNT(*) as total_sessions'))
//         ->groupBy('utm_source', 'utm_medium', 'utm_campaign')
//         ->get()
//         ->map(function ($row) {
//             $totalOrders = DB::table('orders')
//                 ->join('utms', 'orders.utm_id', '=', 'utms.id')
//                 ->where('utms.utm_source', $row->utm_source)
//                 ->where('utms.utm_medium', $row->utm_medium)
//                 ->where('utms.utm_campaign', $row->utm_campaign)
//                 ->count();

//             $totalSales = DB::table('orders')
//                 ->join('utms', 'orders.utm_id', '=', 'utms.id')
//                 ->where('utms.utm_source', $row->utm_source)
//                 ->where('utms.utm_medium', $row->utm_medium)
//                 ->where('utms.utm_campaign', $row->utm_campaign)
//                 ->sum('orders.total_amount');

//             $conversionRate = $row->total_sessions > 0
//                 ? round(($totalOrders / $row->total_sessions) * 100, 2)
//                 : 0;

//             return [
//                 'utm_source' => $row->utm_source,
//                 'utm_type' => $this->detectSourceType($row->utm_source), // optional type helper
//                 'utm_medium' => $row->utm_medium,
//                 'utm_campaign' => $row->utm_campaign,
//                 'total_sessions' => $row->total_sessions,
//                 'total_orders' => $totalOrders,
//                 'total_sales' => round($totalSales, 2),
//                 'conversion_rate' => $conversionRate
//             ];
//         });

//     return response()->json($sources);
// }

// /**
//  * Optional helper to detect source type
//  */
// private function detectSourceType($source)
// {
//     $source = strtolower($source);

//     if (str_contains($source, 'google')) return 'Search Engine';
//     if (str_contains($source, 'facebook')) return 'Social Media';
//     if (str_contains($source, 'linkedin')) return 'Social Media';
//     if (str_contains($source, 'email')) return 'Email';
//     return 'Other';
// }
public function utmSources(Request $request)
{
    $utmSource = $request->query('utm_source');

    if ($utmSource) {
        // Detailed breakdown for one UTM source (medium + campaign)
        $utms = DB::table('utms')
            ->where('utm_source', $utmSource)
            ->select('utm_medium', 'utm_campaign', DB::raw('COUNT(*) as total_sessions'))
            ->groupBy('utm_medium', 'utm_campaign')
            ->get();

        $result = $utms->map(function ($row) use ($utmSource) {
           $totalOrders = DB::table('orders')
                ->join('utms', 'orders.utm_id', '=', 'utms.id')
                ->where('utms.utm_source', $utmSource)
                ->where('utms.utm_medium', $row->utm_medium)
                ->where('utms.utm_campaign', $row->utm_campaign)
                ->count();

            $totalSales = DB::table('orders')
                ->join('utms', 'orders.utm_id', '=', 'utms.id')
                ->where('utms.utm_source', $utmSource)
                ->where('utms.utm_medium', $row->utm_medium)
                ->where('utms.utm_campaign', $row->utm_campaign)
                ->sum('orders.total_amount');

            $conversionRate = $row->total_sessions > 0
                ? round(($totalOrders / $row->total_sessions) * 100, 2)
                : 0;

            return [
                'utm_source' => $utmSource,
                'utm_type' => $this->detectSourceType($utmSource),
                'utm_medium' => $row->utm_medium,
                'utm_campaign' => $row->utm_campaign,
                'total_sessions' => $row->total_sessions,
                'total_orders' => $totalOrders,
                'total_sales' => round($totalSales, 2),
                'conversion_rate' => $conversionRate
            ];
        });

        return response()->json($result);
    }

    // Summary per source (no detailed breakdown)
    $sources = DB::table('utms')
        ->select('utm_source', DB::raw('COUNT(*) as total_sessions'))
        ->groupBy('utm_source')
        ->get()
        ->map(function ($row) {
            $totalOrders = DB::table('orders')
                ->join('utms', 'orders.utm_id', '=', 'utms.id')
                ->where('utm_source', $row->utm_source)
                ->count();

            $totalSales = DB::table('orders')
                ->join('utms', 'orders.utm_id', '=', 'utms.id')
                ->where('utm_source', $row->utm_source)
                ->sum('orders.total_amount');

            $conversionRate = $row->total_sessions > 0
                ? round(($totalOrders / $row->total_sessions) * 100, 2)
                : 0;

            return [
                'utm_source' => $row->utm_source,
                'utm_type' => $this->detectSourceType($row->utm_source),
                'total_sessions' => $row->total_sessions,
                'total_orders' => $totalOrders,
                'total_sales' => round($totalSales, 2),
                'conversion_rate' => $conversionRate
            ];
        });

    return response()->json($sources);
}

/**
 * Optional helper to detect source type
 */
private function detectSourceType($source)
{
    $source = strtolower($source);

    if (str_contains($source, 'google')) return 'Search Engine';
    if (str_contains($source, 'facebook')) return 'Social Media';
    if (str_contains($source, 'linkedin')) return 'Social Media';
    if (str_contains($source, 'email')) return 'Email';
    return 'Other';
}



}
