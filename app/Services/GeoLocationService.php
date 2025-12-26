<?php

// namespace App\Services;

// use GuzzleHttp\Client;
// use GuzzleHttp\Exception\RequestException;

// class GeoLocationService
// {
//     protected $client;
//     protected $apiUrl = 'http://ip-api.com/json/'; // Using ip-api.com

//     public function __construct()
//     {
//         $this->client = new Client();
//     }

//     public function getLocation($ip)
//     {
//         try {
//             $response = $this->client->get($this->apiUrl . $ip);
//             $data = json_decode($response->getBody(), true);

//             return $data;
//         } catch (RequestException $e) {
//             // Handle error
//             return null;
//         }
//     }
// }


namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeoLocationService
{
    protected Client $client;

    // ip-api also supports HTTPS, use that if possible
    protected string $apiUrl = 'http://ip-api.com/json/';

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: new Client([
            'timeout'         => 1.5,  // total time limit
            'connect_timeout' => 0.5,  // TCP connect time
        ]);
    }

    /**
     * Get location for an IP.
     *
     * @param  string|null  $ip
     * @return array|null
     */
    public function getLocation(?string $ip): ?array
    {
        if (empty($ip)) {
            return null;
        }

        // If you're behind CloudFront, you might see private IPs etc.
        // You can add more filtering here if needed.
        $cacheKey = 'geo_ip_' . $ip;

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($ip) {
            try {
                $response = $this->client->get($this->apiUrl . $ip, [
                    'query' => [
                        // you can request only needed fields to reduce payload
                        // 'fields' => 'status,country,regionName,city,lat,lon,query',
                    ],
                ]);

                $data = json_decode((string) $response->getBody(), true);

                if (!is_array($data) || ($data['status'] ?? null) !== 'success') {
                    Log::warning('GeoLocation lookup failed or returned bad status', [
                        'ip'   => $ip,
                        'data' => $data ?? null,
                    ]);

                    // Don’t cache bad responses forever
                    return null;
                }

                return $data;
            } catch (GuzzleException $e) {
                Log::warning('GeoLocation HTTP error', [
                    'ip'      => $ip,
                    'message' => $e->getMessage(),
                ]);

                // returning null here means: fail soft, don’t block the user experience
                return null;
            } catch (\Throwable $e) {
                Log::error('GeoLocation unexpected error', [
                    'ip'      => $ip,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Return a consistent failure response
     * 
     * @param string $ip
     * @param string $message
     * @return array
     */
    private function failureResponse($ip, $message = 'Unknown error')
    {
        return [
            'status' => 'fail',
            'message' => $message,
            'query' => $ip,
            'country' => '',
            'countryCode' => '',
            'region' => '',
            'regionName' => '',
            'city' => '',
            'zip' => '',
            'lat' => 0,
            'lon' => 0,
            'timezone' => '',
            'isp' => '',
            'org' => '',
            'as' => ''
        ];
    }

    /**
     * Check if IP is valid for geolocation lookup
     * 
     * @param string $ip
     * @return bool
     */
    public function isValidIp($ip)
    {
        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Check if it's a public IP (not private or reserved)
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        return true;
    }
}
