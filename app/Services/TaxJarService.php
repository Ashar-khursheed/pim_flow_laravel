<?php

namespace App\Services;

use TaxJar\TaxJar;

class TaxJarService
{
    protected $client;

    public function __construct()
    {
        $this->client = TaxJar::withApiKey(env('TAXJAR_API_KEY'));
    }

    // Get tax rate for a location
    public function getTaxRate($zip, $params = [])
    {
        return $this->client->ratesForLocation($zip, $params);
    }

    // Calculate sales tax for an order
    public function calculateTax(array $orderData)
    {
        return $this->client->taxForOrder($orderData);
    }

    // Example: Create transaction (optional)
    public function createTransaction(array $transactionData)
    {
        return $this->client->createOrder($transactionData);
    }
}
