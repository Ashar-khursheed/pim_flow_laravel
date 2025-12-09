<?php
 

namespace App\Services;

 
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\config;
class TqlRateService
{
         
    public function getRates(array $shipmentData)
    {  
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' .config('services.tql.api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post(config('services.tql.base_url') . '/rates', $shipmentData);
 
        if ($response->successful()) {
            return $response->json()['rates'] ?? [];  // Assume response has 'rates' array
        }
        
        return [];
    }
}
