<?php
namespace App\Http\Middleware;

use App\Models\Country;
use App\Services\GeoLocationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\JsonResponse;

class CurrencyMiddleware
{
    private const PRICE_FIELDS = [
        'price', 'sale_price', 'list_price', 'cost_per_item',
        'total_cost_per_item', 'map', 'surcharge', 'additional_cost',
        'shipping_charge', 'restocking_fees', 'original_price',
        'front_sale_price', 'best_price',
    ];

    public function __construct(protected GeoLocationService $geoService) {}

    // public function handle(Request $request, Closure $next)
    // {
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

    //     \Log::info('CURRENCY_MW_FIRED', ['ip' => $ip]);

    //     $ctx = Cache::remember('currency_ctx_' . $ip, now()->addHours(6), function () use ($ip) {
    //         $geoData     = $this->geoService->getLocation($ip);
    //         $countryName = $geoData['country'] ?? null;

    //         \Log::info('CURRENCY_GEO', ['ip' => $ip, 'country' => $countryName]);

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

    //     // Response intercept
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
    public function handle(Request $request, Closure $next)
{
    // Sirf FrontEnd controllers pe apply karo
    $controller = $request->route()?->getControllerClass();

    // if (!$controller || !str_starts_with($controller, 'App\\Http\\Controllers\\FrontEnd\\')) {
    //     return $next($request);
    // }

    // Real client IP — AWS Load Balancer ke peeche X-Forwarded-For mein hoti hai
    $ip = $request->header('X-Forwarded-For')
        ?? $request->header('CF-Connecting-IP')
        ?? $request->ip();

    // Multiple IPs comma separated hoti hain — pehli real client IP hai
    if (str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }

    // Internal/private IPs ko fallback karo
    if (
        empty($ip) ||
        $ip === '127.0.0.1' ||
        $ip === '::1' ||
        str_starts_with($ip, '172.') ||
        str_starts_with($ip, '10.') ||
        str_starts_with($ip, '192.168.')
    ) {
        $ip = '8.8.8.8';
    }

    $ctx = Cache::remember('currency_ctx_' . $ip, now()->addHours(6), function () use ($ip) {
        $geoData     = $this->geoService->getLocation($ip);
        $countryName = $geoData['country'] ?? null;

        if ($countryName) {
            $country = Country::with('currency')
                ->where('name', $countryName)
                ->first();

            if ($country && $country->currency) {
                return [
                    'symbol'         => $country->currency->symbol,
                    'margin'         => (float) $country->margin,
                    'currency_title' => $country->currency->title,
                    'is_default'     => (bool) $country->currency->is_default,
                ];
            }
        }

        return $this->defaultContext();
    });

    app()->instance('currency.context', $ctx);

    $response = $next($request);

    if (!$response instanceof JsonResponse) {
        return $response;
    }

    // Default currency + no margin = kuch mat karo
    if ($ctx['is_default'] && $ctx['margin'] == 0) {
        return $response;
    }

    $data = $response->getData(true);
    $data = $this->transform($data, $ctx);
    $response->setData($data);

    return $response;
}

    // private function transform(mixed $data, array $ctx): mixed
    // {
    //     if (!is_array($data)) return $data;

    //     foreach ($data as $key => &$value) {
    //         if (in_array($key, self::PRICE_FIELDS) && is_numeric($value) && $value > 0) {
    //             $value = $this->convertPrice((float) $value, $ctx);
    //         } elseif ($key === 'currency' && is_string($value) && strlen($value) <= 5) {
    //             $value = $ctx['symbol'];
    //         } elseif (is_array($value)) {
    //             $value = $this->transform($value, $ctx);
    //         }
    //     }

    //     return $data;
    // }
    private function transform(mixed $data, array $ctx): mixed
{
    if (!is_array($data)) return $data;

    foreach ($data as $key => &$value) {
        if (in_array($key, self::PRICE_FIELDS) && is_numeric($value) && $value > 0) {
            $value = $this->convertPrice((float) $value, $ctx);

        } elseif ($key === 'currency') {
            if (is_string($value) && strlen($value) <= 10) {
                // String currency — simple replace
                $value = $ctx['symbol'];
            } elseif (is_array($value) && isset($value['symbol'])) {
                // Object currency — symbol override karo
                $value['symbol'] = $ctx['symbol'];
                $value['title']  = $ctx['currency_title'];
            }
            // Array ho toh recursion mat karo — already handled above

        } elseif ($key === 'currency_title' && is_string($value)) {
            // "2945.05 $" jaisi string — price + symbol wali
            // Replace karo symbol part
            $value = preg_replace('/[A-Z]{2,}|\$|AED|SAR|KWD|BHD|QAR/', $ctx['symbol'], $value);

        } elseif (is_array($value)) {
            $value = $this->transform($value, $ctx);
        }
    }

    return $data;
}

