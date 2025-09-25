<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class StaxService
{
    public function charge(array $data)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.stax.api_key'),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->post(config('services.stax.base_url') . '/charges', $data);

        if ($response->failed()) {
            throw new \Exception($response->body());
        }

        return $response->json();
    }
}
