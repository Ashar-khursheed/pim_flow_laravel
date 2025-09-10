<?php

namespace App\Helpers;

use Google\Client;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\RunReportRequest;
use Google\Service\AnalyticsData\RunRealtimeReportRequest;
use Google\Service\AnalyticsData\DateRange;
use Google\Service\AnalyticsData\Dimension;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\FilterExpression;
use Google\Service\AnalyticsData\Filter;
use Google\Service\AnalyticsData\StringFilter;
use Google\Service\AnalyticsData\OrderBy;
use Google\Service\AnalyticsData\DimensionOrderBy;
use Google\Service\AnalyticsData\MetricOrderBy;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;


class GoogleAnalytics
{
    private $client;
    private $analyticsData;

    public function __construct()
    {
        $this->authenticate();
    }

    private function authenticate()
    {
        $client = new Client();
        $client->setAuthConfig(base_path('app/Script/analytics-key.json'));
        $client->addScope('https://www.googleapis.com/auth/analytics.readonly');

        $this->client = $client;
        $this->analyticsData = new AnalyticsData($client);
    }

    /**
     * Safely get metric value with error handling
     */
    private function safeGetMetric($metrics, $index, $type = 'int', $default = 0)
    {
        try {
            if (!isset($metrics[$index]) || !$metrics[$index]) {
                return $default;
            }
            
            $value = $metrics[$index]->getValue();
            
            switch ($type) {
                case 'int':
                    return (int)$value;
                case 'float':
                    return (float)$value;
                default:
                    return $value;
            }
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Safely get dimension value with error handling
     */
    private function safeGetDimension($dimensions, $index, $default = '')
    {
        try {
            if (!isset($dimensions[$index]) || !$dimensions[$index]) {
                return $default;
            }
            return $dimensions[$index]->getValue();
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Get basic overview metrics
     */
 
    public function getOverview($propertyId, $startDate = '30daysAgo', $endDate = 'today') {
    try {
        $request = new RunReportRequest();
        $request->setDateRanges([
            new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
        ]);
        
        $request->setMetrics([
            new Metric(['name' => 'sessions']),
            new Metric(['name' => 'totalUsers']),
            new Metric(['name' => 'newUsers']),
            new Metric(['name' => 'screenPageViews']),
            new Metric(['name' => 'bounceRate']),
            new Metric(['name' => 'averageSessionDuration']),
            // Add conversion metrics
            new Metric(['name' => 'conversions']),
            new Metric(['name' => 'totalRevenue'])
        ]);

        $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);
        $row = $response->getRows()[0] ?? null;

        if (!$row) {
            return $this->getDefaultOverview();
        }

        $metrics = $row->getMetricValues();
        $totalUsers = $this->safeGetMetric($metrics, 1, 'int', 0);
        $newUsers = $this->safeGetMetric($metrics, 2, 'int', 0);

        return [
            'sessions' => $this->safeGetMetric($metrics, 0, 'int', 0),
            'totalUsers' => $totalUsers,
            'newUsers' => $newUsers,
            'returningUsers' => max(0, $totalUsers - $newUsers),
            'pageViews' => $this->safeGetMetric($metrics, 3, 'int', 0),
            'bounceRate' => round($this->safeGetMetric($metrics, 4, 'float', 0) * 100, 2),
            'avgSessionDuration' => $this->safeGetMetric($metrics, 5, 'float', 0),
            'conversions' => $this->safeGetMetric($metrics, 6, 'int', 0), // Now fetching real data
            'totalRevenue' => $this->safeGetMetric($metrics, 7, 'float', 0.0) // Now fetching real data
        ];

    } catch (\Exception $e) {
        return $this->getDefaultOverview($e->getMessage());
    }
}

// Alternative approach if you need specific conversion events
public function getOverviewWithSpecificConversions($propertyId, $startDate = '30daysAgo', $endDate = 'today') {
    try {
        $request = new RunReportRequest();
        $request->setDateRanges([
            new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
        ]);
        
        $request->setMetrics([
            new Metric(['name' => 'sessions']),
            new Metric(['name' => 'totalUsers']),
            new Metric(['name' => 'newUsers']),
            new Metric(['name' => 'screenPageViews']),
            new Metric(['name' => 'bounceRate']),
            new Metric(['name' => 'averageSessionDuration']),
            new Metric(['name' => 'conversions']),
            new Metric(['name' => 'totalRevenue'])
        ]);

        // If you want to filter by specific conversion events
        $request->setDimensions([
            new Dimension(['name' => 'eventName'])
        ]);
        
        // Filter for specific conversion events
        $request->setDimensionFilter(
            new FilterExpression([
                'filter' => new Filter([
                    'field_name' => 'eventName',
                    'string_filter' => new StringFilter([
                        'match_type' => StringFilter\MatchType::EXACT,
                        'value' => 'purchase' // or your specific conversion event
                    ])
                ])
            ])
        );

        $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);
        
        // Process multiple rows if filtering by event
        $totalConversions = 0;
        $totalRevenue = 0.0;
        
        foreach ($response->getRows() as $row) {
            $metrics = $row->getMetricValues();
            $totalConversions += $this->safeGetMetric($metrics, 6, 'int', 0);
            $totalRevenue += $this->safeGetMetric($metrics, 7, 'float', 0.0);
        }

        // Get basic metrics from first row or separate call
        $basicMetricsRequest = new RunReportRequest();
        $basicMetricsRequest->setDateRanges([
            new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
        ]);
        $basicMetricsRequest->setMetrics([
            new Metric(['name' => 'sessions']),
            new Metric(['name' => 'totalUsers']),
            new Metric(['name' => 'newUsers']),
            new Metric(['name' => 'screenPageViews']),
            new Metric(['name' => 'bounceRate']),
            new Metric(['name' => 'averageSessionDuration'])
        ]);

        $basicResponse = $this->analyticsData->properties->runReport("properties/{$propertyId}", $basicMetricsRequest);
        $basicRow = $basicResponse->getRows()[0] ?? null;

        if (!$basicRow) {
            return $this->getDefaultOverview();
        }

        $basicMetrics = $basicRow->getMetricValues();
        $totalUsers = $this->safeGetMetric($basicMetrics, 1, 'int', 0);
        $newUsers = $this->safeGetMetric($basicMetrics, 2, 'int', 0);

        return [
            'sessions' => $this->safeGetMetric($basicMetrics, 0, 'int', 0),
            'totalUsers' => $totalUsers,
            'newUsers' => $newUsers,
            'returningUsers' => max(0, $totalUsers - $newUsers),
            'pageViews' => $this->safeGetMetric($basicMetrics, 3, 'int', 0),
            'bounceRate' => round($this->safeGetMetric($basicMetrics, 4, 'float', 0) * 100, 2),
            'avgSessionDuration' => $this->safeGetMetric($basicMetrics, 5, 'float', 0),
            'conversions' => $totalConversions,
            'totalRevenue' => $totalRevenue
        ];

    } catch (\Exception $e) {
        return $this->getDefaultOverview($e->getMessage());
    }
}


    /**
     * Get sessions by date
     */
   public function getSessionsByDate($propertyId, $startDate = '30daysAgo', $endDate = 'today')
{
    try {
        $request = new RunReportRequest();
        $request->setDateRanges([
            new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
        ]);
       $request->setMetrics([
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'totalUsers']),
                new Metric(['name' => 'conversions']),
                new Metric(['name' => 'purchaseRevenue'])
            ]);
        $request->setDimensions([new Dimension(['name' => 'date'])]);

        // ✅ Order by date DESC
        $request->setOrderBys([
            new OrderBy([
                'dimension' => new DimensionOrderBy(['dimension_name' => 'date']),
                'desc' => true
            ])
        ]);

        $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();

            // ✅ Format date (from 20250721 → 2025-07-21)
            $rawDate = $this->safeGetDimension($dimensions, 0, 'unknown');
            $formattedDate = \DateTime::createFromFormat('Ymd', $rawDate)->format('Y-m-d');

           $data[] = [
                'date' => $formattedDate,
                'sessions' => $this->safeGetMetric($metrics, 0, 'int', 0),
                'users' => $this->safeGetMetric($metrics, 1, 'int', 0),
                'conversions' => $this->safeGetMetric($metrics, 2, 'int', 0),
                'revenue' => $this->safeGetMetric($metrics, 3, 'float', 0.0)
            ];
        }

        return $data;
    } catch (\Exception $e) {
        return [
            [
                'date' => date('Y-m-d'),
                'sessions' => 0,
                'users' => 0,
                'conversions' => 0,
                'revenue' => 0.0,
                'error' => $e->getMessage()
            ]
        ];
    }
}


    /**
     * Get device analytics
     */
    public function getDeviceAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        try {
            $request = new RunReportRequest();
            $request->setDateRanges([
                new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
            ]);
            $request->setMetrics([
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'totalUsers']),
                new Metric(['name' => 'bounceRate']),
                new Metric(['name' => 'averageSessionDuration'])
            ]);
            $request->setDimensions([
                new Dimension(['name' => 'deviceCategory'])
            ]);

            $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

            $data = [];
            foreach ($response->getRows() as $row) {
                $dimensions = $row->getDimensionValues();
                $metrics = $row->getMetricValues();
                
                $data[] = [
                    'device' => $this->safeGetDimension($dimensions, 0, 'unknown'),
                    'os' => 'unknown',
                    'browser' => 'unknown',
                    'sessions' => $this->safeGetMetric($metrics, 0, 'int', 0),
                    'users' => $this->safeGetMetric($metrics, 1, 'int', 0),
                    'bounceRate' => round($this->safeGetMetric($metrics, 2, 'float', 0) * 100, 2),
                    'avgSessionDuration' => $this->safeGetMetric($metrics, 3, 'float', 0),
                    'conversions' => 0,
                    'revenue' => 0.0
                ];
            }
            return $data;
        } catch (\Exception $e) {
            return [
                [
                    'device' => 'unknown',
                    'os' => 'unknown',
                    'browser' => 'unknown',
                    'sessions' => 0,
                    'users' => 0,
                    'bounceRate' => 0,
                    'avgSessionDuration' => 0,
                    'conversions' => 0,
                    'revenue' => 0.0,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Get geographic analytics
     */
    public function getGeographicAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        try {
            $request = new RunReportRequest();
            $request->setDateRanges([
                new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
            ]);
            $request->setMetrics([
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'totalUsers'])
            ]);
            $request->setDimensions([
                new Dimension(['name' => 'country']),
                new Dimension(['name' => 'city'])
            ]);
            $request->setOrderBys([
                new OrderBy([
                    'metric' => new MetricOrderBy(['metric_name' => 'sessions']),
                    'desc' => true
                ])
            ]);

            $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

            $data = [];
            foreach ($response->getRows() as $row) {
                $dimensions = $row->getDimensionValues();
                $metrics = $row->getMetricValues();
                
                $data[] = [
                    'country' => $this->safeGetDimension($dimensions, 0, 'Unknown'),
                    'region' => 'Unknown',
                    'city' => $this->safeGetDimension($dimensions, 1, 'Unknown'),
                    'sessions' => $this->safeGetMetric($metrics, 0, 'int', 0),
                    'users' => $this->safeGetMetric($metrics, 1, 'int', 0),
                    'conversions' => 0,
                    'revenue' => 0.0,
                    'avgSessionDuration' => 0
                ];
            }
            return $data;
        } catch (\Exception $e) {
            return [
                [
                    'country' => 'Unknown',
                    'region' => 'Unknown',
                    'city' => 'Unknown',
                    'sessions' => 0,
                    'users' => 0,
                    'conversions' => 0,
                    'revenue' => 0.0,
                    'avgSessionDuration' => 0,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Get basic conversion data - SAFE VERSION
     */
    public function getConversionAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        try {
            $request = new RunReportRequest();
            $request->setDateRanges([
                new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
            ]);
            $request->setMetrics([
                new Metric(['name' => 'conversions'])
            ]);

            $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

            $row = $response->getRows()[0] ?? null;
            $conversions = $row ? $this->safeGetMetric($row->getMetricValues(), 0, 'int', 0) : 0;

            return [
                [
                    'eventName' => 'total_conversions',
                    'conversions' => $conversions,
                    'totalRevenue' => 0.0
                ]
            ];
        } catch (\Exception $e) {
            return [
                [
                    'eventName' => 'error',
                    'conversions' => 0,
                    'totalRevenue' => 0.0,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Get basic conversion metrics
     */
    public function getBasicConversions($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        try {
            $request = new RunReportRequest();
            $request->setDateRanges([
                new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
            ]);
            $request->setMetrics([
                new Metric(['name' => 'conversions'])
            ]);

            $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

            $row = $response->getRows()[0] ?? null;
            return [
                'conversions' => $row ? $this->safeGetMetric($row->getMetricValues(), 0, 'int', 0) : 0,
                'totalRevenue' => 0.0
            ];
        } catch (\Exception $e) {
            return [
                'conversions' => 0,
                'totalRevenue' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get abandoned cart analytics - SAFE VERSION
     */
     public function getAbandonedCartAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $client = new \Google\Client();
        $client->setAuthConfig(base_path('app/Script/analytics-key.json'));
        $client->addScope('https://www.googleapis.com/auth/analytics.readonly');

        $analyticsData = new \Google\Service\AnalyticsData($client);

        $request = new \Google\Service\AnalyticsData\RunReportRequest([
            'dateRanges' => [
                new \Google\Service\AnalyticsData\DateRange([
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ]),
            ],
            'dimensions' => [
                new \Google\Service\AnalyticsData\Dimension(['name' => 'eventName']),
            ],
            'metrics' => [
                new \Google\Service\AnalyticsData\Metric(['name' => 'eventCount']),
            ],
        ]);

        $response = $analyticsData->properties->runReport(
            'properties/' . $propertyId,
            $request
        );

        $addToCarts = 0;
        $checkouts = 0;
        $purchases = 0;

        foreach ($response->getRows() as $row) {
            $eventName = $row->getDimensionValues()[0]->getValue();
            $count = (int) $row->getMetricValues()[0]->getValue();

            if ($eventName === 'add_to_cart') {
                $addToCarts = $count;
            } elseif ($eventName === 'begin_checkout') {
                $checkouts = $count;
            } elseif ($eventName === 'purchase') {
                $purchases = $count;
            }
        }

        // Calculate abandonment & conversion
        $abandonedCarts = max(0, $addToCarts - $checkouts);
        $abandonedCheckouts = max(0, $checkouts - $purchases);

        $cartAbandonmentRate = $addToCarts > 0 ? round(($abandonedCarts / $addToCarts) * 100, 2) : 0;
        $checkoutAbandonmentRate = $checkouts > 0 ? round(($abandonedCheckouts / $checkouts) * 100, 2) : 0;
        $conversionRate = $addToCarts > 0 ? round(($purchases / $addToCarts) * 100, 2) : 0;

        return [
            'addToCarts' => $addToCarts,
            'checkouts' => $checkouts,
            'purchases' => $purchases,
            'abandonedCarts' => $abandonedCarts,
            'abandonedCheckouts' => $abandonedCheckouts,
            'cartAbandonmentRate' => $cartAbandonmentRate,
            'checkoutAbandonmentRate' => $checkoutAbandonmentRate,
            'conversionRate' => $conversionRate,
            'message' => ($addToCarts + $checkouts + $purchases) === 0
                ? 'E-commerce tracking not available or not configured'
                : 'success'
        ];
    }




    /**
     * Get traffic sources - SAFE VERSION
     */
    public function getTrafficSources($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        try {
            $request = new RunReportRequest();
            $request->setDateRanges([
                new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
            ]);
            $request->setMetrics([
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'totalUsers'])
            ]);
            $request->setDimensions([
                new Dimension(['name' => 'sessionDefaultChannelGroup'])
            ]);

            $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

            $data = [];
            foreach ($response->getRows() as $row) {
                $dimensions = $row->getDimensionValues();
                $metrics = $row->getMetricValues();
                
                $data[] = [
                    'channelGroup' => $this->safeGetDimension($dimensions, 0, 'Unknown'),
                    'source' => 'Unknown',
                    'medium' => 'Unknown',
                    'sessions' => $this->safeGetMetric($metrics, 0, 'int', 0),
                    'users' => $this->safeGetMetric($metrics, 1, 'int', 0),
                    'conversions' => 0,
                    'revenue' => 0.0
                ];
            }
            return $data;
        } catch (\Exception $e) {
            return [
                [
                    'channelGroup' => 'Error',
                    'source' => 'Error',
                    'medium' => 'Error',
                    'sessions' => 0,
                    'users' => 0,
                    'conversions' => 0,
                    'revenue' => 0.0,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Placeholder methods for other analytics
     */
  /**
 * Get page analytics data from GA4
 */
public function getPageAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
{
    try {
        $request = new RunReportRequest();
        $request->setDateRanges([
            new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
        ]);
        
        $request->setMetrics([
            new Metric(['name' => 'screenPageViews']),
            new Metric(['name' => 'sessions']),
            new Metric(['name' => 'averageSessionDuration']),
            new Metric(['name' => 'bounceRate']),
            new Metric(['name' => 'engagementRate'])
        ]);
        
        $request->setDimensions([
            new Dimension(['name' => 'pageTitle']),
            new Dimension(['name' => 'pagePath'])
        ]);

        // Order by page views descending
        $request->setOrderBys([
            new OrderBy([
                'metric' => new MetricOrderBy(['metric_name' => 'screenPageViews']),
                'desc' => true
            ])
        ]);

        // Limit to top 50 pages
        $request->setLimit(50);

        $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $pageTitle = $this->safeGetDimension($dimensions, 0, 'Unknown Page');
            $pagePath = $this->safeGetDimension($dimensions, 1, '/');
            
            $data[] = [
                'pageTitle' => $pageTitle,
                'pagePath' => $pagePath,
                'pageViews' => $this->safeGetMetric($metrics, 0, 'int', 0),
                'sessions' => $this->safeGetMetric($metrics, 1, 'int', 0),
                'avgTimeOnPage' => round($this->safeGetMetric($metrics, 2, 'float', 0), 2),
                'bounceRate' => round($this->safeGetMetric($metrics, 3, 'float', 0) * 100, 2),
                'engagementRate' => round($this->safeGetMetric($metrics, 4, 'float', 0) * 100, 2),
                'uniquePageViews' => $this->safeGetMetric($metrics, 0, 'int', 0), // GA4 doesn't have unique pageviews, using pageviews
                'exits' => 0 // GA4 doesn't directly provide exit data in this context
            ];
        }
        
        return $data;
        
    } catch (\Exception $e) {
        return [
            [
                'pageTitle' => 'Error fetching data',
                'pagePath' => '/',
                'pageViews' => 0,
                'sessions' => 0,
                'avgTimeOnPage' => 0,
                'bounceRate' => 0,
                'engagementRate' => 0,
                'uniquePageViews' => 0,
                'exits' => 0,
                'error' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Get top landing pages
 */
public function getLandingPageAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
{
    try {
        $request = new RunReportRequest();
        $request->setDateRanges([
            new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
        ]);
        
        $request->setMetrics([
            new Metric(['name' => 'sessions']),
            new Metric(['name' => 'totalUsers']),
            new Metric(['name' => 'bounceRate']),
            new Metric(['name' => 'averageSessionDuration']),
            new Metric(['name' => 'conversions'])
        ]);
        
        $request->setDimensions([
            new Dimension(['name' => 'landingPage']),
            new Dimension(['name' => 'landingPagePlusQueryString'])
        ]);

        $request->setOrderBys([
            new OrderBy([
                'metric' => new MetricOrderBy(['metric_name' => 'sessions']),
                'desc' => true
            ])
        ]);

        $request->setLimit(25);

        $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'landingPage' => $this->safeGetDimension($dimensions, 0, '/'),
                'landingPageWithQuery' => $this->safeGetDimension($dimensions, 1, '/'),
                'sessions' => $this->safeGetMetric($metrics, 0, 'int', 0),
                'users' => $this->safeGetMetric($metrics, 1, 'int', 0),
                'bounceRate' => round($this->safeGetMetric($metrics, 2, 'float', 0) * 100, 2),
                'avgSessionDuration' => round($this->safeGetMetric($metrics, 3, 'float', 0), 2),
                'conversions' => $this->safeGetMetric($metrics, 4, 'int', 0)
            ];
        }
        
        return $data;
        
    } catch (\Exception $e) {
        return [
            [
                'landingPage' => '/',
                'landingPageWithQuery' => '/',
                'sessions' => 0,
                'users' => 0,
                'bounceRate' => 0,
                'avgSessionDuration' => 0,
                'conversions' => 0,
                'error' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Get page performance metrics
 */
public function getPagePerformance($propertyId, $startDate = '30daysAgo', $endDate = 'today')
{
    try {
        $request = new RunReportRequest();
        $request->setDateRanges([
            new DateRange(['start_date' => $startDate, 'end_date' => $endDate])
        ]);
        
        $request->setMetrics([
            new Metric(['name' => 'screenPageViews']),
            new Metric(['name' => 'userEngagementDuration']),
            new Metric(['name' => 'engagedSessions']),
            new Metric(['name' => 'totalUsers'])
        ]);
        
        $request->setDimensions([
            new Dimension(['name' => 'pageTitle']),
            new Dimension(['name' => 'pagePath'])
        ]);

        $request->setOrderBys([
            new OrderBy([
                'metric' => new MetricOrderBy(['metric_name' => 'userEngagementDuration']),
                'desc' => true
            ])
        ]);

        $request->setLimit(30);

        $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $pageViews = $this->safeGetMetric($metrics, 0, 'int', 0);
            $engagementDuration = $this->safeGetMetric($metrics, 1, 'float', 0);
            $engagedSessions = $this->safeGetMetric($metrics, 2, 'int', 0);
            $users = $this->safeGetMetric($metrics, 3, 'int', 0);
            
            $data[] = [
                'pageTitle' => $this->safeGetDimension($dimensions, 0, 'Unknown'),
                'pagePath' => $this->safeGetDimension($dimensions, 1, '/'),
                'pageViews' => $pageViews,
                'totalEngagementTime' => round($engagementDuration, 2),
                'avgEngagementTime' => $pageViews > 0 ? round($engagementDuration / $pageViews, 2) : 0,
                'engagedSessions' => $engagedSessions,
                'users' => $users,
                'engagementRate' => $users > 0 ? round(($engagedSessions / $users) * 100, 2) : 0
            ];
        }
        
        return $data;
        
    } catch (\Exception $e) {
        return [
            [
                'pageTitle' => 'Error',
                'pagePath' => '/',
                'pageViews' => 0,
                'totalEngagementTime' => 0,
                'avgEngagementTime' => 0,
                'engagedSessions' => 0,
                'users' => 0,
                'engagementRate' => 0,
                'error' => $e->getMessage()
            ]
        ];
    }
}

    public function getEventAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        return [
            [
                'eventName' => 'Data not available',
                'eventCategory' => 'Unknown',
                'eventCount' => 0,
                'eventCountPerUser' => 0,
                'eventValue' => 0
            ]
        ];
    }
    private function getAccessToken()
    {
        $credentialsPath =base_path('app/Script/analytics-key.json');
        
        if (!file_exists($credentialsPath)) {
            throw new \Exception('Credentials file not found');
        }
        
        $credentials = json_decode(file_get_contents($credentialsPath), true);
        
        // Create JWT token
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        
        $now = time();
        $payload = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = '';
        openssl_sign(
            $base64Header . "." . $base64Payload,
            $signature,
            $credentials['private_key'],
            'SHA256'
        );
        
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $jwt = $base64Header . "." . $base64Payload . "." . $base64Signature;
        
        // Exchange JWT for access token
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            throw new \Exception('Failed to get access token: ' . $response);
        }
        
        $tokenData = json_decode($response, true);
        return $tokenData['access_token'];
    }

    public function getRealTimeAnalytics($viewId)
    {
        try {
            // Get access token
            $accessToken = $this->getAccessToken();
            
            // Make API request
            $url = "https://www.googleapis.com/analytics/v3/data/realtime";
            $params = [
                'ids' => 'ga:' . $viewId,
                'metrics' => 'rt:activeUsers',
                'dimensions' => 'rt:country,rt:deviceCategory',
                'access_token' => $accessToken
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url . '?' . http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode !== 200) {
                throw new \Exception('API request failed with status: ' . $httpCode . ' Response: ' . $response);
            }

            $data = json_decode($response, true);
            
            // Process the response
            $processedData = [];
            $totalActiveUsers = 0;

            if (isset($data['rows']) && !empty($data['rows'])) {
                foreach ($data['rows'] as $row) {
                    $country = $row[0] ?? 'Unknown';
                    $device = $row[1] ?? 'Unknown';
                    $activeUsers = (int)($row[2] ?? 0);

                    $processedData[] = [
                        'country' => $country,
                        'device' => $device,
                        'activeUsers' => $activeUsers,
                    ];

                    $totalActiveUsers += $activeUsers;
                }
            }

            return [
                'total_active_users' => $totalActiveUsers,
                'data' => $processedData,
                'timestamp' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            throw new \Exception('Real-time Analytics Error: ' . $e->getMessage());
        }
    }
    public function getAudienceDemographics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
{
    $client = new \Google\Client();
    $client->setAuthConfig(base_path('app/Script/analytics-key.json'));

    $client->addScope('https://www.googleapis.com/auth/analytics.readonly');

    $analyticsData = new \Google\Service\AnalyticsData($client);

    $request = new \Google\Service\AnalyticsData\RunReportRequest([
        'dateRanges' => [
            new \Google\Service\AnalyticsData\DateRange([
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]),
        ],
        'dimensions' => [
            new \Google\Service\AnalyticsData\Dimension(['name' => 'userAgeBracket']),
            new \Google\Service\AnalyticsData\Dimension(['name' => 'userGender']),
        ],
        'metrics' => [
            new \Google\Service\AnalyticsData\Metric(['name' => 'totalUsers']),
            new \Google\Service\AnalyticsData\Metric(['name' => 'sessions']),
        ],
    ]);

    $response = $analyticsData->properties->runReport(
        'properties/' . $propertyId,
        $request
    );

    $data = [];
    foreach ($response->getRows() as $row) {
        $dimensions = $row->getDimensionValues();
        $metrics = $row->getMetricValues();

        $data[] = [
            'ageBracket' => $dimensions[0]->getValue(),
            'gender'     => $dimensions[1]->getValue(),
            'users'      => $metrics[0]->getValue(),
            'sessions'   => $metrics[1]->getValue(),
        ];
    }

    return $data;
}

    //   public function getBasicEcommerceFunnel($propertyId, $startDate, $endDate)
    // {
    //     // Example: simple mock/fallback funnel
    //     $baseUsers = rand(800, 1500);
    //     $addToCartUsers = (int)($baseUsers * 0.25);
    //     $checkoutUsers = (int)($addToCartUsers * 0.5);
    //     $purchaseUsers = (int)($checkoutUsers * 0.6);

    //     return [
    //         'funnel_data' => [
    //             ['step' => 'Product Views', 'users' => $baseUsers, 'conversion_rate' => 100],
    //             ['step' => 'Add to Cart', 'users' => $addToCartUsers, 'conversion_rate' => round(($addToCartUsers/$baseUsers)*100,2)],
    //             ['step' => 'Checkout Started', 'users' => $checkoutUsers, 'conversion_rate' => round(($checkoutUsers/$baseUsers)*100,2)],
    //             ['step' => 'Purchase', 'users' => $purchaseUsers, 'conversion_rate' => round(($purchaseUsers/$baseUsers)*100,2)]
    //         ],
    //         'conversion_rates' => [
    //             'view_to_cart' => round(($addToCartUsers/$baseUsers)*100,2),
    //             'cart_to_checkout' => round(($checkoutUsers/$addToCartUsers)*100,2),
    //             'checkout_to_purchase' => round(($purchaseUsers/$checkoutUsers)*100,2),
    //             'overall_conversion_rate' => round(($purchaseUsers/$baseUsers)*100,2)
    //         ],
    //         'insights' => [
    //             'total_started' => $baseUsers,
    //             'total_completed' => $purchaseUsers,
    //             'overall_conversion_rate' => round(($purchaseUsers/$baseUsers)*100,2),
    //             'total_revenue' => $purchaseUsers * rand(50,200),
    //             'average_order_value' => $purchaseUsers > 0 ? rand(50,200) : 0
    //         ]
    //     ];
    // }
    
    public function getEcommerceFunnel($propertyId, $startDate, $endDate)
    {
        $request = new RunReportRequest([
            'dateRanges' => [
                new DateRange([
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ]),
            ],
            'dimensions' => [
                new Dimension(['name' => 'eventName']),
            ],
            'metrics' => [
                new Metric(['name' => 'eventCount']),
            ],
        ]);

        $response = $this->analyticsData->properties->runReport(
            'properties/' . $propertyId,
            $request
        );

        $funnelSteps = [
            'session_start'   => 0,
            'view_item'       => 0,
            'add_to_cart'     => 0,
            'begin_checkout'  => 0,
            'purchase'        => 0,
        ];

        foreach ($response->getRows() as $row) {
            $event = $row->getDimensionValues()[0]->getValue();
            $count = (int)$row->getMetricValues()[0]->getValue();

            if (array_key_exists($event, $funnelSteps)) {
                $funnelSteps[$event] = $count;
            }
        }

        // Build funnel data
        $funnelData = [
            ['step' => 'Sessions', 'count' => $funnelSteps['session_start']],
            ['step' => 'Product Views', 'count' => $funnelSteps['view_item']],
            ['step' => 'Add to Cart', 'count' => $funnelSteps['add_to_cart']],
            ['step' => 'Checkout', 'count' => $funnelSteps['begin_checkout']],
            ['step' => 'Purchases', 'count' => $funnelSteps['purchase']],
        ];

        // Calculate conversion rates
        $conversionRates = [];
        $previous = null;
        foreach ($funnelData as $step) {
            if ($previous && $previous['count'] > 0) {
                $conversionRates[$step['step']] = round(
                    ($step['count'] / $previous['count']) * 100,
                    2
                ) . '%';
            } else {
                $conversionRates[$step['step']] = 'N/A';
            }
            $previous = $step;
        }

        return [
            'funnel_data' => $funnelData,
            'conversion_rates' => $conversionRates,
            'insights' => [
                'drop_off' => 'Biggest drop is usually between views → add to cart',
            ],
        ];
    }

    public function getAnalyticsData()
    {
        return $this->analyticsData;
    }

}