<?php
// app/Console/Commands/SyncExchangeRates.php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncExchangeRates extends Command
{
    protected $signature   = 'currency:sync-rates';
    protected $description = 'Sync exchange rates from moneyconvert API into ec_currencies table';

    private const API_URL = 'https://open.er-api.com/v6/latest/USD';

    // ec_currencies table mein symbol → API currency code mapping
    // symbol column se API ka key match karo
    private const SYMBOL_TO_CODE = [
        'AED' => 'AED',
        'SAR' => 'SAR',
        'KWD' => 'KWD',
        'BHD' => 'BHD',
        'QAR' => 'QAR',
        'OMR' => 'OMR',
        'USD' => 'USD',
        '$'   => 'USD',
        '€'   => 'EUR',
        '£'   => 'GBP',
        '₹'   => 'INR',
        '₨'   => 'PKR',
        'EUR' => 'EUR',
        'GBP' => 'GBP',
        'INR' => 'INR',
        'PKR' => 'PKR',
    ];

    public function handle(): int
    {
        $this->info('Fetching exchange rates...');

        try {
            $response = Http::timeout(10)->get(self::API_URL);

            if (!$response->successful()) {
                $this->error('API request failed: ' . $response->status());
                return self::FAILURE;
            }

            $rates = $response->json('rates', []);

            if (empty($rates)) {
                $this->error('No rates returned from API');
                return self::FAILURE;
            }

            $currencies = Currency::all();
            $updated    = 0;
            $skipped    = 0;

            foreach ($currencies as $currency) {
                // Symbol se currency code dhundo
                $code = self::SYMBOL_TO_CODE[$currency->symbol] ?? null;

                // Title mein code hoga shayad (e.g. "UAE Dirham" → check title)
                if (!$code) {
                    // Title se try karo common codes
                    $titleUpper = strtoupper($currency->title ?? '');
                    foreach ($rates as $rateCode => $_) {
                        if (str_contains($titleUpper, $rateCode)) {
                            $code = $rateCode;
                            break;
                        }
                    }
                }

                if (!$code || !isset($rates[$code])) {
                    $this->warn("Skipped: {$currency->title} ({$currency->symbol}) — no matching code");
                    $skipped++;
                    continue;
                }

                $currency->exchange_rate = $rates[$code];
                $currency->save();

                $this->line("Updated: {$currency->title} ({$code}) → {$rates[$code]}");
                $updated++;
            }

            $this->info("Done! Updated: {$updated}, Skipped: {$skipped}");
            Log::info('SyncExchangeRates: completed', ['updated' => $updated, 'skipped' => $skipped]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Exception: ' . $e->getMessage());
            Log::error('SyncExchangeRates: failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}