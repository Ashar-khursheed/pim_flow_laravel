<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyConverter
{
	private const API_URL = 'https://cdn.moneyconvert.net/api/latest.json';
	private const CACHE_KEY = 'currency_exchange_rates';
	private const CACHE_DURATION = 3600; // 1 hour in seconds

	/**
	 * Get exchange rates from API or cache
	 *
	 * @return array|null
	 */
	public static function getRates()
	{
		return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
			try {
				$response = Http::timeout(10)->get(self::API_URL);

				if ($response->successful()) {
					$data = $response->json();
					return $data['rates'] ?? null;
				}

				Log::error('Currency API failed', [
					'status' => $response->status(),
					'body' => $response->body()
				]);

				return null;
			} catch (\Exception $e) {
				Log::error('Currency API exception', [
					'error' => $e->getMessage()
				]);

				return null;
			}
		});
	}

	/**
	 * Convert amount from source currency to target currency
	 *
	 * @param string $sourceCurrency Source currency code (e.g., 'AED', 'USD')
	 * @param string $targetCurrency Target currency code (e.g., 'USD', 'EUR')
	 * @param float $sourceAmount Amount in source currency
	 * @return float|null Converted amount or null if conversion fails
	 */
	public static function convertCurrency($sourceCurrency, $targetCurrency, $sourceAmount)
	{
		/* If source and target are the same, return the amount */
		if ($sourceCurrency === $targetCurrency) {
			return (float) $sourceAmount;
		}

		$rates = self::getRates();

		if (!$rates) {
			Log::warning('Currency rates not available');
			return null;
		}

		/* Get source currency rate (USD to source) */
		$sourceRate = $rates[$sourceCurrency] ?? null;

		/* Get target currency rate (USD to target) */
		$targetRate = $rates[$targetCurrency] ?? null;

		if (!$sourceRate || !$targetRate) {
			Log::warning('Currency not found', [
				'source' => $sourceCurrency,
				'target' => $targetCurrency
			]);
			return null;
		}

		/**
		 * Convert: (amount in source) * (target rate / source rate)
		 *
		 * Example:
		 * 100 AED to USD
		 * AED rate = 3.6725, USD rate = 1
		 * 100 * (1 / 3.6725) = 27.23 USD
		 */

		// return $sourceAmount * ($targetRate / $sourceRate);
		return round($sourceAmount * ($targetRate / $sourceRate), 2);
	}

	/**
	 * Get exchange rate from source to target currency
	 *
	 * @param string $sourceCurrency Source currency code
	 * @param string $targetCurrency Target currency code
	 * @return float|null Exchange rate or null
	 */
	public static function getRate($sourceCurrency, $targetCurrency)
	{
		if ($sourceCurrency === $targetCurrency) {
			return 1.0;
		}

		$rates = self::getRates();

		if (!$rates) {
			return null;
		}

		$sourceRate = $rates[$sourceCurrency] ?? null;
		$targetRate = $rates[$targetCurrency] ?? null;

		if (!$sourceRate || !$targetRate) {
			return null;
		}

		return $targetRate / $sourceRate;
	}

	/**
	 * Get all available currencies
	 *
	 * @return array
	 */
	public static function getAvailableCurrencies()
	{
		$rates = self::getRates();
		return $rates ? array_keys($rates) : [];
	}

	/**
	 * Clear cached rates (force refresh)
	 *
	 * @return void
	 */
	public static function clearCache()
	{
		Cache::forget(self::CACHE_KEY);
	}

	/**
	 * Format amount with currency symbol
	 *
	 * @param float $amount
	 * @param string $currency
	 * @return string
	 */
	public static function format($amount, $currency = 'AED')
	{
		$symbols = [
			'AED' => 'AED',
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'SAR' => 'SAR',
			'INR' => '₹',
			'JPY' => '¥',
			'CNY' => '¥',
			'AUD' => 'A$',
			'CAD' => 'C$',
		];

		$symbol = $symbols[$currency] ?? $currency;

		return $symbol . ' ' . number_format($amount, 2, '.', ',');
	}

	/**
	 * Convert and format in one call
	 *
	 * @param string $sourceCurrency
	 * @param string $targetCurrency
	 * @param float $sourceAmount
	 * @return string|null Formatted converted amount or null
	 */
	public static function convertAndFormat($sourceCurrency, $targetCurrency, $sourceAmount)
	{
		$convertedAmount = self::convertCurrency($sourceCurrency, $targetCurrency, $sourceAmount);

		if ($convertedAmount === null) {
			return null;
		}

		return self::format($convertedAmount, $targetCurrency);
	}
}