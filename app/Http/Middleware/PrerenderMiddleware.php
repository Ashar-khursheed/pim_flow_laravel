<?php
namespace App\Http\Middleware;

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
                // Correct full URL handling
                $targetUrl = $request->fullUrl();
                $prerenderUrl = env('PRERENDER_URL') . $targetUrl;

                $ch = curl_init($prerenderUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-Prerender-Token: ' . env('PRERENDER_TOKEN')
                ]);
                $html = curl_exec($ch);

                if ($html === false) {
                    // fallback if Prerender fails
                    return $next($request);
                }

                curl_close($ch);
                return response($html);
            }
        }

        return $next($request);
    }
}
