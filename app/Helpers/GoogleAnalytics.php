<?php

namespace App\Helpers;

class GoogleAnalytics
{
    private $keyFile;
    private $scopes = ['https://www.googleapis.com/auth/analytics.readonly'];
    private $token;

    public function __construct()
    {
        $this->keyFile = storage_path('app/analytics-key.json');
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


    public function getReport($propertyId)
    {
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport";

        $postData = [
            "dimensions" => [
                ["name" => "city"],
                ["name" => "country"]
            ],
            "metrics" => [
                ["name" => "activeUsers"]
            ],
            "dateRanges" => [
                ["startDate" => "2024-08-01", "endDate" => "today"]
            ]
        ];

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
