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

    public function getTaxRate($zip, $params = [])
    {
        return $this->client->ratesForLocation($zip, $params);
    }

    public function calculateTax(array $orderData)
    {
        return $this->client->taxForOrder($orderData);
    }

    public function createTransaction(array $transactionData)
    {
        return $this->client->createOrder($transactionData);
    }
}
