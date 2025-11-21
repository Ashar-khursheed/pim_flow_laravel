<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SeoMonitoring;
use Google\Client as Google_Client;
use Google\Service\SearchConsole as Google_Service_SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest as Google_Service_SearchConsole_SearchAnalyticsQueryRequest;
use App\Events\SeoMonitors;

class LLmsSeoMonitoringController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/get-llms-seo-monitoring",
     *     summary="Get a list of LLMS Google sitemap SERP monitoring data",
     *     description="Returns product performance data including ID, URL, clicks, position, impressions, and CTR.",
     *     tags={"Google Console Monitoring"},
     *     security={{"bearerAuth":{}}},
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
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *          description="Start date filter",
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
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Column name to sort by",
     *         @OA\Schema(type="string", enum={"id", "url", "clicks", "impressions", "ctr", "position"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_dir",
     *         in="query",
     *         description="Sort direction (asc or desc)",
     *         example="desc",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="LLMS Google sitemap SERP monitoring data retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Records retrieved successfully"),
     *             @OA\Property(property="total_records", type="integer", example=250),
     *             @OA\Property(property="total_pages", type="integer", example=13),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="url", type="string", example="https://example.com/product-1"),
     *                     @OA\Property(property="date", type="string", format="date", example="2025-10-02"),
     *                     @OA\Property(property="keyword", type="string", example="best coffee machine"),
     *                     @OA\Property(property="country", type="string", example="UAE"),
     *                     @OA\Property(property="device", type="string", example="mobile"),
     *                     @OA\Property(property="clicks", type="integer", example=150),
     *                     @OA\Property(property="impressions", type="integer", example=2000),
     *                     @OA\Property(property="ctr", type="number", format="float", example=7.5),
     *                     @OA\Property(property="position", type="number", format="float", example=3.2)
     *                 )
     *             )
     *         )
     *     ),
     *
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
        //SeoMonitors::dispatch();
        ini_set('max_execution_time', 712);
        set_time_limit(712);

        $query = SeoMonitoring::query();

        // 🗓 Date Filter
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('date', [$request->from_date, $request->to_date]);
        } elseif ($request->filled('from_date')) {
            $query->where('date', '>=', $request->from_date);
        } elseif ($request->filled('to_date')) {
            $query->where('date', '<=', $request->to_date);
        }


        if ($request->filled('global')) {
            $search = $request->input('global');
            $query->where(function ($q) use ($search) {
                $q->where('url', 'like', "%{$search}%")
                    ->orWhere('keyword', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('device', 'like', "%{$search}%");
            });
        }


        $sortBy = $request->input('sort_by', 'id'); // fallback to id if not provided
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $length = (int) $request->input('length', 20);
        $page = (int) $request->input('page', 1);

        if ($length < 1)
            $length = 20;
        if ($page < 1)
            $page = 1;


        $totalFiltered = (clone $query)->count();
        $totalPages = (int) ceil($totalFiltered / $length);
        if ($page > $totalPages && $totalPages > 0)
            $page = 1;


        $records = $query
            ->orderBy($sortBy, $sortDir)
            ->offset(($page - 1) * $length)
            ->limit($length)
            ->get();

        // 🧮 Map output
        $data = $records->map(function ($row) {
            $clickRate = $row->impressions > 0
                ? round(($row->clicks / $row->impressions) * 100, 2)
                : 0.00;

            return [
                'id' => $row->id,
                'url' => $row->url,
                'date' => $row->date,
                'keyword' => $row->keyword,
                'country' => $row->country,
                'device' => $row->device,
                'total_clicks' => $row->clicks ?? 0,
                'impressions' => $row->impressions ?? 0,
                'click_rate' => $clickRate,
                'position' => $row->position ?? 0,
            ];
        });

        // ✅ Return JSON
        return response()->json([
            'success' => true,
            'message' => __('msg_rec_list'),
            'data' => $data,
            'total_pages' => $totalPages,
            'total_records' => $totalFiltered,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/save-llms-seo-monitoring",
     *     summary="Save LLMS Google sitemap SERP monitoring data",
     *     description="Returns product performance data including ID, URL, clicks, position, impressions, and CTR.",
     *     tags={"Google Console Monitoring"},      
     *     security={{"bearerAuth":{}}},
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

    public function store(Request $request)
    {
        ini_set('max_execution_time', 712);
        set_time_limit(712);
        try {
            // 1️⃣ Load credentials
            $scriptPath = base_path('app/Script/the-horecastore-usa-478610-d9b99c2833ec.json');
           if (!file_exists($scriptPath)) {
                return response()->json(['success' => false, 'message' => 'File not found '.$scriptPath]);
            } 
            $credentials = json_decode(file_get_contents($scriptPath), true);
 
            // 2️⃣ Create JWT for Google API
            $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $now = time();
            $jwtClaim = base64_encode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now
            ]));
            $privateKey = openssl_get_privatekey($credentials['private_key']);
            openssl_sign("$jwtHeader.$jwtClaim", $signature, $privateKey, 'sha256WithRSAEncryption');
            $jwt = "$jwtHeader.$jwtClaim." . base64_encode($signature);

            // 3️⃣ Get access token
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://oauth2.googleapis.com/token',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]),
            ]);
            $response = json_decode(curl_exec($curl), true);
            curl_close($curl);

            if (empty($response['access_token'])) {
                return response()->json(['success' => false, 'message' => 'Unable to fetch access token']);
            }
            $token = $response['access_token'];

            // 4️⃣ Determine site URL
                $siteUrl = "";
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://searchconsole.googleapis.com/webmasters/v3/sites",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
                ]);
                $siteListResponse = json_decode(curl_exec($ch), true);
                curl_close($ch);

                if (!empty($siteListResponse['siteEntry'])) {
                    foreach ($siteListResponse['siteEntry'] as $entry) {
                        if ($entry['permissionLevel'] !== 'siteUnverifiedUser') {
                            $siteUrl = $entry['siteUrl'];
                            break;
                        }
                    }
                }
            

            if (empty($siteUrl)) {
                return response()->json(['success' => false, 'message' => 'No verified site found']);
            }

            if (!empty($request->from_date)) {
                $startDate = date('Y-m-d', strtotime($request->from_date));
            } else {
                $startDate = date('Y-m-d', strtotime('-5 days'));
            }

            if (!empty($request->to_date)) {
                $endDate = date('Y-m-d', strtotime($request->to_date));
            } else {
                $endDate = date('Y-m-d');
            }

            $length = 20;
            $maxRows = 5000;
            $startRow = 0;
            $sitemapList = [];
            do {
                $payload = json_encode([
                    "startDate" => $startDate,
                    "endDate" => $endDate,
                    "dimensions" => ["date", "page", "query", "country", "device"],
                    "rowLimit" => 5000,
                    "startRow" => 0
                ]);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://searchconsole.googleapis.com/webmasters/v3/sites/" . urlencode($siteUrl) . "/searchAnalytics/query",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        "Authorization: Bearer $token",
                        "Content-Type: application/json"
                    ],
                    CURLOPT_POSTFIELDS => $payload
                ]);

                $result = curl_exec($ch);
                curl_close($ch);

                $data = json_decode($result, true);
                $rows = $data['rows'] ?? [];
                $count = count($rows);

                foreach ($rows as $row) {
                    $keys = $row['keys'] ?? [];

                    $record = [
                        'url' => $keys[1] ?? null,
                        'date' => $keys[0] ?? null,
                        'keyword' => $keys[2] ?? null,
                        'country' => $keys[3] ?? null,
                        'device' => $keys[4] ?? null,
                        'total_clicks' => $row['clicks'] ?? 0,
                        'impressions' => $row['impressions'] ?? 0,
                        'click_rate' => round($row['ctr'] ?? 0, 4),
                        'position' => round($row['position'] ?? 0, 3),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    SeoMonitoring::updateOrCreate(
                        [
                            'url' => $record['url'],
                            'date' => $record['date'],
                            'keyword' => $record['keyword']

                        ],
                        $record
                    );
                }

                $startRow += $length;
            } while ($count === $length && $startRow < $maxRows);


            return response()->json([
                'success' => true,
                'message' => 'Record inserted successfully.',
                'site' => config('app.url'),
                'count' => $count
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __("err_update"),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/live-show-llms-seo-monitoring",
     *     summary="Live Show of LLMS Google sitemap SERP monitoring data",
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
    public function show(Request $request)
    {
        ini_set('max_execution_time', 300);
        set_time_limit(300);
        $client = new \Google_Client();
        $scriptPath = base_path('app/Script/the-horecastore-usa-478610-d9b99c2833ec.json');
        if (!file_exists($scriptPath)) {
                return response()->json(['success' => false, 'message' => 'File not found '.$scriptPath]);
        } 
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
            $request->setDimensions(["date", "page", "query", "country", "device"]);
            $request->setRowLimit($length);
            $request->setStartRow(($page - 1) * $length);

            $response = $service->searchanalytics->query($siteUrl, $request);
            $rows = $response->getRows() ?? [];
            $count = count($rows);

            foreach ($rows as $row) {
                $keys = $row->getKeys();
                $sitemapList[] = [

                    'url' => $keys[1] ?? null,
                    'date' => $keys[0] ?? null,
                    'keyword' => $keys[2] ?? null,
                    'country' => $keys[3] ?? null,
                    'device' => $keys[4] ?? null,
                    'total_clicks' => $row->getClicks() ?? 0,
                    'impressions' => $row->getImpressions(),
                    'click_rate' => round($row->getCtr(), 4),
                    'position' => round($row->getPosition(), 3),
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
