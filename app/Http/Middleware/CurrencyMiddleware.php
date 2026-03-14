<?php
namespace App\Http\Middleware;

use App\Models\Country;
use App\Services\GeoLocationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CurrencyMiddleware
{
    public function __construct(protected GeoLocationService $geoService) {}

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        if (in_array($ip, ['127.0.0.1', '::1'])) {
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

        return $next($request);
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