    private function convertPrice(float $price, array $ctx): float
    {
        $margin = $ctx['margin'] ?? 0;
        if ($margin == 0) return round($price, 2);
        return round($price * (1 + $margin / 100), 2);
    }

    private function defaultContext(): array
    {
        $default = \App\Models\Currency::where('is_default', 1)->first();
        return [
            'symbol'         => $default?->symbol ?? 'AED',
            'margin'         => 0.0,
            'currency_title' => $default?->title ?? 'UAE Dirham',
            'is_default'     => true,
        ];
    }
}

// namespace App\Http\Middleware;

// use App\Models\Country;
// use App\Services\GeoLocationService;
// use Closure;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Cache;
// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;
// use Symfony\Component\HttpFoundation\JsonResponse;

// class CurrencyMiddleware
// {
//     private const PRICE_FIELDS = [
//         'price', 'sale_price', 'list_price', 'cost_per_item',
//         'total_cost_per_item', 'map', 'surcharge', 'additional_cost',
//         'shipping_charge', 'restocking_fees', 'original_price',
//         'front_sale_price', 'best_price',
//     ];


//     private const SYMBOL_TO_CODE = [
//         'AED' => 'AED', 'SAR' => 'SAR', 'KWD' => 'KWD',
//         'BHD' => 'BHD', 'QAR' => 'QAR', 'OMR' => 'OMR',
//         'USD' => 'USD', '$'   => 'USD', '€'   => 'EUR',
//         '£'   => 'GBP', '₹'   => 'INR', '₨'   => 'PKR',
//         'EUR' => 'EUR', 'GBP' => 'GBP', 'INR' => 'INR',
//         'PKR' => 'PKR',
//     ];

//     public function __construct(protected GeoLocationService $geoService) {}

//     public function handle(Request $request, Closure $next)
//     {
//         // Real client IP — AWS Load Balancer ke peeche X-Forwarded-For mein hoti hai
//         $ip = $request->header('X-Forwarded-For')
//             ?? $request->header('CF-Connecting-IP')
//             ?? $request->ip();

//         // Multiple IPs comma separated hoti hain — pehli real client IP hai
//         if (str_contains($ip, ',')) {
//             $ip = trim(explode(',', $ip)[0]);
//         }

//         Log::info('CURRENCY_MW_FIRED', ['ip' => $ip]);

//         // Private/internal IP → default context (AED), geo skip karo
//         if (
//             empty($ip) ||
//             $ip === '127.0.0.1' ||
//             $ip === '::1' ||
//             str_starts_with($ip, '172.') ||
//             str_starts_with($ip, '10.') ||
//             str_starts_with($ip, '192.168.')
//         ) {
//             Log::info('CURRENCY_MW: private IP, using default AED', ['ip' => $ip]);
//             $ctx = $this->defaultContext();
//             app()->instance('currency.context', $ctx);
//             return $next($request);
//         }

//         // Public IP → geo detect karo, 6 hours cache
//         $ctx = Cache::remember('currency_ctx_' . $ip, now()->addHours(6), function () use ($ip) {
//             $geoData     = $this->geoService->getLocation($ip);
//             $countryName = $geoData['country'] ?? null;

//             Log::info('CURRENCY_GEO', ['ip' => $ip, 'country' => $countryName]);

//             if ($countryName) {
//                 $country = Country::with('currency')
//                     ->where('name', $countryName)
//                     ->first();

//                 if ($country && $country->currency) {
//                     $symbol = $country->currency->symbol;
//                     $code   = self::SYMBOL_TO_CODE[$symbol] ?? 'AED';

//                     return [
//                         'symbol'         => $symbol,
//                         'margin'         => (float) $country->margin,
//                         'currency_title' => $country->currency->title,
//                         'currency_code'  => $code,
//                         'is_default'     => (bool) $country->currency->is_default,
//                         'decimals'       => (int) ($country->currency->decimals ?? 2),
//                     ];
//                 }
//             }

//             Log::info('CURRENCY_MW: no country match, using default AED', ['ip' => $ip]);
//             return $this->defaultContext();
//         });

//         Log::info('CURRENCY_MW_CTX', ['ip' => $ip, 'ctx' => $ctx]);

//         app()->instance('currency.context', $ctx);

//         $response = $next($request);

