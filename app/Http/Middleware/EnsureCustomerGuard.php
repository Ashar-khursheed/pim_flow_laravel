<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerGuard
{
	public function handle(Request $request, Closure $next): Response
	{
		if (!($request->user() instanceof \App\Models\FrontEnd\Customer)) {
			return response()->json(['message' => 'Unauthorized - Customer only'], 403);
		}
		return $next($request);
	}
}