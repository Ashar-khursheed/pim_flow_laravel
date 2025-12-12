<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\ProductXMLFeedWatchController;

class GenerateProductFeed extends Command
{
    protected $signature = 'feed:generate';
    protected $description = 'Generate optimized data-feed.xml';

    public function handle()
    {
        $this->info('🚀 Starting feed generation...');
        $startTime = microtime(true);

        // Call the controller method that works
        $controller = new ProductXMLFeedWatchController();
        $response = $controller->generateProductFeed(new \Illuminate\Http\Request());

        // Save XML content
        $path = storage_path('app/public/data-feed.xml');
        file_put_contents($path, $response->getContent());

        $xmlSize = filesize($path);

        // Create compressed version
        $this->info('🗜️  Compressing...');
        $xmlContent = file_get_contents($path);
        $gzPath = storage_path('app/public/data-feed.xml.gz');
        file_put_contents($gzPath, gzencode($xmlContent, 9));
        $gzSize = filesize($gzPath);
        
        $savings = round((1 - $gzSize / $xmlSize) * 100, 1);
        $duration = round(microtime(true) - $startTime, 2);

        $this->info('✅ Generation complete in ' . $duration . 's');
        $this->info('💾 XML size: ' . $this->formatBytes($xmlSize));
        $this->info('💾 GZ size: ' . $this->formatBytes($gzSize) . ' (' . $savings . '% smaller)');
        $this->info('🔗 XML: ' . url('storage/data-feed.xml'));
        $this->info('🔗 GZ: ' . url('storage/data-feed.xml.gz'));
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1 << (10 * $pow)), 2) . ' ' . $units[$pow];
    }
}