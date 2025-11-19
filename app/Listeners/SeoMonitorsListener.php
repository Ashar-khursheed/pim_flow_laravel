<?php

namespace App\Listeners;

use App\Events\SeoMonitors;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class SeoMonitorsListener
{

    public $queue = 'seo';
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SeoMonitors $event): void
    { dd('ssss');
        try {
            DB::table('seo_monitorings')->insert([
                'url' => 'https://developmentcalifornia.thehorecastore.co/',
                'keyword' =>'hello',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error('SEO Monitor Listener Error: '.$e->getMessage());
        }
    }
}
