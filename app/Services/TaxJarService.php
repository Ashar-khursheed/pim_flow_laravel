<?php

namespace App\Services;

use TaxJar\TaxJar;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TaxJarService
{
    protected $client;
    protected $cacheEnabled;
    protected $cacheTtl;

    public function __construct()
    {
        $this->client = TaxJar::withApiKey(config('services.taxjar.api_key'));
        $this->cacheEnabled = config('services.taxjar.cache_enabled', true);
        $this->cacheTtl = config('services.taxjar.cache_ttl', 3600); // 1 hour default
        
        // Set sandbox mode if enabled
        if (config('services.taxjar.sandbox', false)) {
            $this->client->setApiConfig('api_url', 'https://api.sandbox.taxjar.com');
        }
    }

    public function getTaxRate(string $zip, array $params = []): array
    {
        try {
            $cacheKey = $this->getCacheKey('rate', $zip, $params);
            
            if ($this->cacheEnabled && Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $response = $this->client->ratesForLocation($zip, $params);
            $result = $this->formatResponse($response);
            
            if ($this->cacheEnabled) {
                Cache::put($cacheKey, $result, $this->cacheTtl);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('TaxJar Rate Error: ' . $e->getMessage(), [
                'zip' => $zip,
                'params' => $params
            ]);
            throw $e;
        }
    }

    public function calculateTax(array $orderData): array
    {
        try {
            $response = $this->client->taxForOrder($orderData);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            Log::error('TaxJar Calculate Error: ' . $e->getMessage(), [
                'order_data' => $orderData
            ]);
            throw $e;
        }
    }

    public function createTransaction(array $transactionData): array
    {
        try {
            $response = $this->client->createOrder($transactionData);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            Log::error('TaxJar Transaction Error: ' . $e->getMessage(), [
                'transaction_data' => $transactionData
            ]);
            throw $e;
        }
    }

    public function updateTransaction(string $transactionId, array $transactionData): array
    {
        try {
            $response = $this->client->updateOrder($transactionId, $transactionData);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            Log::error('TaxJar Update Transaction Error: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
                'transaction_data' => $transactionData
            ]);
            throw $e;
        }
    }

    public function deleteTransaction(string $transactionId): array
    {
        try {
            $response = $this->client->deleteOrder($transactionId);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            Log::error('TaxJar Delete Transaction Error: ' . $e->getMessage(), [
                'transaction_id' => $transactionId
            ]);
            throw $e;
        }
    }

    public function listTransactions(array $params = []): array
    {
        try {
            $response = $this->client->listOrders($params);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            Log::error('TaxJar List Transactions Error: ' . $e->getMessage(), [
                'params' => $params
            ]);
            throw $e;
        }
    }

    public function validateAddress(array $addressData): array
    {
        try {
            $response = $this->client->validateAddress($addressData);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            Log::error('TaxJar Address Validation Error: ' . $e->getMessage(), [
                'address_data' => $addressData
            ]);
            throw $e;
        }
    }

    protected function formatResponse($response): array
    {
        if (is_object($response)) {
            return json_decode(json_encode($response), true);
        }
        return $response;
    }

    protected function getCacheKey(string $type, string $identifier, array $params = []): string
    {
        $paramsHash = md5(serialize($params));
        return "taxjar.{$type}.{$identifier}.{$paramsHash}";
    }
}