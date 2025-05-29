<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserGuard
{
	public function handle(Request $request, Closure $next): Response
	{
		if (!($request->user() instanceof \App\Models\User)) {
			return response()->json(['message' => 'Unauthorized (User only)'], 403);
		}
		return $next($request);
	}
}