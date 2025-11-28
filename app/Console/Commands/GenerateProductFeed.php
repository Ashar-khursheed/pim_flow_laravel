<?php
// app/Console/Commands/GenerateProductFeed.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateProductFeed extends Command
{
    protected $signature = 'feed:generate';
    protected $description = 'Generate data-feed.xml';

    public function handle()
    {
        $this->info('🚀 Starting feed generation...');

        // Call your existing controller method
        $response = app(\App\Http\Controllers\ProductXMLFeedWatchController::class)
                    ->generateProductFeed(new \Illuminate\Http\Request());

        // Save the XML content to storage
        Storage::disk('public')->put('data-feed.xml', $response->getContent());

        $this->info('✅ data-feed.xml generated successfully!');
        $this->info('🔗 Access it at: ' . url('storage/data-feed.xml'));
    }
}
