<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class StaxService
{
    public function charge(array $data)
    {
        // Ensure amount is in smallest currency unit (cents) if required
        // $data['amount'] = intval($data['amount'] * 100);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.stax.api_key'),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->post(config('services.stax.base_url') . '/v1/transactions', $data);

        if ($response->failed()) {
            throw new \Exception($response->body());
        }

        return $response->json();
    }
}
