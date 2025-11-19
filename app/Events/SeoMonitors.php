<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeoMonitors
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $data;

    /**
     * Create a new event instance.
     */
    public function __construct($data)
    {
        //  dd($data);
        $this->data = $data;
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
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
