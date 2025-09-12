<?php
namespace App\Http\Middleware;
use App\Http\Middleware\PrerenderMiddleware;

use Closure;

class PrerenderMiddleware
{
    public function handle($request, Closure $next)
    {
        $userAgent = $request->header('User-Agent', '');
        $crawlers = [
            'googlebot', 'bingbot', 'yahoo', 'baiduspider',
            'facebookexternalhit', 'twitterbot', 'rogerbot', 'linkedinbot'
        ];

        foreach ($crawlers as $crawler) {
            if (stripos($userAgent, $crawler) !== false) {

                // Build full URL for Prerender.io
                $targetUrl = $request->fullUrl(); // full URL of the current request
                $prerenderUrl = rtrim(env('PRERENDER_URL'), '/') . $request->getRequestUri();


                $ch = curl_init($prerenderUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-Prerender-Token: ' . env('PRERENDER_TOKEN')
                ]);
                $html = curl_exec($ch);
                curl_close($ch);

                // If Prerender fails, fallback to normal app
                if ($html === false) {
                    return $next($request);
                }

                return response($html);
            }
        }

        return $next($request);
    }
}
