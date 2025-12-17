<?php
 

namespace App\Services;

 
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\config;
class TqlRateService
{
         
    public function getRates(array $shipmentData, $token)
    {   
      $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Ocp-Apim-Subscription-Key' => config('services.tql.subscription_key'),
        'Content-Type'=> 'application/json'
    ])
    
    ->post('https://public.api.tql.com/ltl/quotes', $shipmentData);
 
      return $response;
          // return ([
            // 'status' => $response->status(),
            // 'body'   => $response->json(),
            // 'raw'    => $response->body()
            // ]);
         
        
        
    }
}
