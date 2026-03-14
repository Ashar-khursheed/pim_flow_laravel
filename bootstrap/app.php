<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserGuard;
use App\Http\Middleware\EnsureCustomerGuard;
use Illuminate\Auth\AuthenticationException;
use App\Http\Middleware\CurrencyMiddleware; 


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Alias middleware for optional route usage
        $middleware->alias([
            'user.guard' => EnsureUserGuard::class,
            'customer.guard' => EnsureCustomerGuard::class,
        
        ]);
          $middleware->append(CurrencyMiddleware::class);


    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        });

    })
    ->create();
