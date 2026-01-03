<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FrontEnd\Order;
class Cancelled72Ccavenue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ccavenue:cancelled72-ccavenue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {        
        $this->info('Search order Started...');
        $seventyTwoHoursAgo = now()->subHours(72);       
        Order::where('status', 'Pending')
        ->where('is_ccavenue', '1')
        ->whereDate('created_at', '<', $seventyTwoHoursAgo)
        ->update(['status' => 'Cancelled','updated_at' => now(),]);
          $this->info('Search order Completed!');
    }
}
