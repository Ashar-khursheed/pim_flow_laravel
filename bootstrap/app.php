<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserGuard;
use App\Http\Middleware\EnsureCustomerGuard;
use Illuminate\Auth\AuthenticationException;
use App\Http\Middleware\EcommerceCacheMiddleware;



return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    
    ->withMiddleware(function (Middleware $middleware) {
          $middleware->api(append: [
            EcommerceCacheMiddleware::class,
        ]);
        

        // Alias middleware for optional route usage
        $middleware->alias([
            'user.guard' => EnsureUserGuard::class,
            'customer.guard' => EnsureCustomerGuard::class,
            'cache.middleware' => EcommerceCacheMiddleware::class, // Optional alias for selective use
        
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        });
    })
    ->create();
