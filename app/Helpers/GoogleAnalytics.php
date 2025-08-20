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
    public function getOverview($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
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
                new Metric(['name' => 'averageSessionDuration'])
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
                'conversions' => 0, // Will get separately
                'totalRevenue' => 0.0 // Will get separately
            ];
        } catch (\Exception $e) {
            return $this->getDefaultOverview($e->getMessage());
        }
    }

    /**
     * Get default overview data
     */
    private function getDefaultOverview($error = null)
    {
        $data = [
            'sessions' => 0,
            'totalUsers' => 0,
            'newUsers' => 0,
            'returningUsers' => 0,
            'pageViews' => 0,
            'bounceRate' => 0,
            'avgSessionDuration' => 0,
            'conversions' => 0,
            'totalRevenue' => 0.0
        ];
        
        if ($error) {
            $data['error'] = $error;
        }
        
        return $data;
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
                new Metric(['name' => 'totalUsers'])
            ]);
            $request->setDimensions([new Dimension(['name' => 'date'])]);
            $request->setOrderBys([
                new OrderBy([
                    'dimension' => new DimensionOrderBy(['dimension_name' => 'date'])
                ])
            ]);

            $response = $this->analyticsData->properties->runReport("properties/{$propertyId}", $request);

            $data = [];
            foreach ($response->getRows() as $row) {
                $dimensions = $row->getDimensionValues();
                $metrics = $row->getMetricValues();
                
                $data[] = [
                    'date' => $this->safeGetDimension($dimensions, 0, 'unknown'),
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
                    'date' => date('Ymd'),
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
        return [
            'addToCarts' => 0,
            'checkouts' => 0,
            'purchases' => 0,
            'abandonedCarts' => 0,
            'abandonedCheckouts' => 0,
            'cartAbandonmentRate' => 0,
            'checkoutAbandonmentRate' => 0,
            'conversionRate' => 0,
            'message' => 'E-commerce tracking not available or not configured'
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
    public function getPageAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        return [
            [
                'pageTitle' => 'Data not available',
                'pagePath' => '/',
                'pageViews' => 0,
                'uniquePageViews' => 0,
                'avgTimeOnPage' => 0,
                'exits' => 0
            ]
        ];
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

    public function getRealTimeAnalytics($propertyId)
    {
        return [
            [
                'country' => 'Unknown',
                'device' => 'Unknown',
                'activeUsers' => 0,
                'pageViews' => 0
            ]
        ];
    }

    public function getAudienceDemographics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        return [
            [
                'ageBracket' => 'Unknown',
                'gender' => 'Unknown',
                'users' => 0,
                'sessions' => 0
            ]
        ];
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