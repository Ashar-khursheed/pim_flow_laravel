<?php
// app/Services/TaxJarService.php

namespace App\Services;

use TaxJar\Client;

class TaxJarService
{
    protected $client;

    public function __construct()
    {
        $this->client = Client::withApiKey(env('TAXJAR_API_KEY'));
    }

    /**
     * Get tax rate by ZIP code or full address.
     */
    public function getTaxRate($zip, $params = [])
    {
        return $this->client->ratesForLocation($zip, $params);
    }

    /**
     * Calculate sales tax for an order.
     */
    public function calculateSalesTax(array $order)
    {
        return $this->client->taxForOrder($order);
    }
}
