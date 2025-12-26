<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SeoMonitoring;
use Google\Client as Google_Client;
use Google\Service\SearchConsole as Google_Service_SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest as Google_Service_SearchConsole_SearchAnalyticsQueryRequest;
use App\Events\SeoMonitors;
class DailyLLmsSEO extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:dailyUpdateLlmsSeo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {         
        ini_set('max_execution_time', 712);
        set_time_limit(712);
        $this->info('Search Console Sync Started...');
        
            // 1️⃣ Load credentials
            $scriptPath = base_path('app/Script/the-horecastore-usa-478610-d9b99c2833ec.json');
           if (!file_exists($scriptPath)) {
                $this->info('Credentials file not found -the-horecastore-usa-478610-d9b99c2833ec');
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
            
       
           
            $startDate = now()->subDays(2)->format('Y-m-d');
            $endDate = now()->subDays(2)->format('Y-m-d');
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


        $this->info('Search Console Sync Completed!');
         return Command::SUCCESS;

    }
}
