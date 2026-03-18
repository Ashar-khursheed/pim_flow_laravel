<?php
// namespace App\Http\Middleware;

// use App\Models\Country;
// use App\Services\GeoLocationService;
// use Closure;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Cache;
// use Symfony\Component\HttpFoundation\JsonResponse;

// class CurrencyMiddleware
// {
//     private const PRICE_FIELDS = [
//         'price', 'sale_price', 'list_price', 'cost_per_item',
//         'total_cost_per_item', 'map', 'surcharge', 'additional_cost',
//         'shipping_charge', 'restocking_fees', 'original_price',
//         'front_sale_price', 'best_price',
//     ];

//     public function __construct(protected GeoLocationService $geoService) {}

//     // public function handle(Request $request, Closure $next)
//     // {
//     //     // Real client IP — AWS Load Balancer ke peeche X-Forwarded-For mein hoti hai
//     //     $ip = $request->header('X-Forwarded-For')
//     //         ?? $request->header('CF-Connecting-IP')
//     //         ?? $request->ip();

//     //     // Multiple IPs comma separated hoti hain — pehli real client IP hai
//     //     if (str_contains($ip, ',')) {
//     //         $ip = trim(explode(',', $ip)[0]);
//     //     }

//     //     // Internal/private IPs ko fallback karo
//     //     if (
//     //         empty($ip) ||
//     //         $ip === '127.0.0.1' ||
//     //         $ip === '::1' ||
//     //         str_starts_with($ip, '172.') ||
//     //         str_starts_with($ip, '10.') ||
//     //         str_starts_with($ip, '192.168.')
//     //     ) {
//     //         $ip = '8.8.8.8';
//     //     }

//     //     \Log::info('CURRENCY_MW_FIRED', ['ip' => $ip]);

//     //     $ctx = Cache::remember('currency_ctx_' . $ip, now()->addHours(6), function () use ($ip) {
//     //         $geoData     = $this->geoService->getLocation($ip);
//     //         $countryName = $geoData['country'] ?? null;

//     //         \Log::info('CURRENCY_GEO', ['ip' => $ip, 'country' => $countryName]);

//     //         if ($countryName) {
//     //             $country = Country::with('currency')
//     //                 ->where('name', $countryName)
//     //                 ->first();

//     //             if ($country && $country->currency) {
//     //                 return [
//     //                     'symbol'         => $country->currency->symbol,
//     //                     'margin'         => (float) $country->margin,
//     //                     'currency_title' => $country->currency->title,
//     //                     'is_default'     => (bool) $country->currency->is_default,
//     //                 ];
//     //             }
//     //         }

//     //         return $this->defaultContext();
//     //     });

//     //     app()->instance('currency.context', $ctx);

//     //     // Response intercept
//     //     $response = $next($request);

//     //     if (!$response instanceof JsonResponse) {
//     //         return $response;
//     //     }

//     //     // Default currency + no margin = kuch mat karo
//     //     if ($ctx['is_default'] && $ctx['margin'] == 0) {
//     //         return $response;
//     //     }

//     //     $data = $response->getData(true);
//     //     $data = $this->transform($data, $ctx);
//     //     $response->setData($data);

//     //     return $response;
//     // }
//     public function handle(Request $request, Closure $next)
// {
//     // Sirf FrontEnd controllers pe apply karo
//     $controller = $request->route()?->getControllerClass();

//     if (!$controller || !str_starts_with($controller, 'App\\Http\\Controllers\\FrontEnd\\')) {
//         return $next($request);
//     }

//     // Real client IP — AWS Load Balancer ke peeche X-Forwarded-For mein hoti hai
//     $ip = $request->header('X-Forwarded-For')
//         ?? $request->header('CF-Connecting-IP')
//         ?? $request->ip();

//     // Multiple IPs comma separated hoti hain — pehli real client IP hai
//     if (str_contains($ip, ',')) {
//         $ip = trim(explode(',', $ip)[0]);
//     }

//     // Internal/private IPs ko fallback karo
//     if (
//         empty($ip) ||
//         $ip === '127.0.0.1' ||
//         $ip === '::1' ||
//         str_starts_with($ip, '172.') ||
//         str_starts_with($ip, '10.') ||
//         str_starts_with($ip, '192.168.')
//     ) {
//         $ip = '8.8.8.8';
//     }

