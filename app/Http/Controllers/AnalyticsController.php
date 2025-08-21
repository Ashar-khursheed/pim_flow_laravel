<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\GoogleAnalytics;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;

class AnalyticsController extends Controller
{
    private $ga;
    private $propertyId;

    public function __construct()
    {
        $this->ga = new GoogleAnalytics();
        $this->propertyId = "441790093"; // Your GA4 property ID
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/overview",
     *     summary="Get Analytics Overview",
     *     tags={"Analytics"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date",
     *         required=false,
     *         @OA\Schema(type="string", example="30daysAgo")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query", 
     *         description="End date",
     *         required=false,
     *         @OA\Schema(type="string", example="today")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful Response"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function overview(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            // Use the safer method if available, fallback to original
            $data = method_exists($this->ga, 'getOverviewSafe') 
                ? $this->ga->getOverviewSafe($this->propertyId, $startDate, $endDate)
                : $this->ga->getOverview($this->propertyId, $startDate, $endDate);
            
            return response()->json([
                'status' => 'success',
                'data' => $data,
                'period' => ['start' => $startDate, 'end' => $endDate]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
                'data' => [
                    'sessions' => 0,
                    'totalUsers' => 0,
                    'newUsers' => 0,
                    'returningUsers' => 0,
                    'pageViews' => 0,
                    'bounceRate' => 0,
                    'avgSessionDuration' => 0
                ]
            ], 500);
        }
    }
//     public function overview(Request $request)
// {
//     $startDate = $request->get('start_date', '30daysAgo');
//     $endDate = $request->get('end_date', 'today');

//     try {
//         // Initialize GA4 client
//         $client = new BetaAnalyticsDataClient([
//             'credentials' => storage_path('app/analytics-key.json')
//         ]);

//         // Build request
//         $requestGA = new RunReportRequest([
//             'property' => "properties/{$this->propertyId}",
//             'dateRanges' => [
//                 new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
//             ],
//             'metrics' => [
//                 new Metric(['name' => 'sessions']),
//                 new Metric(['name' => 'activeUsers']),
//                 new Metric(['name' => 'newUsers']),
//                 new Metric(['name' => 'screenPageViews']),
//                 new Metric(['name' => 'bounceRate']),
//                 new Metric(['name' => 'averageSessionDuration'])
//             ]
//         ]);

//         $response = $client->runReport($requestGA);

//         // Debug: dump GA response if empty
//         if (count($response->getRows()) === 0) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'GA returned no rows. Check property ID, credentials, metrics, or date range.',
//                 'raw_response' => $response->serializeToJsonString()
//             ], 500);
//         }

//         $row = $response->getRows()[0];
//         $metrics = $row->getMetricValues();

//         $sessions = isset($metrics[0]) ? (int)$metrics[0]->getValue() : 0;
//         $activeUsers = isset($metrics[1]) ? (int)$metrics[1]->getValue() : 0;
//         $newUsers = isset($metrics[2]) ? (int)$metrics[2]->getValue() : 0;
//         $pageViews = isset($metrics[3]) ? (int)$metrics[3]->getValue() : 0;
//         $bounceRate = isset($metrics[4]) ? round((float)$metrics[4]->getValue() * 100, 2) : 0;
//         $avgSessionDuration = isset($metrics[5]) ? (float)$metrics[5]->getValue() : 0;

//         return response()->json([
//             'status' => 'success',
//             'period' => ['start' => $startDate, 'end' => $endDate],
//             'data' => [
//                 'sessions' => $sessions,
//                 'totalUsers' => $activeUsers,
//                 'newUsers' => $newUsers,
//                 'returningUsers' => max(0, $activeUsers - $newUsers),
//                 'pageViews' => $pageViews,
//                 'bounceRate' => $bounceRate,
//                 'avgSessionDuration' => $avgSessionDuration
//             ]
//         ]);

//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => 'error',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }


