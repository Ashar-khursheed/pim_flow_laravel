<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class StaxService
{
    /**
     * Charge a customer using Stax API
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function charge(array $data)
    {
        // Convert amount to smallest currency unit (cents) if needed
        if (isset($data['amount'])) {
            $data['amount'] = intval($data['amount'] * 100);
        }

        // Generate a unique idempotency key for this request
        $idempotencyKey = uniqid('stax_', true);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.stax.api_key'),
            'Idempotency-Key' => $idempotencyKey, // automatically attached
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post(config('services.stax.base_url') . '/payments/charge', $data);

        if ($response->failed()) {
            throw new \Exception($response->body());
        }

        return $response->json();
    }
}
