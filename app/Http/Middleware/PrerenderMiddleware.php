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
                // Use full URL for Prerender.io
                $url = env('PRERENDER_URL', 'https://service.prerender.io/') . $request->fullUrl();
                $token = env('PRERENDER_TOKEN');

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Prerender-Token: $token"]);
                $html = curl_exec($ch);
                curl_close($ch);

                return response($html);
            }
        }

        return $next($request);
    }
}
