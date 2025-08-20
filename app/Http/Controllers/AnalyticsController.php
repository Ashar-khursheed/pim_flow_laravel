<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\GoogleAnalytics;

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
            $data = $this->ga->getRealTimeAnalytics($this->propertyId);
            
            $totalActiveUsers = array_sum(array_column($data, 'activeUsers'));
            
            return response()->json([
                'status' => 'success',
                'total_active_users' => $totalActiveUsers,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/analytics/demographics",
     *     summary="Get Audience Demographics",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Demographics data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function audienceDemographics(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            $data = $this->ga->getAudienceDemographics($this->propertyId, $startDate, $endDate);
            
            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

  public function completeDashboard(Request $request)
{
    $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
    $endDate = $request->get('end_date', now()->format('Y-m-d'));
    $page = max((int) $request->get('page', 1), 1);
    $perPage = min((int) $request->get('per_page', 10), 100);

    try {
        $sessions = $this->ga->getSessionsByDate($this->propertyId, $startDate, $endDate);
        $totalSessions = count($sessions);
        $paginatedSessions = array_slice($sessions, ($page - 1) * $perPage, $perPage);

        $dashboard = [
            'overview' => $this->ga->getOverview($this->propertyId, $startDate, $endDate),
            'sessions_by_date' => $paginatedSessions,
            'page_info' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalSessions,
                'total_pages' => ceil($totalSessions / $perPage),
            ],
            'device_analytics' => $this->ga->getDeviceAnalytics($this->propertyId, $startDate, $endDate),
            'geographic_data' => $this->ga->getGeographicAnalytics($this->propertyId, $startDate, $endDate),
            'conversions' => $this->ga->getConversionAnalytics($this->propertyId, $startDate, $endDate),
            'abandoned_cart' => $this->ga->getAbandonedCartAnalytics($this->propertyId, $startDate, $endDate),
            'traffic_sources' => $this->ga->getTrafficSources($this->propertyId, $startDate, $endDate),
            'page_analytics' => $this->ga->getPageAnalytics($this->propertyId, $startDate, $endDate),
            'event_analytics' => $this->ga->getEventAnalytics($this->propertyId, $startDate, $endDate),
            'realtime' => $this->ga->getRealTimeAnalytics($this->propertyId),
            'demographics' => $this->ga->getAudienceDemographics($this->propertyId, $startDate, $endDate),
            'generated_at' => now()->toISOString(),
            'period' => ['start' => $startDate, 'end' => $endDate]
        ];

        return response()->json([
            'status' => 'success',
            'dashboard' => $dashboard
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
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
     *     @OA\Response(response=200, description="E-commerce funnel data"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function ecommerceFunnel(Request $request)
    {
        $startDate = $request->get('start_date', '30daysAgo');
        $endDate = $request->get('end_date', 'today');

        try {
            // Get funnel data for different stages
            $funnelStages = [
                'view_item' => 'Product Views',
                'add_to_cart' => 'Add to Cart',
                'view_cart' => 'Cart Views',
                'begin_checkout' => 'Checkout Started',
                'add_shipping_info' => 'Shipping Added',
                'add_payment_info' => 'Payment Added',
                'purchase' => 'Purchase Completed'
            ];

            $funnelData = [];
            foreach ($funnelStages as $event => $label) {
                try {
                    $request = new \Google\Service\AnalyticsData\RunReportRequest();
                    $request->setDateRanges([
                        new \Google\Service\AnalyticsData\DateRange(['start_date' => $startDate, 'end_date' => $endDate])
                    ]);
                    $request->setMetrics([
                        new \Google\Service\AnalyticsData\Metric(['name' => 'eventCount']),
                        new \Google\Service\AnalyticsData\Metric(['name' => 'totalUsers'])
                    ]);
                    $request->setDimensionFilter(
                        new \Google\Service\AnalyticsData\FilterExpression([
                            'filter' => new \Google\Service\AnalyticsData\Filter([
                                'field_name' => 'eventName',
                                'string_filter' => new \Google\Service\AnalyticsData\StringFilter([
                                    'match_type' => 'EXACT',
                                    'value' => $event
                                ])
                            ])
                        ])
                    );

                    $response = $this->ga->analyticsData->properties->runReport("properties/{$this->propertyId}", $request);

                    $row = $response->getRows()[0] ?? null;
                    $funnelData[$event] = [
                        'label' => $label,
                        'events' => $row ? (int)$row->getMetricValues()[0]->getValue() : 0,
                        'users' => $row ? (int)$row->getMetricValues()[1]->getValue() : 0
                    ];
                } catch (\Exception $e) {
                    $funnelData[$event] = [
                        'label' => $label,
                        'events' => 0,
                        'users' => 0
                    ];
                }
            }

            // Calculate conversion rates
            $conversions = [];
            $stages = array_keys($funnelStages);
            for ($i = 1; $i < count($stages); $i++) {
                $current = $funnelData[$stages[$i]]['users'];
                $previous = $funnelData[$stages[$i-1]]['users'];
                $conversions[$stages[$i]] = $previous > 0 ? round(($current / $previous) * 100, 2) : 0;
            }

            return response()->json([
                'status' => 'success',
                'funnel_data' => $funnelData,
                'conversion_rates' => $conversions,
                'insights' => [
                    'total_started' => $funnelData['view_item']['users'],
                    'total_completed' => $funnelData['purchase']['users'],
                    'overall_conversion_rate' => $funnelData['view_item']['users'] > 0 ? 
                        round(($funnelData['purchase']['users'] / $funnelData['view_item']['users']) * 100, 2) : 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
            $reportRequest->setDateRanges([
                new \Google\Service\AnalyticsData\DateRange(['start_date' => $startDate, 'end_date' => $endDate])
            ]);
            $reportRequest->setMetrics([
                new \Google\Service\AnalyticsData\Metric(['name' => 'cohortActiveUsers']),
                new \Google\Service\AnalyticsData\Metric(['name' => 'cohortTotalUsers'])
            ]);
            $reportRequest->setDimensions([
                new \Google\Service\AnalyticsData\Dimension(['name' => 'cohort']),
                new \Google\Service\AnalyticsData\Dimension(['name' => 'cohortNthDay'])
            ]);
            
            $cohortSpec = new \Google\Service\AnalyticsData\CohortSpec();
            $cohortSpec->setCohorts([
                new \Google\Service\AnalyticsData\Cohort([
                    'name' => 'cohort_1',
                    'date_range' => new \Google\Service\AnalyticsData\DateRange([
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ])
                ])
            ]);
            $cohortSpec->setCohortsRange(
                new \Google\Service\AnalyticsData\CohortsRange([
                    'granularity' => 'DAILY',
                    'end_offset' => 7
                ])
            );
            $reportRequest->setCohortSpec($cohortSpec);

            $response = $this->ga->analyticsData->properties->runReport("properties/{$this->propertyId}", $reportRequest);

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