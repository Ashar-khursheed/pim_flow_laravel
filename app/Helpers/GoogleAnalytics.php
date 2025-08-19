<?php

namespace App\Helpers;

class GoogleAnalytics
{
    private $keyFile;
    private $scopes = ['https://www.googleapis.com/auth/analytics.readonly'];
    private $token;

    public function __construct()
    {
        $this->keyFile = base_path('app/Script/analytics-key.json');
        $this->authenticate();
    }

    private function authenticate()
    {
        $json = json_decode(file_get_contents($this->keyFile), true);

        $jwtHeader = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT'
        ]));

        $now = time();
        $jwtClaim = $this->base64UrlEncode(json_encode([
            "iss" => $json['client_email'],
            "scope" => implode(' ', $this->scopes),
            "aud" => "https://oauth2.googleapis.com/token",
            "exp" => $now + 3600,
            "iat" => $now,
        ]));

        $signatureInput = $jwtHeader . '.' . $jwtClaim;

        openssl_sign($signatureInput, $signature, openssl_pkey_get_private($json['private_key']), 'sha256WithRSAEncryption');
        $jwtAssertion = $signatureInput . '.' . $this->base64UrlEncode($signature);

        // Exchange JWT for access token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwtAssertion,
        ]));
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $this->token = $response['access_token'] ?? null;
    }

    /**
     * Get GA4 Report dynamically
     */
    public function getReport(
        string $propertyId,
        array $dimensions = ['city', 'country'],
        array $metrics = ['activeUsers'],
        string $startDate = '2024-08-01',
        string $endDate = 'today',
        array $filters = []
    ) {
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport";

        // Build dimensions
        $dims = array_map(fn($d) => ['name' => $d], $dimensions);
        // Build metrics
        $mets = array_map(fn($m) => ['name' => $m], $metrics);

        $postData = [
            "dimensions" => $dims,
            "metrics"    => $mets,
            "dateRanges" => [
                ["startDate" => $startDate, "endDate" => $endDate]
            ]
        ];

        // Add filters if provided
        if (!empty($filters)) {
            $postData['dimensionFilter'] = $filters;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->token,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $response;
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
