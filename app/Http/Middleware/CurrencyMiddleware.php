<?php


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
//         'PKR' => 'PKR', 'Rs'  => 'PKR', 'Rs.' => 'PKR',
//     ];

//     private const COUNTRY_NAME_MAP = [
//         'United Arab Emirates'    => 'UAE',
//         'UAE'                     => 'UAE',
//         'Kingdom of Saudi Arabia' => 'Saudi Arabia',
//         'KSA'                     => 'Saudi Arabia',
//         'Pakistan'                => 'Pakistan',
//         'India'                   => 'India',
//         'United Kingdom'          => 'United Kingdom',
//         'United States'           => 'United States',
//         'Bahrain'                 => 'Bahrain',
//         'Kuwait'                  => 'Kuwait',
//         'Qatar'                   => 'Qatar',
//         'Oman'                    => 'Oman',
//     ];

//     private const ISO_TO_NAME_MAP = [
//         'AE' => 'UAE',
//         'SA' => 'Saudi Arabia',
//         'PK' => 'Pakistan',
//         'IN' => 'India',
//         'US' => 'United States',
//         'GB' => 'United Kingdom',
//         'BH' => 'Bahrain',
//         'KW' => 'Kuwait',
//         'QA' => 'Qatar',
//         'OM' => 'Oman',
//     ];

//     private const BASE_CURRENCY_CODE   = 'AED';
//     private const BASE_CURRENCY_SYMBOL = 'AED';
//     private const BASE_AED_RATE        = 3.6725;

//     public function __construct(protected GeoLocationService $geoService) {}

//     public function handle(Request $request, Closure $next)
//     {
//         $startTime = microtime(true);

//         if (!str_contains($request->path(), 'frontend')) {
//             return $next($request);
//         }

//         $forceCountry = $request->header('X-Forced-Country') ?? $request->query('force_country');

//         // Resolve real client IP
//         $ip = $request->header('CF-Connecting-IP')
//             ?? $request->header('X-Real-IP')
//             ?? $request->header('X-Forwarded-For')
//             ?? $request->ip();

//         if (str_contains((string) $ip, ',')) {
//             $ip = trim(explode(',', (string) $ip)[0]);
//         }

//         // Fast path: Check CDN headers first (Cloudflare / CloudFront)
//         $cdnCountry = $request->header('CF-IPCountry') ?? $request->header('CloudFront-Viewer-Country');

//         $isPrivateIp = $this->isPrivateIp($ip);

//         // If local/private and no override, use default instantly
//         if ($isPrivateIp && !$forceCountry && !$cdnCountry) {
//             $ctx = $this->buildContext($ctx = $this->defaultContext());
//             app()->instance('currency.context', $ctx);
//             return $this->processJsonResponse($next($request), $ctx);
//         }

//         $cacheKey = 'currency_ctx_v3_' . ($forceCountry
//             ? 'forced_' . $forceCountry
//             : ($cdnCountry ? 'cdn_' . $cdnCountry : $ip));

//         $ctx = Cache::remember($cacheKey, now()->addHours(6), function () use ($ip, $forceCountry, $cdnCountry, $isPrivateIp) {
//             $countryName = $forceCountry;

//             // Priority 1: CDN header (very fast)
//             if (!$countryName && $cdnCountry && $cdnCountry !== 'XX') {
//                 $countryName = $cdnCountry;
//             }

//             // Priority 2: GeoIP API (fallback)
//             if (!$countryName && !$isPrivateIp) {
//                 $geoData     = $this->geoService->getLocation($ip);
//                 $countryName = $geoData['country'] ?? null;
//                 Log::info('CURRENCY_MW: GeoIP lookup', ['ip' => $ip, 'country' => $countryName]);
//             }

//             if ($countryName) {
//                 // If it's a 2-char ISO code (from CDN), map to full name
//                 $dbName = (strlen((string) $countryName) === 2)
//                     ? (self::ISO_TO_NAME_MAP[strtoupper($countryName)] ?? $countryName)
//                     : (self::COUNTRY_NAME_MAP[$countryName] ?? $countryName);

//                 $country = Country::with('currency')
//                     ->whereRaw('LOWER(name) = ?', [strtolower($dbName)])
//                     ->first();

