<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\FrontEnd\AlternateProduct;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Google\Client as Google_Client;
use Google\Service\SearchConsole as Google_Service_SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest as Google_Service_SearchConsole_SearchAnalyticsQueryRequest;
class LLmsSeoMonitoringController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/llms-seo-monitoring",
     *     summary="Get a list of LLMS Google sitemap SERP monitoring data",
     *     description="Returns product performance data including ID, URL, clicks, position, impressions, and CTR.",
     *     tags={"Google Console Monitoring"},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         example=1,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="length",
     *         in="query",
     *         description="Number of records per page",
     *         example=20,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *             
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date filter",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date filter",
     *         @OA\Schema(type="string", format="date", example="2025-10-02")
     *     ),
     *     @OA\Parameter(
     *         name="global",
     *         in="query",
     *         description="Global search term",
     *         @OA\Schema(type="string")
     *     ),
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="LLMS Google sitemap SERP monitoring data retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=101),
     *             @OA\Property(property="url", type="string", example="https://example.com/product-1"),
     *             @OA\Property(property="clicks", type="integer", example=150),
     *             @OA\Property(property="impressions", type="integer", example=2000),
     *             @OA\Property(property="ctr", type="number", format="float", example=7.5),
     *             @OA\Property(property="position", type="number", format="float", example=3.2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */

    public function index(Request $request)
    {
        ini_set('max_execution_time', 300);
        set_time_limit(300);
        $client = new \Google_Client();
        $scriptPath = base_path('app/Script/horecastore-seo-monitor-9f0bfa40e9cd.json');
        $client->setAuthConfig($scriptPath);
        $client->addScope('https://www.googleapis.com/auth/webmasters.readonly');

        $service = new \Google_Service_SearchConsole($client);

        // Get verified site URL
        $sites = $service->sites->listSites();
        $siteUrl = null;

        foreach ($sites as $site) {

            $siteUrl = $site->getSiteUrl();
            break;

        }

        if (!$siteUrl) {
            return response()->json(['success' => false, 'message' => 'No verified site found']);
        }
        $limit = 100;
        $startRow = 0;
        $sitemapList = [];
        $startDate = date('Y-m-d', strtotime('-2 days'));
        $endDate = date('Y-m-d');
         if (!empty($request->from_date)) {
            $startDate = date('Y-m-d', strtotime($request->from_date));
            } else {
                $startDate = date('Y-m-d', strtotime('-2 days'));                
            }

            if (!empty($request->to_date)) {
                $endDate = date('Y-m-d', strtotime($request->to_date));
            } else {
                $endDate = date('Y-m-d');                
            }
            $length = (int) $request->length;
         
            $page = (int) $request->page;
        do {
            $request = new \Google_Service_SearchConsole_SearchAnalyticsQueryRequest();
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);
            $request->setDimensions(['page']);
            $request->setRowLimit($length);
            $request->setStartRow(($page - 1) * $length);
            //$siteUrl = "https://www.horecastore.ae";
            // Call API
            $response = $service->searchanalytics->query($siteUrl, $request);
            $rows = $response->getRows() ?? [];
            $count = count($rows);

            foreach ($rows as $row) {
                $keys = $row->getKeys();
                $sitemapList[] = [
                    'url' => $keys[0] ?? '',
                    'clicks' => $row->getClicks(),
                    'impressions' => $row->getImpressions(),
                    'ctr' => round($row->getCtr(), 3),
                    'position' =>round($row->getPosition(), 3) ,
                ];
            }
       
            $startRow += $length;
            $maxRows = 500;
        } while ($count === $length && $startRow < $maxRows);

     
        return response()->json([
            'success' => true,
            'total_records' => count($sitemapList),
            'page_size' => $length,
            'total_pages' => ceil(count($sitemapList) / $length),
            'data' => $sitemapList,
            'message' => 'Horecastore Google console monitoring data fetched successfully',
        ]);
    }
  

}