//     $ctx = Cache::remember('currency_ctx_' . $ip, now()->addHours(6), function () use ($ip) {
//         $geoData     = $this->geoService->getLocation($ip);
//         $countryName = $geoData['country'] ?? null;

//         if ($countryName) {
//             $country = Country::with('currency')
//                 ->where('name', $countryName)
//                 ->first();

//             if ($country && $country->currency) {
//                 return [
//                     'symbol'         => $country->currency->symbol,
//                     'margin'         => (float) $country->margin,
//                     'currency_title' => $country->currency->title,
//                     'is_default'     => (bool) $country->currency->is_default,
//                 ];
//             }
//         }

//         return $this->defaultContext();
//     });

//     app()->instance('currency.context', $ctx);

//     $response = $next($request);

//     if (!$response instanceof JsonResponse) {
//         return $response;
//     }

//     // Default currency + no margin = kuch mat karo
//     if ($ctx['is_default'] && $ctx['margin'] == 0) {
//         return $response;
//     }

//     $data = $response->getData(true);
//     $data = $this->transform($data, $ctx);
//     $response->setData($data);

//     return $response;
// }

//     // private function transform(mixed $data, array $ctx): mixed
//     // {
//     //     if (!is_array($data)) return $data;

//     //     foreach ($data as $key => &$value) {
//     //         if (in_array($key, self::PRICE_FIELDS) && is_numeric($value) && $value > 0) {
//     //             $value = $this->convertPrice((float) $value, $ctx);
//     //         } elseif ($key === 'currency' && is_string($value) && strlen($value) <= 5) {
//     //             $value = $ctx['symbol'];
//     //         } elseif (is_array($value)) {
//     //             $value = $this->transform($value, $ctx);
//     //         }
//     //     }

//     //     return $data;
//     // }
//     private function transform(mixed $data, array $ctx): mixed
// {
//     if (!is_array($data)) return $data;

//     foreach ($data as $key => &$value) {
//         if (in_array($key, self::PRICE_FIELDS) && is_numeric($value) && $value > 0) {
//             $value = $this->convertPrice((float) $value, $ctx);

//         } elseif ($key === 'currency') {
//             if (is_string($value) && strlen($value) <= 10) {
//                 // String currency — simple replace
//                 $value = $ctx['symbol'];
//             } elseif (is_array($value) && isset($value['symbol'])) {
//                 // Object currency — symbol override karo
//                 $value['symbol'] = $ctx['symbol'];
//                 $value['title']  = $ctx['currency_title'];
//             }
//             // Array ho toh recursion mat karo — already handled above

//         } elseif ($key === 'currency_title' && is_string($value)) {
//             // "2945.05 $" jaisi string — price + symbol wali
//             // Replace karo symbol part
//             $value = preg_replace('/[A-Z]{2,}|\$|AED|SAR|KWD|BHD|QAR/', $ctx['symbol'], $value);

//         } elseif (is_array($value)) {
//             $value = $this->transform($value, $ctx);
//         }
//     }

//     return $data;
// }

//     private function convertPrice(float $price, array $ctx): float
//     {
//         $margin = $ctx['margin'] ?? 0;
//         if ($margin == 0) return round($price, 2);
//         return round($price * (1 + $margin / 100), 2);
//     }

//     private function defaultContext(): array
//     {
//         $default = \App\Models\Currency::where('is_default', 1)->first();
//         return [
//             'symbol'         => $default?->symbol ?? 'AED',
//             'margin'         => 0.0,
//             'currency_title' => $default?->title ?? 'UAE Dirham',
//             'is_default'     => true,
//         ];
//     }
// }


namespace App\Http\Middleware;

