<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;

class GenerateProductFeed extends Command
{
    protected $signature = 'feed:generate';
    protected $description = 'Generate data-feed.xml';

    public function handle()
    {
        $this->info('🚀 Starting feed generation...');

        $website = config('app.url', 'https://www.thehorecastore.com');
        $tempFile = storage_path('app/temp-feed.xml');
        
        // Open file for writing (streams to disk, not memory)
        $handle = fopen($tempFile, 'w');

        // Write XML header
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fwrite($handle, '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL);
        fwrite($handle, '<channel>' . PHP_EOL);
        fwrite($handle, '<title>Product Feed</title>' . PHP_EOL);
        fwrite($handle, '<link>' . htmlspecialchars($website) . '</link>' . PHP_EOL);
        fwrite($handle, '<description>DataFeedWatch Product Feed</description>' . PHP_EOL);

        // Get controller instance
        $controller = app(\App\Http\Controllers\ProductXMLFeedWatchController::class);
        
        // Process products in chunks
        $totalProcessed = 0;
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
        ->chunk(500, function ($products) use ($handle, $controller, &$totalProcessed) {
            foreach ($products as $product) {
                fwrite($handle, $controller->mapProductToXml($product));
            }
            $totalProcessed += count($products);
            $this->info("✓ Processed {$totalProcessed} products...");
        });

        // Close XML
        fwrite($handle, '</channel>' . PHP_EOL);
        fwrite($handle, '</rss>' . PHP_EOL);
        fclose($handle);

        // Move to public storage
        Storage::disk('public')->put('data-feed.xml', file_get_contents($tempFile));
        unlink($tempFile);

        $this->info("✅ Feed generated with {$totalProcessed} products!");
        $this->info('🔗 Access at: ' . url('storage/data-feed.xml'));
        
        return 0;
    }
}