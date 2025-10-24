<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;

class GeoLocationService
{
    protected $client;
    protected $apiUrl = 'http://ip-api.com/json/';

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 5,  // 5 second timeout
            'connect_timeout' => 3,  // 3 second connection timeout
            'http_errors' => false,  // Don't throw exceptions on 4xx/5xx responses
        ]);
    }

    /**
     * Get geolocation data for an IP address
     * 
     * @param string $ip
     * @return array
     */
    public function getLocation($ip)
    {
        try {
            // Add query parameters to get all useful fields
            $response = $this->client->get($this->apiUrl . $ip, [
                'query' => [
                    'fields' => 'status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            
            // Check if the request was successful
            if ($statusCode !== 200) {
                Log::warning('IP geolocation API returned non-200 status', [
                    'ip' => $ip,
                    'status_code' => $statusCode,
                    'response' => $response->getBody()->getContents()
                ]);
                
                return $this->failureResponse($ip, 'Service temporarily unavailable');
            }

            $data = json_decode($response->getBody(), true);

            // Validate JSON parsing
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse geolocation response', [
                    'ip' => $ip,
                    'error' => json_last_error_msg()
                ]);
                
                return $this->failureResponse($ip, 'Invalid response from service');
            }

            // Check if the API returned a successful status
            if (isset($data['status']) && $data['status'] === 'success') {
                return [
                    'status' => 'success',
                    'country' => $data['country'] ?? '',
                    'countryCode' => $data['countryCode'] ?? '',
                    'region' => $data['region'] ?? '',
                    'regionName' => $data['regionName'] ?? '',
                    'city' => $data['city'] ?? '',
                    'zip' => $data['zip'] ?? '',
                    'lat' => $data['lat'] ?? 0,
                    'lon' => $data['lon'] ?? 0,
                    'timezone' => $data['timezone'] ?? '',
                    'isp' => $data['isp'] ?? '',
                    'org' => $data['org'] ?? '',
                    'as' => $data['as'] ?? '',
                    'query' => $data['query'] ?? $ip
                ];
            }

            // API returned fail status (e.g., private IP, invalid IP, etc.)
            $message = $data['message'] ?? 'Location lookup failed';
            
            Log::info('IP geolocation lookup failed', [
                'ip' => $ip,
                'reason' => $message
            ]);

            return $this->failureResponse($ip, $message);

        } catch (ConnectException $e) {
            // Connection timeout or network issue
            Log::error('IP geolocation connection failed', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            
            return $this->failureResponse($ip, 'Unable to connect to geolocation service');

        } catch (RequestException $e) {
            // HTTP request failed
            Log::error('IP geolocation request exception', [
                'ip' => $ip,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            return $this->failureResponse($ip, 'Geolocation service error');

        } catch (\Exception $e) {
            // Any other unexpected error
            Log::error('Unexpected error in IP geolocation', [
                'ip' => $ip,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->failureResponse($ip, 'Service temporarily unavailable');
        }
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
