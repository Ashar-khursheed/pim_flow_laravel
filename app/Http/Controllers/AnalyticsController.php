<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\GoogleAnalytics;

class AnalyticsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/analytics/report",
     *     summary="Get Google Analytics Report",
     *     description="Fetches analytics data from Google Analytics 4 (GA4).",
     *     tags={"Analytics"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful Response",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "sessions": 1200,
     *                 "users": 800,
     *                 "pageViews": 3400,
     *                 "bounceRate": 0.45,
     *                 "averageSessionDuration": "3m 24s"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid credentials"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function report()
    {
        $ga = new GoogleAnalytics();
        $propertyId = "441790093"; // Your GA4 property ID
        $data = $ga->getReport($propertyId);

        return response()->json($data);
    }
}
