<?php

namespace App\Http\Controllers;

use App\Models\Utm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UtmController extends Controller
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

}
