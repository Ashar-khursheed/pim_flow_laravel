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
     * Total sessions by date
     */
    public function getSessions($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [['name' => 'sessions']],
            'dimensions' => [['name' => 'date']],
        ]);

        return $response->getRows();
    }

    /**
     * Users: total, new, returning
     */
    public function getUsers($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [['name' => 'totalUsers'], ['name' => 'newUsers']],
        ]);

        $rows = $response->getRows();
        $totalUsers = $rows[0]->getMetricValues()[0]->getValue();
        $newUsers = $rows[0]->getMetricValues()[1]->getValue();
        $returningUsers = $totalUsers - $newUsers;

        return [
            'totalUsers' => (int)$totalUsers,
            'newUsers' => (int)$newUsers,
            'returningUsers' => (int)$returningUsers,
        ];
    }

    /**
     * Bounce rate & average session duration
     */
    public function getEngagement($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'bounceRate'],
                ['name' => 'averageSessionDuration']
            ]
        ]);

        $rows = $response->getRows();
        return [
            'bounceRate' => (float)$rows[0]->getMetricValues()[0]->getValue(),
            'avgSessionDuration' => (float)$rows[0]->getMetricValues()[1]->getValue(), // in seconds
        ];
    }

    /**
     * Revenue / conversions / purchases
     */
    public function getConversions($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'conversions'],
                ['name' => 'purchaseRevenue']
            ],
        ]);

        $rows = $response->getRows();
        return [
            'conversions' => (int)$rows[0]->getMetricValues()[0]->getValue(),
            'totalRevenue' => (float)$rows[0]->getMetricValues()[1]->getValue(),
        ];
    }

    /**
     * Sessions by device category
     */
    public function getSessionsByDevice($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [['name' => 'sessions']],
            'dimensions' => [['name' => 'deviceCategory']],
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $device = $row->getDimensionValues()[0]->getValue();
            $sessions = $row->getMetricValues()[0]->getValue();
            $data[$device] = (int)$sessions;
        }

        return $data;
    }

    /**
     * Sessions by geography: country / city
     */
    public function getGeo($propertyId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $response = $this->analyticsData->properties->runReport($propertyId, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [['name' => 'sessions']],
            'dimensions' => [['name' => 'country'], ['name' => 'city']],
        ]);

        $data = [];
        foreach ($response->getRows() as $row) {
            $country = $row->getDimensionValues()[0]->getValue();
            $city = $row->getDimensionValues()[1]->getValue();
            $sessions = $row->getMetricValues()[0]->getValue();
            $data[] = [
                'country' => $country,
                'city' => $city,
                'sessions' => (int)$sessions,
            ];
        }

        return $data;
    }
}

// namespace App\Helpers;

// class GoogleAnalytics
// {
//     private $keyFile;
//     private $scopes = ['https://www.googleapis.com/auth/analytics.readonly'];
//     private $token;

//     public function __construct()
//     {
//         $this->keyFile = base_path('app/Script/analytics-key.json');
//         $this->authenticate();
//     }

//     private function authenticate()
//     {
//         $json = json_decode(file_get_contents($this->keyFile), true);

//         $jwtHeader = $this->base64UrlEncode(json_encode([
//             'alg' => 'RS256',
//             'typ' => 'JWT'
//         ]));

//         $now = time();
//         $jwtClaim = $this->base64UrlEncode(json_encode([
//             "iss" => $json['client_email'],
//             "scope" => implode(' ', $this->scopes),
//             "aud" => "https://oauth2.googleapis.com/token",
//             "exp" => $now + 3600,
//             "iat" => $now,
//         ]));

//         $signatureInput = $jwtHeader . '.' . $jwtClaim;

//         openssl_sign($signatureInput, $signature, openssl_pkey_get_private($json['private_key']), 'sha256WithRSAEncryption');
//         $jwtAssertion = $signatureInput . '.' . $this->base64UrlEncode($signature);

//         // Exchange JWT for access token
//         $ch = curl_init('https://oauth2.googleapis.com/token');
//         curl_setopt($ch, CURLOPT_POST, true);
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//         curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
//             'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
//             'assertion'  => $jwtAssertion,
//         ]));
//         $response = json_decode(curl_exec($ch), true);
//         curl_close($ch);

//         $this->token = $response['access_token'] ?? null;
//     }

//     /**
//      * Get GA4 Report dynamically
//      */
//     public function getReport(
//         string $propertyId,
//         array $dimensions = ['city', 'country'],
//         array $metrics = ['activeUsers'],
//         string $startDate = '2024-08-01',
//         string $endDate = 'today',
//         array $filters = []
//     ) {
//         $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport";

//         // Build dimensions
//         $dims = array_map(fn($d) => ['name' => $d], $dimensions);
//         // Build metrics
//         $mets = array_map(fn($m) => ['name' => $m], $metrics);

//         $postData = [
//             "dimensions" => $dims,
//             "metrics"    => $mets,
//             "dateRanges" => [
//                 ["startDate" => $startDate, "endDate" => $endDate]
//             ]
//         ];

//         // Add filters if provided
//         if (!empty($filters)) {
//             $postData['dimensionFilter'] = $filters;
//         }

//         $ch = curl_init($url);
//         curl_setopt($ch, CURLOPT_HTTPHEADER, [
//             "Authorization: Bearer " . $this->token,
//             "Content-Type: application/json"
//         ]);
//         curl_setopt($ch, CURLOPT_POST, true);
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
//         $response = json_decode(curl_exec($ch), true);
//         curl_close($ch);

//         return $response;
//     }

//     private function base64UrlEncode($data)
//     {
//         return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
//     }
// }