    /**
     * @OA\Get(
     *     path="/api/analytics/sessions-by-date",
     *     summary="Get Sessions by Date",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful Response"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function sessionsByDate(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            $data = $this->ga->getSessionsByDate($this->propertyId, $startDate, $endDate);
            
            return response()->json([
                'status' => 'success',
                'data' => $data,
                'total_days' => count($data)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/device",
     *     summary="Get Device Analytics",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Device analytics data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function deviceAnalytics(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            $data = $this->ga->getDeviceAnalytics($this->propertyId, $startDate, $endDate);
            
            // Group by device category for summary
            $deviceSummary = [];
            foreach ($data as $row) {
                $device = $row['device'];
                if (!isset($deviceSummary[$device])) {
                    $deviceSummary[$device] = [
                        'sessions' => 0,
                        'users' => 0,
                        'conversions' => 0,
                        'revenue' => 0
                    ];
                }
                $deviceSummary[$device]['sessions'] += $row['sessions'];
                $deviceSummary[$device]['users'] += $row['users'];
                $deviceSummary[$device]['conversions'] += $row['conversions'];
                $deviceSummary[$device]['revenue'] += $row['revenue'];
            }
            
            return response()->json([
                'status' => 'success',
                'summary' => $deviceSummary,
                'detailed' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/geographic",
     *     summary="Get Geographic Analytics", 
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Geographic analytics data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function geographicAnalytics(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            $data = $this->ga->getGeographicAnalytics($this->propertyId, $startDate, $endDate);
            
            // Group by country for summary
            $countrySummary = [];
            foreach ($data as $row) {
                $country = $row['country'];
                if (!isset($countrySummary[$country])) {
                    $countrySummary[$country] = [
                        'sessions' => 0,
                        'users' => 0,
                        'conversions' => 0,
                        'revenue' => 0,
                        'cities' => []
                    ];
                }
                $countrySummary[$country]['sessions'] += $row['sessions'];
                $countrySummary[$country]['users'] += $row['users'];
                $countrySummary[$country]['conversions'] += $row['conversions'];
                $countrySummary[$country]['revenue'] += $row['revenue'];
                $countrySummary[$country]['cities'][] = $row['city'];
            }
            
            return response()->json([
                'status' => 'success',
                'by_country' => $countrySummary,
                'detailed' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/test",
     *     summary="Test Analytics Connection",
     *     tags={"Analytics"},
     *     @OA\Response(response=200, description="Test successful"),
     *     @OA\Response(response=500, description="Test failed")
     * )
     */
    public function testAnalytics()
    {
        try {
            // Test basic connection
            $basicData = $this->ga->getBasicConversions($this->propertyId, '7daysAgo', 'today');
            
            return response()->json([
                'status' => 'success',
                'message' => 'Analytics connection working',
                'property_id' => $this->propertyId,
                'test_data' => $basicData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Analytics connection failed',
                'error' => $e->getMessage(),
                'property_id' => $this->propertyId
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/conversions",
     *     summary="Get Conversion Analytics",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Conversion analytics data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function conversionAnalytics(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            // Use safer method if available
            $data = method_exists($this->ga, 'getConversionAnalyticsSafe') 
                ? $this->ga->getConversionAnalyticsSafe($this->propertyId, $startDate, $endDate)
                : $this->ga->getConversionAnalytics($this->propertyId, $startDate, $endDate);
            
            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/abandoned-cart",
     *     summary="Get Abandoned Cart Analytics",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Abandoned cart analytics"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function abandonedCartAnalytics(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            $data = $this->ga->getAbandonedCartAnalytics($this->propertyId, $startDate, $endDate);
            
            return response()->json([
                'status' => 'success',
                'data' => $data,
                'insights' => [
                    'potential_lost_revenue' => 'Calculate based on average order value',
                    'recovery_opportunities' => $data['abandonedCarts'] + $data['abandonedCheckouts']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/traffic-sources",
     *     summary="Get Traffic Sources Analytics",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Traffic sources data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function trafficSources(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            $data = $this->ga->getTrafficSources($this->propertyId, $startDate, $endDate);
            
            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/pages",
     *     summary="Get Page Analytics",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Page analytics data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function pageAnalytics(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            $data = $this->ga->getPageAnalytics($this->propertyId, $startDate, $endDate);
            
            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/events",
     *     summary="Get Event Analytics",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Event analytics data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function eventAnalytics(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            $data = $this->ga->getEventAnalytics($this->propertyId, $startDate, $endDate);
            
            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/realtime",
     *     summary="Get Real-time Analytics",
     *     tags={"Analytics"},
     *     @OA\Response(response=200, description="Real-time analytics data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
 public function realTimeAnalytics()
    {
        try {
            $propertyId = '441790093';
            
            // Check credentials file exists
            $credentialsPath = base_path('app/Script/analytics-key.json');
            if (!file_exists($credentialsPath)) {
                throw new \Exception('Credentials file not found at: ' . $credentialsPath);
            }
            
            // Initialize GA4 client with correct namespace
            $client = new BetaAnalyticsDataClient([
                'credentials' => $credentialsPath
            ]);

            // Create real-time request object
            $request = new RunRealtimeReportRequest();
            $request->setProperty("properties/{$propertyId}");
            
            // Set dimensions
            $request->setDimensions([
                new Dimension(['name' => 'country']),
                new Dimension(['name' => 'deviceCategory']),
            ]);
            
            // Set metrics
            $request->setMetrics([
                new Metric(['name' => 'activeUsers']),
            ]);

            $response = $client->runRealtimeReport($request);
            
            $data = [];
            $totalActiveUsers = 0;

            // Process real data
            foreach ($response->getRows() as $row) {
                $dimensionValues = $row->getDimensionValues();
                $metricValues = $row->getMetricValues();
                
                $country = isset($dimensionValues[0]) ? $dimensionValues[0]->getValue() : 'Unknown';
                $device = isset($dimensionValues[1]) ? $dimensionValues[1]->getValue() : 'Unknown';
                $activeUsers = isset($metricValues[0]) ? (int)$metricValues[0]->getValue() : 0;

                $data[] = [
                    'country' => $country,
                    'device' => $device,
                    'activeUsers' => $activeUsers,
                ];
                $totalActiveUsers += $activeUsers;
            }

            return response()->json([
                'status' => 'success',
                'total_active_users' => $totalActiveUsers,
                'data' => $data,
                'timestamp' => now()->toISOString(),
                'is_real_data' => true,
                'property_id' => $propertyId
            ]);

        } catch (\Google\ApiCore\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'error_type' => 'validation',
                'message' => 'Invalid request parameters: ' . $e->getMessage(),
                'suggestions' => [
                    'Check if property ID is correct',
                    'Verify real-time reporting is enabled in GA4'
                ]
            ], 400);
            
        } catch (\Google\ApiCore\ApiException $e) {
            return response()->json([
                'status' => 'error',
                'error_type' => 'api',
                'message' => 'Google Analytics API error: ' . $e->getMessage(),
                'code' => $e->getCode()
            ], 500);
            
        } catch (\Exception $e) {
            \Log::error('Real-time Analytics Error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'status' => 'error',
                'error_type' => 'general',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'debug_info' => [
                    'credentials_path' => $credentialsPath ?? 'not_set',
                    'credentials_exists' => isset($credentialsPath) ? file_exists($credentialsPath) : false
                ]
            ], 500);
        }
    }



    /**
     * @OA\Post(
     *     path="/api/analytics/custom-report",
     *     summary="Get Custom Analytics Report",
     *     tags={"Analytics"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"metrics"},
     *             @OA\Property(property="metrics", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="dimensions", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="start_date", type="string"),
     *             @OA\Property(property="end_date", type="string"),
     *             @OA\Property(property="filters", type="object")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Custom report data"),
     *     @OA\Response(response=422, description="Validation Error"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function customReport(Request $request)
    {
        $request->validate([
            'metrics' => 'required|array',
            'dimensions' => 'array',
            'start_date' => 'string',
            'end_date' => 'string',
            'filters' => 'array'
        ]);

        $metrics = $request->get('metrics');
        $dimensions = $request->get('dimensions', []);
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');
        $filters = $request->get('filters', []);

        try {
            // Build the report request
            $reportRequest = [
                'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                'metrics' => array_map(fn($m) => ['name' => $m], $metrics),
                'dimensions' => array_map(fn($d) => ['name' => $d], $dimensions)
            ];

            // Add filters if provided
            if (!empty($filters)) {
                $reportRequest['dimensionFilter'] = $filters;
            }

            $response = $this->ga->analyticsData->properties->runReport($this->propertyId, $reportRequest);

            $data = [];
            foreach ($response->getRows() as $row) {
                $rowData = [];
                
                // Add dimensions
                foreach ($row->getDimensionValues() as $index => $dimension) {
                    $rowData[$dimensions[$index]] = $dimension->getValue();
                }
                
                // Add metrics
                foreach ($row->getMetricValues() as $index => $metric) {
                    $rowData[$metrics[$index]] = $metric->getValue();
                }
                
                $data[] = $rowData;
            }

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'request_info' => [
                    'metrics' => $metrics,
                    'dimensions' => $dimensions,
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'filters_applied' => !empty($filters)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

 /**
 * @OA\Get(
 *     path="/api/analytics/ecommerce-funnel",
 *     summary="Get E-commerce Funnel Analytics",
 *     tags={"Analytics"},
 *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="mock", in="query", required=false, @OA\Schema(type="boolean", description="Use mock data for testing")),
 *     @OA\Response(response=200, description="E-commerce funnel data"),
 *     @OA\Response(response=500, description="Internal Server Error")
 * )
 */
public function ecommerceFunnel(Request $request)
{
    $startDate = $request->get('start_date', '30daysAgo');
    $endDate = $request->get('end_date', 'today');
    $useMock = $request->get('mock', false);

    try {
        // Set a timeout for the operation
        set_time_limit(60); // 60 seconds max for GA API calls
        
        $data = [];
        
       $data = $this->ga->getEcommerceFunnel($this->propertyId, $startDate, $endDate);

        
        // Validate data structure
        if (!isset($data['funnel_data']) || !isset($data['conversion_rates']) || !isset($data['insights'])) {
            throw new \Exception('Invalid funnel data structure returned');
        }
        
        return response()->json([
            'status' => 'success',
            'funnel_data' => $data['funnel_data'],
            'conversion_rates' => $data['conversion_rates'],
            'insights' => $data['insights'],
            'period' => [
                'start' => $startDate, 
                'end' => $endDate
            ],
            'data_source' => $useMock || !method_exists($this->ga, 'getEcommerceFunnel') ? 'mock' : 'ga4',
            'generated_at' => now()->toISOString()
        ]);
        
    } catch (\Exception $e) {
        // Log the error for debugging
        \Log::error('Ecommerce funnel error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'property_id' => $this->propertyId ?? 'not_set'
        ]);
        
        return response()->json([
            'status' => 'error',
            'error' => 'Failed to retrieve funnel data: ' . $e->getMessage(),
            'funnel_data' => $this->getEmptyFunnelData(),
            'conversion_rates' => $this->getEmptyConversionRates(),
            'insights' => $this->getEmptyInsights(),
            'period' => [
                'start' => $startDate, 
                'end' => $endDate
            ],
            'data_source' => 'error_fallback',
            'generated_at' => now()->toISOString()
        ], 500);
    }
}

/**
 * Get empty funnel data structure for error fallback
 */
private function getEmptyFunnelData()
{
    return [
        ['step' => 'Product Views', 'users' => 0, 'conversion_rate' => 0],
        ['step' => 'Add to Cart', 'users' => 0, 'conversion_rate' => 0],
        ['step' => 'Checkout Started', 'users' => 0, 'conversion_rate' => 0],
        ['step' => 'Purchase', 'users' => 0, 'conversion_rate' => 0]
    ];
}

/**
 * Get empty conversion rates for error fallback
 */
private function getEmptyConversionRates()
{
    return [
        'view_to_cart' => 0,
        'cart_to_checkout' => 0,
        'checkout_to_purchase' => 0,
        'overall_conversion_rate' => 0
    ];
}

/**
 * Get empty insights for error fallback
 */
private function getEmptyInsights()
{
    return [
        'total_started' => 0,
        'total_completed' => 0,
        'overall_conversion_rate' => 0,
        'total_revenue' => 0,
        'average_order_value' => 0
    ];
}

// ========================================
// Add this to your GoogleAnalytics Helper Class
// File: app/Helpers/GoogleAnalytics.php
// ========================================

/**
 * Get basic ecommerce funnel data (mock/fallback implementation)
 * 
 * @param string $propertyId
 * @param string $startDate
 * @param string $endDate
 * @return array
 */
public function getBasicEcommerceFunnel($propertyId, $startDate, $endDate)
{
    // You can either return mock data or implement a simpler GA4 query
    
    // Option 1: Mock data for testing
    if (config('app.env') === 'local' || request()->get('mock')) {
        return $this->getMockFunnelData();
    }
    
    // Option 2: Simplified GA4 implementation
    try {
        return $this->getSimplifiedEcommerceFunnel($propertyId, $startDate, $endDate);
    } catch (\Exception $e) {
        \Log::warning('Basic ecommerce funnel failed, returning mock data: ' . $e->getMessage());
        return $this->getMockFunnelData();
    }
}

/**
 * Get mock funnel data for testing/fallback
 */
private function getMockFunnelData()
{
    $baseUsers = rand(800, 1500);
    $addToCartRate = rand(15, 30) / 100;
    $checkoutRate = rand(30, 50) / 100;
    $purchaseRate = rand(40, 70) / 100;
    
    $addToCartUsers = (int)($baseUsers * $addToCartRate);
    $checkoutUsers = (int)($addToCartUsers * $checkoutRate);
    $purchaseUsers = (int)($checkoutUsers * $purchaseRate);
    
    return [
        'funnel_data' => [
            [
                'step' => 'Product Views',
                'users' => $baseUsers,
                'conversion_rate' => 100,
                'events' => $baseUsers * rand(2, 5)
            ],
            [
                'step' => 'Add to Cart',
                'users' => $addToCartUsers,
                'conversion_rate' => round(($addToCartUsers / $baseUsers) * 100, 2),
                'events' => $addToCartUsers * rand(1, 3)
            ],
            [
                'step' => 'Checkout Started',
                'users' => $checkoutUsers,
                'conversion_rate' => round(($checkoutUsers / $baseUsers) * 100, 2),
                'events' => $checkoutUsers
            ],
            [
                'step' => 'Purchase',
                'users' => $purchaseUsers,
                'conversion_rate' => round(($purchaseUsers / $baseUsers) * 100, 2),
                'events' => $purchaseUsers,
                'revenue' => $purchaseUsers * rand(50, 200)
            ]
        ],
        'conversion_rates' => [
            'view_to_cart' => round(($addToCartUsers / $baseUsers) * 100, 2),
            'cart_to_checkout' => $addToCartUsers > 0 ? round(($checkoutUsers / $addToCartUsers) * 100, 2) : 0,
            'checkout_to_purchase' => $checkoutUsers > 0 ? round(($purchaseUsers / $checkoutUsers) * 100, 2) : 0,
            'overall_conversion_rate' => round(($purchaseUsers / $baseUsers) * 100, 2)
        ],
        'insights' => [
            'total_started' => $baseUsers,
            'total_completed' => $purchaseUsers,
            'overall_conversion_rate' => round(($purchaseUsers / $baseUsers) * 100, 2),
            'total_revenue' => $purchaseUsers * rand(50, 200),
            'average_order_value' => $purchaseUsers > 0 ? rand(50, 200) : 0,
            'biggest_drop_off' => $this->getBiggestDropOff($baseUsers, $addToCartUsers, $checkoutUsers, $purchaseUsers)
        ]
    ];
}

/**
 * Get simplified ecommerce funnel from GA4
 */
private function getSimplifiedEcommerceFunnel($propertyId, $startDate, $endDate)
{
    // Basic GA4 implementation - you'll need to adapt this to your GA4 client
    try {
        // This is a simplified example - adjust based on your GA4 setup
        $metrics = [
            'sessions',
            'addToCarts', 
            'beginCheckouts',
            'purchases',
            'purchaseRevenue'
        ];
        
        $dimensions = ['date'];
        
        // Make GA4 API call (pseudo-code - adapt to your GA4 client)
        $response = $this->makeGA4Request($propertyId, $startDate, $endDate, $metrics, $dimensions);
        
        return $this->processGA4FunnelResponse($response);
        
    } catch (\Exception $e) {
        throw new \Exception('GA4 API call failed: ' . $e->getMessage());
    }
}

/**
 * Process GA4 response into funnel format
 */
private function processGA4FunnelResponse($response)
{
    // Process your GA4 response here
    // This is pseudo-code - adapt to your actual GA4 response structure
    
    $sessions = $response['sessions'] ?? 0;
    $addToCarts = $response['addToCarts'] ?? 0;
    $beginCheckouts = $response['beginCheckouts'] ?? 0;
    $purchases = $response['purchases'] ?? 0;
    $revenue = $response['purchaseRevenue'] ?? 0;
    
    return [
        'funnel_data' => [
            [
                'step' => 'Product Views',
                'users' => $sessions,
                'conversion_rate' => 100,
                'events' => $sessions
            ],
            [
                'step' => 'Add to Cart',
                'users' => $addToCarts,
                'conversion_rate' => $sessions > 0 ? round(($addToCarts / $sessions) * 100, 2) : 0,
                'events' => $addToCarts
            ],
            [
                'step' => 'Checkout Started',
                'users' => $beginCheckouts,
                'conversion_rate' => $sessions > 0 ? round(($beginCheckouts / $sessions) * 100, 2) : 0,
                'events' => $beginCheckouts
            ],
            [
                'step' => 'Purchase',
                'users' => $purchases,
                'conversion_rate' => $sessions > 0 ? round(($purchases / $sessions) * 100, 2) : 0,
                'events' => $purchases,
                'revenue' => $revenue
            ]
        ],
        'conversion_rates' => [
            'view_to_cart' => $sessions > 0 ? round(($addToCarts / $sessions) * 100, 2) : 0,
            'cart_to_checkout' => $addToCarts > 0 ? round(($beginCheckouts / $addToCarts) * 100, 2) : 0,
            'checkout_to_purchase' => $beginCheckouts > 0 ? round(($purchases / $beginCheckouts) * 100, 2) : 0,
            'overall_conversion_rate' => $sessions > 0 ? round(($purchases / $sessions) * 100, 2) : 0
        ],
        'insights' => [
            'total_started' => $sessions,
            'total_completed' => $purchases,
            'overall_conversion_rate' => $sessions > 0 ? round(($purchases / $sessions) * 100, 2) : 0,
            'total_revenue' => $revenue,
            'average_order_value' => $purchases > 0 ? round($revenue / $purchases, 2) : 0
        ]
    ];
}

/**
 * Find the biggest drop-off point in the funnel
 */
private function getBiggestDropOff($views, $carts, $checkouts, $purchases)
{
    $dropOffs = [
        'view_to_cart' => $views - $carts,
        'cart_to_checkout' => $carts - $checkouts, 
        'checkout_to_purchase' => $checkouts - $purchases
    ];
    
    return array_key_exists(max($dropOffs), $dropOffs) ? 
           array_search(max($dropOffs), $dropOffs) : 'view_to_cart';
}
    /**
     * @OA\Get(
     *     path="/api/analytics/cohort-analysis",
     *     summary="Get Cohort Analysis",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Cohort analysis data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
  public function cohortAnalysis(Request $request)
{
    $startDate = $request->get('start_date', '60daysAgo');
    $endDate = $request->get('end_date', 'today');

    try {
        $reportRequest = new \Google\Service\AnalyticsData\RunReportRequest();

        // ❌ DO NOT set top-level dateRanges for cohort reports
        // $reportRequest->setDateRanges([...]);  <-- remove this

        $reportRequest->setMetrics([
            new \Google\Service\AnalyticsData\Metric(['name' => 'cohortActiveUsers']),
            new \Google\Service\AnalyticsData\Metric(['name' => 'cohortTotalUsers'])
        ]);

        $reportRequest->setDimensions([
            new \Google\Service\AnalyticsData\Dimension(['name' => 'cohort']),
            new \Google\Service\AnalyticsData\Dimension(['name' => 'cohortNthDay'])
        ]);

        // ✅ Define cohortSpec properly
     $cohortSpec = new \Google\Service\AnalyticsData\CohortSpec();
        $cohortSpec->setCohorts([
            new \Google\Service\AnalyticsData\Cohort([
                'name' => 'group1', // ✅ changed name
                'dimension' => 'firstSessionDate',
                'dateRange' => new \Google\Service\AnalyticsData\DateRange([
                    'startDate' => $startDate,
                    'endDate' => $endDate
                ])
            ])
        ]);

        $cohortSpec->setCohortsRange(
            new \Google\Service\AnalyticsData\CohortsRange([
                'granularity' => 'DAILY',
                'endOffset' => 7
            ])
        );

        $reportRequest->setCohortSpec($cohortSpec);


        $response = $this->ga->getAnalyticsData()
            ->properties->runReport("properties/{$this->propertyId}", $reportRequest);

        $cohortData = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();

            $cohort = $dimensions[0]->getValue();
            $nthDay = $dimensions[1]->getValue();
            $activeUsers = (int)$metrics[0]->getValue();
            $totalUsers = (int)$metrics[1]->getValue();

            $cohortData[] = [
                'cohort' => $cohort,
                'day' => $nthDay,
                'active_users' => $activeUsers,
                'total_users' => $totalUsers,
                'retention_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $cohortData
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
}