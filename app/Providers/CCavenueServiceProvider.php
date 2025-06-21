<?php

namespace App\Providers;

use App\Services\CCavenueService;
use Illuminate\Support\ServiceProvider;

class CCavenueServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(CCavenueService::class, function ($app) {
            return new CCavenueService();
        });
    }

    public function boot()
    {
        //
    }
}

// 10. Generate Swagger documentation
// Run: php artisan l5-swagger:generate
// Access at: /api/documentation

?>