<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;

class GenerateProductFeed extends Command
{
    protected $signature = 'feed:generate';
    protected $description = 'Generate optimized data-feed.xml';

    public function handle()
    {
        $this->info('🚀 Starting feed generation...');
        $startTime = microtime(true);

        $path = storage_path('app/public/data-feed.xml');
        $handle = fopen($path, 'w');

        // Write XML header
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>');
        fwrite($handle, '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">');
        fwrite($handle, '<channel>');
        fwrite($handle, '<title>Product Feed</title>');
        fwrite($handle, '<link>' . config('app.url') . '</link>');
        fwrite($handle, '<description>Product Feed</description>');

        $totalProducts = Product::where('status', 'published')->count();
        $this->info('⚙️  Processing ' . number_format($totalProducts) . ' products...');
        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

        // Use the controller's mapProductToXml method
        $controller = new \App\Http\Controllers\ProductXMLFeedWatchController();

        // Stream products in chunks
        Product::with([
            'brand:id,name,logo',
            'categories:id,name,parent_id',
            'categories.parent:id,name',
            'slug:id,key,reference_id',
            'productSuppliers.vendor:id,name',
            'vendors:id,name',
            'seoUrl',
            'seoProductUrl',
            'productVariants'
        ])
        ->select([
            'id', 'name', 'sku', 'images', 'brand_id', 'status',
            'gen_type', 'approved', 'description', 'quote_available',
            'stock_status', 'barcode',
        ])
        ->where('status', 'published')
        ->orderBy('id', 'desc')
        ->chunk(200, function ($products) use ($handle, $controller, $bar) {
            foreach ($products as $product) {
                fwrite($handle, $controller->mapProductToXml($product));
                $bar->advance();
            }
        });

        fwrite($handle, '</channel></rss>');
        fclose($handle);

        $bar->finish();
        $this->newLine();

        // Get file size
        $xmlSize = filesize($path);

        if ($xmlSize == 0) {
            $this->error('❌ XML file is empty! Check database connection.');
            return;
        }

        // Create compressed version
        $this->info('🗜️  Compressing...');
        $xmlContent = file_get_contents($path);
        $gzPath = storage_path('app/public/data-feed.xml.gz');
        file_put_contents($gzPath, gzencode($xmlContent, 9));
        $gzSize = filesize($gzPath);
        
        $savings = round((1 - $gzSize / $xmlSize) * 100, 1);
        $duration = round(microtime(true) - $startTime, 2);

        $this->info('✅ Generation complete in ' . $duration . 's');
        $this->info('📊 Products: ' . number_format($totalProducts));
        $this->info('💾 XML size: ' . $this->formatBytes($xmlSize));
        $this->info('💾 GZ size: ' . $this->formatBytes($gzSize) . ' (' . $savings . '% smaller)');
        $this->info('🔗 XML: ' . url('storage/data-feed.xml'));
        $this->info('🔗 GZ: ' . url('storage/data-feed.xml.gz'));
    }

    private function formatBytes($bytes)
    {
        if ($bytes == 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1 << (10 * $pow)), 2) . ' ' . $units[$pow];
    }
}