//         if (!$response instanceof JsonResponse) {
//             return $response;
//         }

//         // AED default + no margin → kuch mat karo
//         if ($ctx['is_default'] && $ctx['margin'] == 0) {
//             return $response;
//         }

//         $data = $response->getData(true);
//         $data = $this->transform($data, $ctx);
//         $response->setData($data);

//         return $response;
//     }

//     // ─────────────────────────────────────────────
//     // Recursive transform
//     // ─────────────────────────────────────────────
//     private function transform(mixed $data, array $ctx): mixed
//     {
//             if (!is_array($data)) return $data;

//             foreach ($data as $key => &$value) {
//                 if (in_array($key, self::PRICE_FIELDS) && is_numeric($value) && $value > 0) {
//                     $value = $this->convertPrice((float) $value, $ctx);

//                 } elseif ($key === 'currency') {
//                 if (is_string($value) && strlen($value) <= 10) {
//                     $value = $ctx['symbol'];
//                 } elseif (is_array($value) && isset($value['symbol'])) {
//                     $value['symbol'] = $ctx['symbol'];
//                     $value['title']  = $ctx['currency_title'];
//                     unset($value['major_unit_name']);
//                     unset($value['minor_unit_name']);
//                 }
//                 }
//                 elseif ($key === 'currency_title' && is_string($value)) {
//                     $value = preg_replace('/[A-Z]{2,}|\$|AED|SAR|KWD|BHD|QAR/', $ctx['symbol'], $value);

//                 } elseif (is_array($value)) {
//                     $value = $this->transform($value, $ctx);
//                 }
//             }

//         return $data;
//     }

//     // ─────────────────────────────────────────────
//     // Price: AED → margin → exchange rate → target
//     // ─────────────────────────────────────────────
//     private function convertPrice(float $price, array $ctx): float
//     {
//         $margin     = $ctx['margin'] ?? 0;
//         $targetCode = $ctx['currency_code'] ?? 'AED';
//         $decimals   = $ctx['decimals'] ?? 2;

//         // Step 1: margin apply
//         $priceAfterMargin = $margin != 0
//             ? $price * (1 + $margin / 100)
//             : $price;

//         // Step 2: AED hi hai → no conversion
//         if ($targetCode === 'AED') {
//             return round($priceAfterMargin, $decimals);
//         }

//         // Step 3: exchange rates fetch (cached, no DB)
//         $rates      = $this->getExchangeRates();
//         $targetRate = $rates[$targetCode] ?? null;

//         if (!$targetRate) {
//             Log::warning('CURRENCY_MW: unknown code', ['code' => $targetCode]);
//             return round($priceAfterMargin, $decimals);
//         }

//         // AED → USD → Target
//         $converted = ($priceAfterMargin / self::AED_RATE) * $targetRate;

//         return round($converted, $decimals);
//     }

//     // ─────────────────────────────────────────────
//     // Exchange rates — open.er-api.com, 6 hours cache
//     // ─────────────────────────────────────────────
//     private function getExchangeRates(): array
//     {
//         return Cache::remember('exchange_rates', now()->addHours(6), function () {
//             try {
//                 $response = Http::timeout(5)->get('https://open.er-api.com/v6/latest/USD');

//                 if ($response->successful()) {
//                     Log::info('CURRENCY_MW: exchange rates refreshed from API');
//                     return $response->json('rates', []);
//                 }
//             } catch (\Throwable $e) {
//                 Log::error('CURRENCY_MW: rates fetch failed', ['error' => $e->getMessage()]);
//             }

//             Log::warning('CURRENCY_MW: using hardcoded fallback rates');
//             return [
//                 'AED' => 3.6725, 'USD' => 1.0,
//                 'SAR' => 3.75,   'KWD' => 0.3066,
//                 'BHD' => 0.376,  'QAR' => 3.64,
//                 'OMR' => 0.3847, 'EUR' => 0.866,
//                 'GBP' => 0.748,  'INR' => 92.71,
//                 'PKR' => 278.47,
//             ];
//         });
//     }

//     // ─────────────────────────────────────────────
//     // Default context — AED, no margin
//     // ─────────────────────────────────────────────
//     private function defaultContext(): array
//     {
//         $default = \App\Models\Currency::where('is_default', 1)->first();
//         return [
//             'symbol'         => $default?->symbol ?? 'AED',
//             'margin'         => 0.0,
//             'currency_title' => $default?->title ?? 'UAE Dirham',
//             'currency_code'  => 'AED',
//             'is_default'     => true,
//             'decimals'       => (int) ($default?->decimals ?? 2),
//         ];
//     }
// }