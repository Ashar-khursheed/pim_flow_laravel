<?php

namespace App\Helpers;

use Google\Client;
use Google\Service\AnalyticsData;

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
     * Get basic overview metrics
     */
    public function getOverview($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'newUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'bounceRate'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'conversions'],
                ['name' => 'totalRevenue']
            ]
        ]);

        $row = $response->getRows()[0] ?? null;
        if (!$row) return null;

        $metrics = $row->getMetricValues();
        return [
            'sessions' => (int)$metrics[0]->getValue(),
            'totalUsers' => (int)$metrics[1]->getValue(),
            'newUsers' => (int)$metrics[2]->getValue(),
            'returningUsers' => (int)$metrics[1]->getValue() - (int)$metrics[2]->getValue(),
            'pageViews' => (int)$metrics[3]->getValue(),
            'bounceRate' => round((float)$metrics[4]->getValue() * 100, 2),
            'avgSessionDuration' => (float)$metrics[5]->getValue(),
            'conversions' => (int)$metrics[6]->getValue(),
            'totalRevenue' => (float)$metrics[7]->getValue()
        ];
    }

    /**
     * Get sessions by date (day-wise data)
     */
    public function getSessionsByDate($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'conversions'],
                ['name' => 'totalRevenue']
            ],
            'dimensions' => [['name' => 'date']],
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]]
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $date = $row->getDimensionValues()[0]->getValue();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'date' => $date,
                'sessions' => (int)$metrics[0]->getValue(),
                'users' => (int)$metrics[1]->getValue(),
                'conversions' => (int)$metrics[2]->getValue(),
                'revenue' => (float)$metrics[3]->getValue()
            ];
        }
        return $data;
    }

    /**
     * Get detailed device analytics
     */
    public function getDeviceAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'bounceRate'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'conversions'],
                ['name' => 'totalRevenue']
            ],
            'dimensions' => [
                ['name' => 'deviceCategory'],
                ['name' => 'operatingSystem'],
                ['name' => 'browser']
            ]
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'device' => $dimensions[0]->getValue(),
                'os' => $dimensions[1]->getValue(),
                'browser' => $dimensions[2]->getValue(),
                'sessions' => (int)$metrics[0]->getValue(),
                'users' => (int)$metrics[1]->getValue(),
                'bounceRate' => round((float)$metrics[2]->getValue() * 100, 2),
                'avgSessionDuration' => (float)$metrics[3]->getValue(),
                'conversions' => (int)$metrics[4]->getValue(),
                'revenue' => (float)$metrics[5]->getValue()
            ];
        }
        return $data;
    }

    /**
     * Get detailed geographic analytics
     */
    public function getGeographicAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'conversions'],
                ['name' => 'totalRevenue'],
                ['name' => 'averageSessionDuration']
            ],
            'dimensions' => [
                ['name' => 'country'],
                ['name' => 'region'],
                ['name' => 'city']
            ],
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]]
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'country' => $dimensions[0]->getValue(),
                'region' => $dimensions[1]->getValue(),
                'city' => $dimensions[2]->getValue(),
                'sessions' => (int)$metrics[0]->getValue(),
                'users' => (int)$metrics[1]->getValue(),
                'conversions' => (int)$metrics[2]->getValue(),
                'revenue' => (float)$metrics[3]->getValue(),
                'avgSessionDuration' => (float)$metrics[4]->getValue()
            ];
        }
        return $data;
    }

    /**
     * Get conversion analytics with funnel data
     */
    public function getConversionAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'conversions'],
                ['name' => 'totalRevenue'],
                ['name' => 'purchaseRevenue'],
                ['name' => 'addToCarts'],
                ['name' => 'checkouts']
            ],
            'dimensions' => [['name' => 'conversionEventName']]
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $eventName = $row->getDimensionValues()[0]->getValue();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'eventName' => $eventName,
                'conversions' => (int)$metrics[0]->getValue(),
                'totalRevenue' => (float)$metrics[1]->getValue(),
                'purchaseRevenue' => (float)$metrics[2]->getValue(),
                'addToCarts' => (int)$metrics[3]->getValue(),
                'checkouts' => (int)$metrics[4]->getValue()
            ];
        }
        return $data;
    }

    /**
     * Get abandoned cart analytics
     */
    public function getAbandonedCartAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        // Get add_to_cart events
        $addToCartResponse = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [['name' => 'eventCount']],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName' => 'eventName',
                    'stringFilter' => [
                        'matchType' => 'EXACT',
                        'value' => 'add_to_cart'
                    ]
                ]
            ]
        ]);

        // Get purchase events
        $purchaseResponse = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [['name' => 'eventCount']],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName' => 'eventName',
                    'stringFilter' => [
                        'matchType' => 'EXACT',
                        'value' => 'purchase'
                    ]
                ]
            ]
        ]);

        // Get begin_checkout events
        $checkoutResponse = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [['name' => 'eventCount']],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName' => 'eventName',
                    'stringFilter' => [
                        'matchType' => 'EXACT',
                        'value' => 'begin_checkout'
                    ]
                ]
            ]
        ]);

        $addToCarts = $addToCartResponse->getRows()[0]->getMetricValues()[0]->getValue() ?? 0;
        $purchases = $purchaseResponse->getRows()[0]->getMetricValues()[0]->getValue() ?? 0;
        $checkouts = $checkoutResponse->getRows()[0]->getMetricValues()[0]->getValue() ?? 0;

        $cartAbandonmentRate = $addToCarts > 0 ? (($addToCarts - $checkouts) / $addToCarts) * 100 : 0;
        $checkoutAbandonmentRate = $checkouts > 0 ? (($checkouts - $purchases) / $checkouts) * 100 : 0;

        return [
            'addToCarts' => (int)$addToCarts,
            'checkouts' => (int)$checkouts,
            'purchases' => (int)$purchases,
            'abandonedCarts' => (int)($addToCarts - $checkouts),
            'abandonedCheckouts' => (int)($checkouts - $purchases),
            'cartAbandonmentRate' => round($cartAbandonmentRate, 2),
            'checkoutAbandonmentRate' => round($checkoutAbandonmentRate, 2),
            'conversionRate' => $addToCarts > 0 ? round(($purchases / $addToCarts) * 100, 2) : 0
        ];
    }

    /**
     * Get traffic source analytics
     */
    public function getTrafficSources($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'conversions'],
                ['name' => 'totalRevenue']
            ],
            'dimensions' => [
                ['name' => 'sessionDefaultChannelGroup'],
                ['name' => 'sessionSource'],
                ['name' => 'sessionMedium']
            ],
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]]
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'channelGroup' => $dimensions[0]->getValue(),
                'source' => $dimensions[1]->getValue(),
                'medium' => $dimensions[2]->getValue(),
                'sessions' => (int)$metrics[0]->getValue(),
                'users' => (int)$metrics[1]->getValue(),
                'conversions' => (int)$metrics[2]->getValue(),
                'revenue' => (float)$metrics[3]->getValue()
            ];
        }
        return $data;
    }

    /**
     * Get page analytics
     */
    public function getPageAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'screenPageViews'],
                ['name' => 'uniquePageViews'],
                ['name' => 'averageTimeOnPage'],
                ['name' => 'exits']
            ],
            'dimensions' => [
                ['name' => 'pageTitle'],
                ['name' => 'pagePath']
            ],
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit' => 50
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'pageTitle' => $dimensions[0]->getValue(),
                'pagePath' => $dimensions[1]->getValue(),
                'pageViews' => (int)$metrics[0]->getValue(),
                'uniquePageViews' => (int)$metrics[1]->getValue(),
                'avgTimeOnPage' => (float)$metrics[2]->getValue(),
                'exits' => (int)$metrics[3]->getValue()
            ];
        }
        return $data;
    }

    /**
     * Get event analytics
     */
    public function getEventAnalytics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'eventCount'],
                ['name' => 'eventCountPerUser'],
                ['name' => 'eventValue']
            ],
            'dimensions' => [
                ['name' => 'eventName'],
                ['name' => 'customEvent:event_category']
            ],
            'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]]
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'eventName' => $dimensions[0]->getValue(),
                'eventCategory' => $dimensions[1]->getValue(),
                'eventCount' => (int)$metrics[0]->getValue(),
                'eventCountPerUser' => (float)$metrics[1]->getValue(),
                'eventValue' => (float)$metrics[2]->getValue()
            ];
        }
        return $data;
    }

    /**
     * Get real-time analytics
     */
    public function getRealTimeAnalytics($propertyId)
    {
        $response = $this->analyticsData->properties->runRealtimeReport($propertyId, [
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'screenPageViews']
            ],
            'dimensions' => [
                ['name' => 'country'],
                ['name' => 'deviceCategory']
            ]
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'country' => $dimensions[0]->getValue(),
                'device' => $dimensions[1]->getValue(),
                'activeUsers' => (int)$metrics[0]->getValue(),
                'pageViews' => (int)$metrics[1]->getValue()
            ];
        }
        return $data;
    }

    /**
     * Get audience demographics
     */
    public function getAudienceDemographics($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'totalUsers'],
                ['name' => 'sessions']
            ],
            'dimensions' => [
                ['name' => 'userAgeBracket'],
                ['name' => 'userGender']
            ]
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();
            
            $data[] = [
                'ageBracket' => $dimensions[0]->getValue(),
                'gender' => $dimensions[1]->getValue(),
                'users' => (int)$metrics[0]->getValue(),
                'sessions' => (int)$metrics[1]->getValue()
            ];
        }
        return $data;
    }
}