//                 if ($country && $country->currency) {
//                     $symbol = $country->currency->symbol;
//                     $code   = self::SYMBOL_TO_CODE[$symbol] ?? $symbol;

//                     Log::info('CURRENCY_MW: Found country in DB', [
//                         'country' => $dbName,
//                         'symbol'  => $symbol,
//                         'code'    => $code,
//                     ]);

//                     return [
//                         'symbol'         => $symbol,
//                         'margin'         => (float) $country->margin,
//                         'currency_title' => $country->currency->title,
//                         'currency_code'  => $code,
//                         'is_default'     => ($code === self::BASE_CURRENCY_CODE),
//                         'decimals'       => (int) ($country->currency->decimals ?? 2),
//                     ];
//                 }

//                 Log::warning('CURRENCY_MW: Country not found in DB or missing currency', [
//                     'geo_name' => $countryName,
//                     'db_name'  => $dbName,
//                 ]);
//             }

//             Log::info('CURRENCY_MW: Falling back to default AED context');
//             return $this->defaultContext();
//         });

//         // ✅ FIX 2: Prefetch rates BEFORE $next() so app('currency.context') always has rates
//         $ctx['rates'] = $this->getExchangeRates();
//         app()->instance('currency.context', $ctx);

//         $response = $next($request);

//         if (!$response instanceof JsonResponse || !$response->isSuccessful()) {
//             return $response;
//         }

//         $result = $this->processJsonResponse($response, $ctx);

//         Log::info('CURRENCY_MW_FINISH', [
//             'ms'       => round((microtime(true) - $startTime) * 1000, 2),
//             'currency' => $ctx['currency_code'] ?? '?',
//         ]);

//         return $result;
//     }

//     private function buildContext(array $ctx): array
//     {
//         $ctx['rates'] = $this->getExchangeRates();
//         return $ctx;
//     }

//     // ✅ Correct RFC-1918 private IP ranges
//     private function isPrivateIp(string $ip): bool
//     {
//         if (empty($ip) || in_array($ip, ['127.0.0.1', '::1'])) {
//             return true;
//         }

//         $ipLong = ip2long($ip);
//         if ($ipLong === false) {
//             return false;
//         }

//         foreach ([
//             ['10.0.0.0',    '10.255.255.255'],
//             ['172.16.0.0',  '172.31.255.255'],  // ✅ Only true private range, NOT all 172.x
//             ['192.168.0.0', '192.168.255.255'],
//             ['169.254.0.0', '169.254.255.255'],  // link-local
//         ] as [$start, $end]) {
//             if ($ipLong >= ip2long($start) && $ipLong <= ip2long($end)) {
//                 return true;
//             }
//         }

//         return false;
//     }

//     private function processJsonResponse($response, array $ctx)
//     {
//         if (!$response instanceof JsonResponse) {
//             return $response;
//         }

//         // ✅ FIX 1: ALWAYS set these headers on every currency-sensitive response.
//         // Cloudflare IGNORES the Vary header — it will cache AED and serve it to everyone.
//         // CDN-Cache-Control and Cloudflare-CDN-Cache-Control explicitly tell CF: never cache this.
//         $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
//         $response->headers->set('Pragma', 'no-cache');
//         $response->headers->set('CDN-Cache-Control', 'no-store');                  // Generic CDN
//         $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');       // Cloudflare specific
//         $response->headers->set('Surrogate-Control', 'no-store');                  // Varnish / Fastly
//         $response->headers->set('Vary', 'CF-IPCountry, X-Forced-Country, Accept-Encoding');

//         $isDefault = ($ctx['is_default'] ?? false) && ($ctx['margin'] ?? 0) == 0;
//         $isForced  = request()->has('force_country') || request()->hasHeader('X-Forced-Country');

//         // FAST PATH: Already AED with no margin and not forced — skip expensive transform
//         // CDN headers are already set above, so CF won't cache it ✅
//         if ($isDefault && !$isForced) {
//             return $response;
//         }

//         $data = $response->getData(true);

//         Log::info('CURRENCY_MW_TRANSFORM', [
//             'currency'   => $ctx['currency_code'] ?? 'AED',
//             'symbol'     => $ctx['symbol'] ?? 'AED',
//             'is_default' => $ctx['is_default'] ?? false,
//             'data_keys'  => is_array($data) ? array_keys($data) : 'not_array',
//         ]);

