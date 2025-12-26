<?php 

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\SeoMonitors;
use App\Listeners\SeoMonitorsListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SeoMonitors::class => [
            SeoMonitorsListener::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}