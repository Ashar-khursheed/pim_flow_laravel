<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Utm;

class CaptureUtm
{
    public function handle(Request $request, Closure $next)
    {
        $sessionId = $request->header('X-Session-ID') ?? $request->session()->getId() ?? Str::uuid()->toString();

        if ($request->hasAny(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'])) {
            Utm::create([
                'utm_source'   => $request->query('utm_source'),
                'utm_medium'   => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign'),
                'utm_term'     => $request->query('utm_term'),
                'utm_content'  => $request->query('utm_content'),
                'gclid'        => $request->query('gclid'),
                'session_id'   => $sessionId,
            ]);
        }

        $response = $next($request);
        $response->headers->set('X-Session-ID', $sessionId);

        return $response;
    }
}
