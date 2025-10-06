<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\City;
use App\Models\State;
use App\Models\Zipcode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/frontend/location",
     *     summary="Get location by IP address",
     *     description="Fetches geolocation data based on the client's IP address or a provided IP parameter.",
     *     tags={"Locations"},
     *     @OA\Parameter(
     *         name="ip",
     *         in="query",
     *         description="Optional IP address to lookup. If not provided, uses client's IP.",
     *         required=false,
     *         example="8.8.8.8",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200, 
     *         description="Location data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="country", type="string", example="India"),
     *             @OA\Property(property="countryCode", type="string", example="IN"),
     *             @OA\Property(property="region", type="string", example="PB"),
     *             @OA\Property(property="regionName", type="string", example="Punjab"),
     *             @OA\Property(property="city", type="string", example="Kapurthala Town"),
     *             @OA\Property(property="zip", type="string", example="144601"),
     *             @OA\Property(property="lat", type="number", example=31.3882),
     *             @OA\Property(property="lon", type="number", example=75.3826),
     *             @OA\Property(property="timezone", type="string", example="Asia/Kolkata"),
     *             @OA\Property(property="isp", type="string", example="BSNL Internet"),
     *             @OA\Property(property="query", type="string", example="117.203.2.170")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid IP address"
     *     )
     * )
     */
    public function getLocation(Request $request)
    {
        // Get real client IP (works with CloudFront + ALB)
        $clientIp = $this->getRealClientIp($request);
        
        // Allow override with query parameter for testing
        $ip = $request->query('ip', $clientIp);
        
        // Validate IP address
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Invalid IP address',
                'query' => $ip
            ], 400);
        }
        
        // Get location data
        $locationData = $this->getIpLocation($ip);
        
        return response()->json($locationData);
    }
    
    /**
     * Get real client IP from request headers
     * Handles CloudFront -> ALB -> Laravel chain
     */
    private function getRealClientIp(Request $request)
    {
        // Priority order of headers to check
        $headers = [
            'HTTP_CF_CONNECTING_IP',           // CloudFront
            'HTTP_CLOUDFRONT_VIEWER_ADDRESS',  // CloudFront viewer address
            'HTTP_TRUE_CLIENT_IP',             // CloudFront/Akamai
            'HTTP_X_REAL_IP',                  // Nginx proxy
            'HTTP_X_FORWARDED_FOR',            // Standard proxy header
            'HTTP_CLIENT_IP',                  // Alternative
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // X-Forwarded-For can contain multiple IPs (client, proxy1, proxy2)
                // Get the first one (original client IP)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validate it's a public IP (not private/reserved)
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to Laravel's default (may be ALB private IP)
        return $request->ip();
    }
    
    /**
     * Fetch geolocation data for an IP address
     */
    private function getIpLocation($ip)
    {
        try {
            // Using ip-api.com (free tier: 45 requests/minute)
            // For production, consider paid services like MaxMind, IPStack, or ipgeolocation.io
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query'
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'success') {
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
                
                return [
                    'status' => 'fail',
                    'message' => $data['message'] ?? 'Unknown error',
                    'query' => $ip
                ];
            }
            
            Log::error('IP geolocation API error', [
                'ip' => $ip,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return [
                'status' => 'fail',
                'message' => 'Geolocation service unavailable',
                'query' => $ip
            ];
            
        } catch (\Exception $e) {
            Log::error('IP geolocation exception', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'fail',
                'message' => 'Service temporarily unavailable',
                'query' => $ip
            ];
        }
    }

    /**
     * @OA\Get(
     *     path="/api/countries",
     *     summary="Get country List",
     *     description="Fetches a list of all countries.",
     *     tags={"Locations"},
     *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json"))
     * )
     */
    public function getCountryList(Request $request)
    {
        $records = Country::query();

        /* Pagination */
        if ($request->filled('page') && $request->filled('length')) {
            $page = (int) $request->input('page');
            $length = (int) $request->input('length');
            $totalRecords = $records->count();
            $totalPages = ceil($totalRecords / $length);

            $records = $records->offset(($page - 1) * $length)->limit($length)->get();
        } else {
            $records = $records->get(['id', 'name']);
            $totalRecords = $records->count();
        }

        return response()->json([
            'message' => __("msg_rec_list"),
            'data' => $records,
            'total_pages' => $totalPages ?? 1,
            'total_records' => $totalRecords,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/states/{country_id}",
     *     summary="Get state List",
     *     description="Fetches a list of all states.",
     *     tags={"Locations"},
     *     @OA\Parameter(name="country_id", in="path", required=true, description="ID of the country", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json"))
     * )
     */
    public function getStateList(Request $request, $countryId)
    {
        $records = State::where('country_id', $countryId);

        /* Pagination */
        if ($request->filled('page') && $request->filled('length')) {
            $page = (int) $request->input('page');
            $length = (int) $request->input('length');
            $totalRecords = $records->count();
            $totalPages = ceil($totalRecords / $length);

            $records = $records->offset(($page - 1) * $length)->limit($length)->get();
        } else {
            $records = $records->get(['id', 'name']);
            $totalRecords = $records->count();
        }

        return response()->json([
            'message' => __("msg_rec_list"),
            'data' => $records,
            'total_pages' => $totalPages ?? 1,
            'total_records' => $totalRecords,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/cities/{country_id}",
     *     summary="Get city List",
     *     description="Fetches a list of all cities.",
     *     tags={"Locations"},
     *     @OA\Parameter(name="country_id", in="path", required=true, description="ID of the country", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json"))
     * )
     */
    public function getCityList(Request $request, $countryId)
    {
        $records = City::where('country_id', $countryId);

        /* Pagination */
        if ($request->filled('page') && $request->filled('length')) {
            $page = (int) $request->input('page');
            $length = (int) $request->input('length');
            $totalRecords = $records->count();
            $totalPages = ceil($totalRecords / $length);

            $records = $records->offset(($page - 1) * $length)->limit($length)->get();
        } else {
            $records = $records->get(['id', 'name']);
            $totalRecords = $records->count();
        }

        return response()->json([
            'message' => __("msg_rec_list"),
            'data' => $records,
            'total_pages' => $totalPages ?? 1,
            'total_records' => $totalRecords,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/zipcodes/{city_id}",
     *     summary="Get zipcode List",
     *     description="Fetches a list of all zipcodes.",
     *     tags={"Locations"},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="path",
     *         required=true,
     *         description="ID of the city",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination. Starts from 1.",
     *         required=true,
     *         example=1,
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="length",
     *         in="query",
     *         description="Number of records per page.",
     *         required=true,
     *         example=20,
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1
     *         )
     *     ),
     *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getZipcodeList(Request $request, $cityId)
    {
        $records = Zipcode::where('city_id', $cityId);

        /* Pagination */
        if ($request->filled('page') && $request->filled('length')) {
            $page = (int) $request->input('page');
            $length = (int) $request->input('length');
            $totalRecords = $records->count();
            $totalPages = ceil($totalRecords / $length);

            $records = $records->offset(($page - 1) * $length)->limit($length)->get();
        } else {
            $records = $records->get(['id', 'zip_code']);
            $totalRecords = $records->count();
        }

        return response()->json([
            'message' => __("msg_rec_list"),
            'data' => $records,
            'total_pages' => $totalPages ?? 1,
            'total_records' => $totalRecords,
        ]);
    }
}
