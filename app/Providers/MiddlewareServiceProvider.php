<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use CodebarAg\LaravelPrerender\Middleware\PrerenderMiddleware;

class MiddlewareServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

public function boot()
{
    $kernel = $this->app->make(Kernel::class);
    $kernel->pushMiddleware(\CodebarAg\LaravelPrerender\Middleware\PrerenderMiddleware::class);
}

}