//         $transformedData = $this->transform($data, $ctx);
//         $response->setData($transformedData);

//         return $response;
//     }

//     private function transform(mixed $data, array $ctx): mixed
//     {
//         if (!is_array($data)) {
//             return $data;
//         }

//         $needsPriceConversion = !($ctx['is_default'] && $ctx['margin'] == 0);
//         $symbol               = $ctx['symbol'] ?? 'AED';
//         $rates                = $ctx['rates'] ?? [];

//         foreach ($data as $key => &$value) {
//             // Recurse into nested arrays
//             if (is_array($value)) {
//                 $value = $this->transform($value, $ctx);

//                 // If this is a currency object, override its sub-fields
//                 if ($key === 'currency' && isset($value['symbol'])) {
//                     $value['symbol'] = $symbol;
//                     $value['title']  = $ctx['currency_title'] ?? $value['title'] ?? 'Selected Currency';
//                 }
//                 continue;
//             }

//             // Convert numeric price fields
//             if ($needsPriceConversion && in_array($key, self::PRICE_FIELDS) && is_numeric($value) && $value > 0) {
//                 $margin           = $ctx['margin'] ?? 0;
//                 $targetCode       = $ctx['currency_code'] ?? self::BASE_CURRENCY_CODE;
//                 $decimals         = $ctx['decimals'] ?? 2;
//                 $priceAfterMargin = $margin != 0 ? (float) $value * (1 + $margin / 100) : (float) $value;

//                 if ($targetCode === self::BASE_CURRENCY_CODE) {
//                     $value = round($priceAfterMargin, $decimals);
//                 } else {
//                     $targetRate = $rates[$targetCode] ?? null;
//                     if ($targetRate) {
//                         $value = round(($priceAfterMargin / self::BASE_AED_RATE) * $targetRate, $decimals);
//                     } else {
//                         Log::warning('CURRENCY_MW: No exchange rate found', ['code' => $targetCode]);
//                         $value = round($priceAfterMargin, $decimals);
//                     }
//                 }
//                 continue;
//             }

//             // Replace currency code string (e.g. "AED" → "SAR")
//             if ($key === 'currency' && is_string($value) && strlen($value) <= 10) {
//                 $value = $symbol;
//                 continue;
//             }

//             // Replace currency symbol in formatted strings (e.g. "AED 100" → "SAR 137")
//             if (($key === 'currency_title' || $key === 'price_with_symbol') && is_string($value)) {
//                 if (isset($data['price']) && is_numeric($data['price'])) {
//                     $value = $symbol . ' ' . $data['price'];
//                 } elseif (isset($data['sale_price']) && is_numeric($data['sale_price'])) {
//                     $value = $symbol . ' ' . $data['sale_price'];
//                 } else {
//                     $value = preg_replace(
//                         '/\b(AED|USD|SAR|KWD|BHD|QAR|OMR|PKR|INR|EUR|GBP|Rs\.?)\b|\$/',
//                         $symbol,
//                         $value
//                     );
//                 }
//             }
//         }

//         return $data;
//     }

//     private function convertPrice(float $price, array $ctx): float
//     {
//         $margin     = $ctx['margin'] ?? 0;
//         $targetCode = $ctx['currency_code'] ?? self::BASE_CURRENCY_CODE;
//         $decimals   = $ctx['decimals'] ?? 2;

//         $priceAfterMargin = $margin != 0 ? $price * (1 + $margin / 100) : $price;

//         if ($targetCode === self::BASE_CURRENCY_CODE) {
//             return round($priceAfterMargin, $decimals);
//         }

//         $rates      = $this->getExchangeRates();
//         $targetRate = $rates[$targetCode] ?? null;

//         if (!$targetRate) {
//             Log::warning('CURRENCY_MW: No exchange rate for', ['code' => $targetCode]);
//             return round($priceAfterMargin, $decimals);
//         }

//         return round(($priceAfterMargin / self::BASE_AED_RATE) * $targetRate, $decimals);
//     }

