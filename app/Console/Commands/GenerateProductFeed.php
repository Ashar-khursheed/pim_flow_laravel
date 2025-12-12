<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\SeoManagement;
use App\Models\ProductAttribute;

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

        // Bulk fetch SEO data
        $this->info('📦 Fetching SEO data...');
        $seoDataMap = SeoManagement::whereIn('relational_id', function($query) {
            $query->select('id')->from('products')->where('status', 'published');
        })->get()->keyBy('relational_id');

        // Bulk fetch attributes
        $this->info('📦 Fetching attributes...');
        $attributesMap = ProductAttribute::join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
            ->whereIn('product_id', function($query) {
                $query->select('id')->from('products')->where('status', 'published');
            })
            ->select('product_id', 'attributes.name as attribute_name', 'product_attributes.attribute_value')
            ->get()
            ->groupBy('product_id');

        $totalProducts = Product::where('status', 'published')->count();
        $this->info('⚙️  Processing ' . number_format($totalProducts) . ' products...');
        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

        // Stream products in chunks
        Product::with([
            'brand:id,name',
            'categories:id,name,parent_id',
            'categories.parent:id,name',
            'productSuppliers:id,product_id,price,sale_price',
            'seoProductUrl:id,relational_id,url'
        ])
        ->select(['id', 'name', 'sku', 'barcode', 'images', 'brand_id', 'description', 'stock_status'])
        ->where('status', 'published')
        ->orderBy('id', 'desc')
        ->chunk(200, function ($products) use ($handle, $seoDataMap, $attributesMap, $bar) {
            foreach ($products as $product) {
                fwrite($handle, $this->buildProductXml($product, $seoDataMap, $attributesMap));
                $bar->advance();
            }
        });

        fwrite($handle, '</channel></rss>');
        fclose($handle);

        $bar->finish();
        $this->newLine();

        // Get file size
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
        $this->info('📊 Products: ' . number_format($totalProducts));
        $this->info('💾 XML size: ' . $this->formatBytes($xmlSize));
        $this->info('💾 GZ size: ' . $this->formatBytes($gzSize) . ' (' . $savings . '% smaller)');
        $this->info('🔗 XML: ' . url('storage/data-feed.xml'));
        $this->info('🔗 GZ: ' . url('storage/data-feed.xml.gz'));
    }

    private function buildProductXml($product, $seoDataMap, $attributesMap)
    {
        $supplier = $product->productSuppliers->first();
        $price = $supplier->price ?? 0;
        $salePrice = $supplier->sale_price ?? 0;
        
        $seoData = $seoDataMap->get($product->id);
        $attributes = $attributesMap->get($product->id, collect());

        // Compact description (300 chars max)
        $desc = '';
        if (is_string($product->description)) {
            $decoded = json_decode($product->description, true);
            $desc = is_array($decoded) && json_last_error() === JSON_ERROR_NONE
                ? implode(' ', array_filter($decoded))
                : $product->description;
        }
        $desc = mb_substr(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($desc))), 0, 300);

        $images = json_decode($product->images, true);
        $image = $images[0] ?? null;

        $category = $product->categories->first();
        $parent = $category?->parent;
        
        // Build slug
        $fullSlug = '';
        if (method_exists($product, 'parent_category_url') && method_exists($product, 'category_url')) {
            $fullSlug = $product->parent_category_url() . '/' . $product->category_url() . '/' . ($product->seoProductUrl->url ?? $product->id);
        } else {
            $fullSlug = $product->seoProductUrl->url ?? $product->id;
        }

        $type = '';
        if ($parent && $category) {
            $type = $parent->name . ' > ' . $category->name;
        } elseif ($category) {
            $type = $category->name;
        }

        // Ultra-compact XML
        $xml = '<item>';
        $xml .= '<g:id>' . $product->id . '</g:id>';
        $xml .= '<g:sku>' . htmlspecialchars($product->sku ?? '', ENT_XML1) . '</g:sku>';
        $xml .= '<g:barcode>' . htmlspecialchars($product->barcode ?? '', ENT_XML1) . '</g:barcode>';
        $xml .= '<g:title>' . htmlspecialchars($seoData?->meta_title ?? $product->name, ENT_XML1) . '</g:title>';
        $xml .= '<g:link>' . config('app.url') . '/' . $fullSlug . '</g:link>';
        $xml .= '<g:description>' . htmlspecialchars($desc, ENT_XML1) . '</g:description>';
        $xml .= '<g:price>' . number_format($price, 2, '.', '') . '</g:price>';
        $xml .= '<g:sale_price>' . number_format($salePrice, 2, '.', '') . '</g:sale_price>';
        $xml .= '<g:availability>' . $product->stock_status . '</g:availability>';
        $xml .= '<g:brand>' . htmlspecialchars($product->brand?->name ?? '', ENT_XML1) . '</g:brand>';
        
        if ($image) {
            $xml .= '<g:image_link>' . htmlspecialchars($image, ENT_XML1) . '</g:image_link>';
        }

        // Only 2 attributes
        foreach ($attributes->take(2) as $attr) {
            $xml .= '<g:product_detail>';
            $xml .= '<g:section_name>Specification</g:section_name>';
            $xml .= '<g:attribute_name>' . htmlspecialchars($attr->attribute_name, ENT_XML1) . '</g:attribute_name>';
            $xml .= '<g:attribute_value>' . htmlspecialchars($attr->attribute_value, ENT_XML1) . '</g:attribute_value>';
            $xml .= '</g:product_detail>';
        }

        $xml .= '<g:identifier_exists>no</g:identifier_exists>';
        $xml .= '<g:condition>new</g:condition>';
        $xml .= '<g:google_product_category>' . htmlspecialchars($type, ENT_XML1) . '</g:google_product_category>';
        $xml .= '<g:product_type>' . htmlspecialchars($type, ENT_XML1) . '</g:product_type>';
        $xml .= '</item>';

        return $xml;
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1 << (10 * $pow)), 2) . ' ' . $units[$pow];
    }
}