use App\Models\Country;
use App\Services\GeoLocationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class CurrencyMiddleware
{
    private const PRICE_FIELDS = [
        'price', 'sale_price', 'list_price', 'cost_per_item',
        'total_cost_per_item', 'map', 'surcharge', 'additional_cost',
        'shipping_charge', 'restocking_fees', 'original_price',
        'front_sale_price', 'best_price',
    ];

    private const SYMBOL_TO_CODE = [
        'AED' => 'AED', 'SAR' => 'SAR', 'KWD' => 'KWD',
        'BHD' => 'BHD', 'QAR' => 'QAR', 'OMR' => 'OMR',
        'USD' => 'USD', '$'   => 'USD', '€'   => 'EUR',
        '£'   => 'GBP', '₹'   => 'INR', '₨'   => 'PKR',
        'EUR' => 'EUR', 'GBP' => 'GBP', 'INR' => 'INR',
        'PKR' => 'PKR', 'Rs'  => 'PKR', 'Rs.' => 'PKR',
    ];

    // ─── FIX #5: ip-api returns full country names — map them to DB names ─────
    private const COUNTRY_NAME_MAP = [
        'United Arab Emirates' => 'UAE',
        'UAE'                  => 'UAE',
        'Kingdom of Saudi Arabia' => 'Saudi Arabia',
        'KSA'                  => 'Saudi Arabia',
        'Pakistan'             => 'Pakistan',
        'India'                => 'India',
        'United Kingdom'       => 'United Kingdom',
        'United States'        => 'United States',
        'Bahrain'              => 'Bahrain',
        'Kuwait'               => 'Kuwait',
        'Qatar'                => 'Qatar',
        'Oman'                 => 'Oman',
    ];

    // Base currency — prices in DB are stored in AED
    private const BASE_CURRENCY_CODE   = 'AED';
    private const BASE_CURRENCY_SYMBOL = 'AED';
    private const BASE_AED_RATE        = 3.6725;

    public function __construct(protected GeoLocationService $geoService) {}

    public function handle(Request $request, Closure $next)
    {
            $controller = $request->route()?->getControllerClass();

                if (!str_starts_with($request->path(), 'api/frontend')) {
                return $next($request);
            }
        // Optional: force a country for testing (?force_country=Pakistan)
        $forceCountry = $request->query('force_country');

        // Resolve real client IP
        $ip = $request->header('X-Forwarded-For')
            ?? $request->header('CF-Connecting-IP')
            ?? $request->ip();

        if (str_contains((string) $ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $isPrivateIp = (
            empty($ip) ||
            $ip === '127.0.0.1' ||
            $ip === '::1' ||
            str_starts_with($ip, '172.') ||
            str_starts_with($ip, '10.') ||
            str_starts_with($ip, '192.168.')
        );

        // ─── FIX #3: Private IP without force → skip geo, return AED default ──
        if ($isPrivateIp && !$forceCountry) {
            $ctx = $this->defaultContext();
            app()->instance('currency.context', $ctx);
            return $this->processJsonResponse($next($request), $ctx);
        }

        $cacheKey = 'currency_ctx_v2_' . ($forceCountry ? 'forced_' . $forceCountry : $ip);

        $ctx = Cache::remember($cacheKey, now()->addHours(6), function () use ($ip, $forceCountry, $isPrivateIp) {
            $countryName = $forceCountry;

            if (!$countryName && !$isPrivateIp) {
                $geoData     = $this->geoService->getLocation($ip);
                $countryName = $geoData['country'] ?? null;
                Log::info('CURRENCY_GEO', ['ip' => $ip, 'geo_country' => $countryName]);
            }

            if ($countryName) {
                // ─── FIX #5: Normalize country name to match DB ────────────────
                $dbName = self::COUNTRY_NAME_MAP[$countryName] ?? $countryName;

                $country = Country::with('currency')
                    ->whereRaw('LOWER(name) = ?', [strtolower($dbName)])
                    ->first();

                if ($country && $country->currency) {
                    $symbol = $country->currency->symbol;
                    $code   = self::SYMBOL_TO_CODE[$symbol] ?? $symbol;

                    Log::info('CURRENCY_MW: country matched', [
                        'country' => $dbName,
                        'symbol'  => $symbol,
                        'code'    => $code,
                        'margin'  => $country->margin,
                    ]);

                    return [
                        'symbol'         => $symbol,
                        'margin'         => (float) $country->margin,
                        'currency_title' => $country->currency->title,
                        'currency_code'  => $code,
                        'is_default'     => ($code === self::BASE_CURRENCY_CODE),
                        'decimals'       => (int) ($country->currency->decimals ?? 2),
                    ];
                }

                Log::warning('CURRENCY_MW: country not found in DB', [
                    'geo_name' => $countryName,
                    'db_name'  => $dbName,
                ]);
            }

            return $this->defaultContext();
        });

        app()->instance('currency.context', $ctx);

        return $this->processJsonResponse($next($request), $ctx);
    }

    
    private function processJsonResponse($response, array $ctx)
    {
        if (!$response instanceof JsonResponse) {
            return $response;
        }

        // ALWAYS transform — even for default AED context
        // Because ec_products may have USD symbol stored — we must override it
        $data = $response->getData(true);
        $data = $this->transform($data, $ctx);
        $response->setData($data);

        return $response;
    }

    private function transform(mixed $data, array $ctx): mixed
    {
        if (!is_array($data)) return $data;

        // Pass 1: convert numeric price fields (skip if default + no margin = no conversion needed)
        $needsPriceConversion = !($ctx['is_default'] && $ctx['margin'] == 0);
        foreach ($data as $key => &$value) {
            if ($needsPriceConversion && in_array($key, self::PRICE_FIELDS) && is_numeric($value) && $value > 0) {
                $value = $this->convertPrice((float) $value, $ctx);
            } elseif (is_array($value)) {
                $value = $this->transform($value, $ctx);
            }
        }

        // Pass 2: update currency symbol/title string fields
        foreach ($data as $key => &$value) {
            if ($key === 'currency') {
                if (is_string($value) && strlen($value) <= 10) {
                    $value = $ctx['symbol'];
                } elseif (is_array($value) && isset($value['symbol'])) {
                    $value['symbol'] = $ctx['symbol'];
                    $value['title']  = $ctx['currency_title'];
                }
            } elseif (($key === 'currency_title' || $key === 'price_with_symbol') && is_string($value)) {
                if (isset($data['price'])) {
                    $value = $ctx['symbol'] . ' ' . $data['price'];
                } elseif (isset($data['sale_price'])) {
                    $value = $ctx['symbol'] . ' ' . $data['sale_price'];
                } else {
                    $value = preg_replace(
                        '/\b(AED|USD|SAR|KWD|BHD|QAR|OMR|PKR|INR|EUR|GBP|Rs\.?)\b|\$/',
                        $ctx['symbol'],
                        $value
                    );
                }
            }
        }

        return $data;
    }

    private function convertPrice(float $price, array $ctx): float
    {
        $margin     = $ctx['margin'] ?? 0;
        $targetCode = $ctx['currency_code'] ?? self::BASE_CURRENCY_CODE;
        $decimals   = $ctx['decimals'] ?? 2;

        $priceAfterMargin = $margin != 0 ? $price * (1 + $margin / 100) : $price;

        // AED → AED, no FX needed
        if ($targetCode === self::BASE_CURRENCY_CODE) {
            return round($priceAfterMargin, $decimals);
        }

        // AED → USD (using fixed peg) → target currency
        $rates      = $this->getExchangeRates();
        $targetRate = $rates[$targetCode] ?? null;

        if (!$targetRate) {
            Log::warning('CURRENCY_MW: no exchange rate for', ['code' => $targetCode]);
            return round($priceAfterMargin, $decimals);
        }

        $converted = ($priceAfterMargin / self::BASE_AED_RATE) * $targetRate;
        return round($converted, $decimals);
    }

    private function getExchangeRates(): array
    {
        return Cache::remember('exchange_rates_v2', now()->addHours(6), function () {
            try {
                $response = Http::timeout(5)->get('https://open.er-api.com/v6/latest/USD');
                if ($response->successful()) {
                    $rates = $response->json('rates', []);
                    if (!empty($rates)) {
                        return $rates;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('CURRENCY_MW: exchange rate fetch failed', ['error' => $e->getMessage()]);
            }

            // Fallback static rates (relative to USD)
            return [
                'AED' => 3.6725, 'USD' => 1.0,    'SAR' => 3.75,
                'KWD' => 0.3066, 'BHD' => 0.376,  'QAR' => 3.64,
                'OMR' => 0.3847, 'EUR' => 0.92,   'GBP' => 0.79,
                'INR' => 83.5,   'PKR' => 278.47,
            ];
        });
    }

    // ─── FIX #2: Always return AED as base (prices stored in AED) ─────────────
    private function defaultContext(): array
    {
        return [
            'symbol'         => self::BASE_CURRENCY_SYMBOL,
            'margin'         => 0.0,
            'currency_title' => 'UAE Dirham',
            'currency_code'  => self::BASE_CURRENCY_CODE,
            'is_default'     => true,
            'decimals'       => 2,
        ];
    }
}