//     private function getExchangeRates(): array
//     {
//         return Cache::remember('exchange_rates_v2', now()->addHours(6), function () {
//             try {
//                 $response = Http::timeout(5)->get('https://open.er-api.com/v6/latest/USD');
//                 if ($response->successful()) {
//                     $rates = $response->json('rates', []);
//                     if (!empty($rates)) {
//                         return $rates;
//                     }
//                 }
//             } catch (\Throwable $e) {
//                 Log::error('CURRENCY_MW: Exchange rate fetch failed', ['error' => $e->getMessage()]);
//             }

//             // Hardcoded fallback rates
//             return [
//                 'AED' => 3.6725, 'USD' => 1.0,    'SAR' => 3.75,
//                 'KWD' => 0.3066, 'BHD' => 0.376,  'QAR' => 3.64,
//                 'OMR' => 0.3847, 'EUR' => 0.92,   'GBP' => 0.79,
//                 'INR' => 83.5,   'PKR' => 278.47,
//             ];
//         });
//     }

//     private function defaultContext(): array
//     {
//         return [
//             'symbol'         => self::BASE_CURRENCY_SYMBOL,
//             'margin'         => 0.0,
//             'currency_title' => 'UAE Dirham',
//             'currency_code'  => self::BASE_CURRENCY_CODE,
//             'is_default'     => true,
//             'decimals'       => 2,
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
        'MVR' => 'MVR', 'SCR' => 'SCR',
    ];

    // ISO-2 code → DB country name
    private const ISO_TO_NAME = [
        'AE' => 'UAE',            'SA' => 'Saudi Arabia',
        'PK' => 'Pakistan',       'IN' => 'India',
        'US' => 'United States',  'GB' => 'United Kingdom',
        'BH' => 'Bahrain',        'KW' => 'Kuwait',
        'QA' => 'Qatar',          'OM' => 'Oman',
        'MV' => 'Maldives',      'SC' => 'Seychelles',
    ];

    private const BASE_CODE   = 'AED';
    private const BASE_SYMBOL = 'AED';
    private const BASE_RATE   = 3.6725; // 1 USD = 3.6725 AED

    public function __construct(protected GeoLocationService $geoService) {}

    // ─────────────────────────────────────────────
    // MAIN
    // ─────────────────────────────────────────────
    public function handle(Request $request, Closure $next)
    {
        // Only run for frontend routes
        if (!str_contains($request->path(), 'frontend')) {
            return $next($request);
        }

        // Detect country → get currency context
        $ctx = $this->detectCurrency($request);

        // Make it available globally (e.g. in controllers/services)
        app()->instance('currency.context', $ctx);

        // Run the actual request
        $response = $next($request);

        // Only transform successful JSON responses
        if (!$response instanceof JsonResponse || !$response->isSuccessful()) {
            return $response;
        }

        // Set no-cache headers so CDN never serves wrong currency to anyone
        $this->setNoCacheHeaders($response);

        // AED with no margin → nothing to convert, return as-is
        if ($ctx['is_default'] && $ctx['margin'] == 0) {
            return $response;
        }

        // Convert prices in response
        $data = $response->getData(true);
        $response->setData($this->convertPrices($data, $ctx));

        return $response;
    }

    // ─────────────────────────────────────────────
    // STEP 1: Detect which country the request is from
    // ─────────────────────────────────────────────
    private function detectCurrency(Request $request): array
    {
        // Allow manual override (for testing)
        $forceCountry = $request->header('X-Forced-Country')
            ?? $request->query('force_country');

        if ($forceCountry) {
            return $this->getCurrencyForCountry($forceCountry, 'forced_' . $forceCountry);
        }

        // ── Priority 1: Cloudflare / CloudFront CDN header (most reliable, works on mobile too)
        $cdnIso = $request->header('CF-IPCountry')
            ?? $request->header('CloudFront-Viewer-Country');

        if ($cdnIso && $cdnIso !== 'XX' && strlen($cdnIso) === 2) {
            return $this->getCurrencyForCountry($cdnIso, 'cdn_' . strtolower($cdnIso));
        }

        // ── Priority 2: GeoIP lookup by real IP
        $ip = $this->getRealIp($request);

        if ($ip && !$this->isPrivateIp($ip)) {
            return $this->getCurrencyForCountry($ip, 'ip_' . md5($ip), isIp: true);
        }

        // ── Fallback: default AED (local/private IP)
        return $this->defaultContext();
    }

    // ─────────────────────────────────────────────
    // STEP 2: Given a country identifier, return currency context
    // Cache only successful resolutions for 6h, failures for 2min
    // ─────────────────────────────────────────────
    private function getCurrencyForCountry(string $identifier, string $cacheKey, bool $isIp = false): array
    {
        // Check cache first
        $cached = Cache::get('curr_ctx_' . $cacheKey);
        if ($cached) {
            return $cached;
        }

        // Resolve country name
        if ($isIp) {
            // GeoIP lookup
            try {
                $geo        = $this->geoService->getLocation($identifier);
                $countryRaw = $geo['country'] ?? null;
            } catch (\Throwable $e) {
                Log::error('CURRENCY: GeoIP failed', ['ip' => $identifier, 'err' => $e->getMessage()]);
                $countryRaw = null;
            }
        } else {
            $countryRaw = $identifier;
        }

        if (!$countryRaw) {
            // GeoIP failed — cache for only 2 min so it retries soon
            Cache::put('curr_ctx_' . $cacheKey, $this->defaultContext(), now()->addMinutes(2));
            return $this->defaultContext();
        }

        // Normalize: ISO-2 code or full name → DB-friendly name
        $dbName = strlen($countryRaw) === 2
            ? (self::ISO_TO_NAME[strtoupper($countryRaw)] ?? null)
            : $countryRaw;

        // DB lookup
        $country = null;

        if ($dbName) {
            $country = Country::with('currency')
                ->whereRaw('LOWER(name) = ?', [strtolower($dbName)])
                ->first();
        }

        // Fallback: try iso_code column if name lookup failed
        if (!$country && strlen($countryRaw) === 2) {
            $country = Country::with('currency')
                ->whereRaw('UPPER(iso_code) = ?', [strtoupper($countryRaw)])
                ->first();
        }

        if (!$country || !$country->currency) {
            Log::warning('CURRENCY: Country not in DB', ['input' => $countryRaw]);
            // Unknown country — cache 2 min only
            Cache::put('curr_ctx_' . $cacheKey, $this->defaultContext(), now()->addMinutes(2));
            return $this->defaultContext();
        }

        // Build context
        $symbol = $country->currency->symbol;
        $code   = self::SYMBOL_TO_CODE[$symbol] ?? $symbol;

        $ctx = [
            'symbol'         => $symbol,
            'margin'         => (float) $country->margin,
            'currency_title' => $country->currency->title,
            'currency_code'  => $code,
            'is_default'     => ($code === self::BASE_CODE),
            'decimals'       => (int) ($country->currency->decimals ?? 2),
            'rates'          => $this->getExchangeRates(),
        ];

        Log::info('CURRENCY: Resolved', [
            'input'    => $identifier,
            'country'  => $country->name,
            'currency' => $code,
        ]);

        // Successful resolution → cache 6 hours
        Cache::put('curr_ctx_' . $cacheKey, $ctx, now()->addHours(6));

        return $ctx;
    }

    // ─────────────────────────────────────────────
    // STEP 3: Convert all price fields in response
    // ─────────────────────────────────────────────
    private function convertPrices(mixed $data, array $ctx): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $symbol     = $ctx['symbol']        ?? self::BASE_SYMBOL;
        $margin     = $ctx['margin']        ?? 0;
        $targetCode = $ctx['currency_code'] ?? self::BASE_CODE;
        $decimals   = $ctx['decimals']      ?? 2;
        $rates      = $ctx['rates']         ?? [];
        $targetRate = $rates[$targetCode]   ?? null;
        $needsConv  = !($ctx['is_default'] && $margin == 0);

        foreach ($data as $key => &$value) {
            // Recurse into nested arrays/objects
            if (is_array($value)) {
                $value = $this->convertPrices($value, $ctx);

                // Update nested currency object fields
                if ($key === 'currency' && isset($value['symbol'])) {
                    $value['symbol'] = $symbol;
                    $value['title']  = $ctx['currency_title'] ?? $value['title'] ?? '';
                }
                continue;
            }

            // ── Convert numeric price fields
            if ($needsConv && in_array($key, self::PRICE_FIELDS, true) && is_numeric($value) && $value > 0) {
                $price = (float) $value;

                // Apply margin first
                if ($margin != 0) {
                    $price = $price * (1 + $margin / 100);
                }

                // Convert from AED to target currency
                if ($targetCode !== self::BASE_CODE) {
                    if ($targetRate) {
                        // AED → USD → target
                        $price = ($price / self::BASE_RATE) * $targetRate;
                    } else {
                        Log::warning('CURRENCY: No rate for ' . $targetCode);
                    }
                }

                $value = round($price, $decimals);
                continue;
            }

            // ── Replace bare currency code string e.g. "AED" → "SAR"
            if ($key === 'currency' && is_string($value) && strlen($value) <= 10) {
                $value = $symbol;
                continue;
            }

            // ── Replace symbol in formatted price strings e.g. "AED 100"
            if (in_array($key, ['currency_title', 'price_with_symbol'], true) && is_string($value)) {
                $value = preg_replace(
                    '/\b(AED|USD|SAR|KWD|BHD|QAR|OMR|PKR|INR|EUR|GBP|Rs\.?)\b|\$/',
                    $symbol,
                    $value
                );
            }
        }

        return $data;
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    /**
     * Get the real client IP — handles Cloudflare, proxies, IPv6, CGNAT
     */
    private function getRealIp(Request $request): ?string
    {
        // Cloudflare sets this to the true client IP always
        $ip = $request->header('CF-Connecting-IP');
        if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
            return trim($ip);
        }

        // Nginx reverse proxy
        $ip = $request->header('X-Real-IP');
        if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
            return trim($ip);
        }

        // X-Forwarded-For — find first public IP (skip private + CGNAT)
        $forwarded = $request->header('X-Forwarded-For');
        if ($forwarded) {
            foreach (array_map('trim', explode(',', $forwarded)) as $candidate) {
                if (
                    filter_var($candidate, FILTER_VALIDATE_IP) &&
                    filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) &&
                    !$this->isCgnat($candidate)
                ) {
                    return $candidate;
                }
            }
        }

        return $request->ip();
    }

    private function isPrivateIp(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', ''], true)) {
            return true;
        }

        // IPv6 — only loopback + link-local are "private"
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip === '::1' || str_starts_with(strtolower($ip), 'fe80:');
        }

        // IPv4 private ranges
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $long = ip2long($ip);

        foreach ([
            ['10.0.0.0',    '10.255.255.255'],
            ['172.16.0.0',  '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['100.64.0.0',  '100.127.255.255'], // CGNAT (mobile carriers)
        ] as [$start, $end]) {
            if ($long >= ip2long($start) && $long <= ip2long($end)) {
                return true;
            }
        }

        return false;
    }

    private function isCgnat(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        $long = ip2long($ip);
        return $long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255');
    }

    private function setNoCacheHeaders(JsonResponse $response): void
    {
        $response->headers->set('Cache-Control',                'private, no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma',                       'no-cache');
        $response->headers->set('CDN-Cache-Control',            'no-store');
        $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');
        $response->headers->set('Surrogate-Control',            'no-store');
        $response->headers->set('Vary',                         'CF-IPCountry, X-Forced-Country, Accept-Encoding');
    }

    private function getExchangeRates(): array
    {
        return Cache::remember('exchange_rates_v2', now()->addHours(6), function () {
            try {
                $resp = Http::timeout(5)->get('https://open.er-api.com/v6/latest/USD');
                if ($resp->successful()) {
                    $rates = $resp->json('rates', []);
                    if (!empty($rates)) {
                        return $rates;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('CURRENCY: Rate fetch failed', ['err' => $e->getMessage()]);
            }

            // Hardcoded fallback
            return [
                'AED' => 3.6725, 'USD' => 1.0,    'SAR' => 3.75,
                'KWD' => 0.3066, 'BHD' => 0.376,  'QAR' => 3.64,
                'OMR' => 0.3847, 'EUR' => 0.92,   'GBP' => 0.79,
                'INR' => 83.5,   'PKR' => 278.47, 'MVR' => 15.4,
                'SCR' => 14.2,
            ];
        });
    }

    private function defaultContext(): array
    {
        return [
            'symbol'         => self::BASE_SYMBOL,
            'margin'         => 0.0,
            'currency_title' => 'UAE Dirham',
            'currency_code'  => self::BASE_CODE,
            'is_default'     => true,
            'decimals'       => 2,
            'rates'          => [],
        ];
    }
}