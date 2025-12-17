<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\SeoManagement;
use App\Models\Attribute;
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

        $totalProducts = Product::where('status', 'published')->count();
        $this->info('⚙️  Processing ' . number_format($totalProducts) . ' products...');
        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

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
            'stock_status', 'barcode', 'benefits_features'
        ])
        ->where('status', 'published')
        ->orderBy('id', 'desc')
        ->chunk(200, function ($products) use ($handle, $bar) {
            foreach ($products as $product) {
                fwrite($handle, $this->mapProductToXml($product));
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

    private function mapProductToXml($product)
    {
        // Get the first supplier for price info
        $firstSupplier = $product->productSuppliers->first();
        $price = $firstSupplier->price ?? 0;
        $salePrice = $firstSupplier->sale_price ?? 0;

        // Decode description safely
        $descriptionText = '';
        if (is_string($product->description)) {
            $decoded = json_decode($product->description, true);
            $descriptionText = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? implode(' ', array_filter($decoded))
                : $product->description;
        }
        $descriptionText = preg_replace('/\s+/', ' ', trim(strip_tags(html_entity_decode($descriptionText))));

        // SEO data
        $seoData = SeoManagement::where('relational_id', $product->id)
            ->select('url', 'meta_title', 'meta_description')
            ->first();

        // Product images
        $image = null;
        if ($imageUrls = json_decode($product->images, true)) {
            $image = $imageUrls[0] ?? null;
        }

        // Product categories
        $currentCategory = $product->categories->first();
        $parentCategory = $currentCategory?->parent;
        $fullSlug = $product->parent_category_url() . '/' . $product->category_url() . '/' . ($product->seoProductUrl->url ?? "");

        $product_type = '';
        $google_product_category = '';
        if ($parentCategory && $currentCategory) {
            $product_type = $parentCategory->name . ' > ' . $currentCategory->name;
            $google_product_category = $product_type;
        } elseif ($currentCategory) {
            $product_type = $currentCategory->name;
            $google_product_category = $currentCategory->name;
        }

        // Product attributes
        $attributes = ProductAttribute::join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
            ->where('product_id', $product->id)
            ->select('attributes.name as attribute_name', 'product_attributes.attribute_value')
            ->get()
            ->map(fn($attr) => [
                'attribute_name' => $attr->attribute_name,
                'attribute_value' => $attr->attribute_value,
            ])
            ->toArray();

        // Variants
        $productVariants = $product->productVariants->map(function ($variant) {
            $childIds = json_decode($variant->child_ids, true) ?? [];
            $variants = json_decode($variant->variants, true) ?? [];
            if (empty($childIds) || empty($variants)) {
                return [];
            }

            $children = Product::whereIn('id', $childIds)->select('id')->get();
            $attributeIds = array_column($variants, 'attribute_id');
            $attributes = Attribute::whereIn('id', $attributeIds)->pluck('name', 'id');
            $productAttributes = ProductAttribute::whereIn('product_id', $childIds)
                ->whereIn('attribute_id', $attributeIds)
                ->get()
                ->groupBy('product_id');

            $result = [];
            foreach ($variants as $v) {
                $attributeId = $v['attribute_id'];
                $attributeName = $attributes[$attributeId] ?? null;
                if (!$attributeName) continue;

                $seenAttributeValues = [];
                foreach ($children as $child) {
                    $attrValue = $productAttributes->get($child->id)?->firstWhere('attribute_id', $attributeId)?->attribute_value ?? null;
                    if (empty($attrValue) || isset($seenAttributeValues[$attrValue])) continue;
                    $seenAttributeValues[$attrValue] = true;

                    $result[] = [
                        'attribute_id' => $attributeId,
                        'attribute_name' => $attributeName,
                        'label' => $v['labels'] ?? $attributeName,
                        'type' => $v['type'] ?? 'dropdown',
                        'attrValue' => $attrValue ?? '',
                    ];
                }
            }

            return $result;
        })->flatten(1)->values();

        // Start XML for this product
        $xml = '<item>';
        $xml .= '<g:id>' . $product->id . '</g:id>';
        $xml .= '<g:sku>' . htmlspecialchars($product->sku ?? '') . '</g:sku>';
        $xml .= '<g:barcode>' . htmlspecialchars($product->barcode ?? '') . '</g:barcode>';
        $xml .= '<g:title>' . htmlspecialchars($product?->name ?? $product->name) . '</g:title>';
        $xml .= '<g:link>' . config('app.url') . '/' . $fullSlug . '</g:link>';
        $xml .= '<g:description>' . htmlspecialchars($descriptionText) . '</g:description>';
        $xml .= '<g:price>' . number_format($price, 2) . '</g:price>';
        $xml .= '<g:sale_price>' . number_format($salePrice, 2) . '</g:sale_price>';
        $xml .= '<g:availability>' . $product->stock_status . '</g:availability>';
        $xml .= '<g:brand>' . htmlspecialchars($product->brand?->name ?? '') . '</g:brand>';
        $xml .= '<g:gtin> ' . htmlspecialchars($product->barcode ?? '') . '</g:gtin>';
        $xml .= '<g:mpn>' . $product->sku . '</g:mpn>';

        $xml .= '<g:material>' . htmlspecialchars($parentCategory->name ?? '') . '</g:material>';
        if ($image) {
            $xml .= '<g:image_link>' . htmlspecialchars($image) . '</g:image_link>';
        }

        // Product attributes
        foreach ($attributes as $attr) {
            $xml .= '<g:product_detail>';
            $xml .= '<g:section_name> Key Specification </g:section_name>';
            $xml .= '<g:attribute_name>' . htmlspecialchars($attr['attribute_name']) . '</g:attribute_name>';
            $xml .= '<g:attribute_value>' . htmlspecialchars($attr['attribute_value']) . '</g:attribute_value>';
            $xml .= '</g:product_detail>';
        }

        // Product variants
        foreach ($productVariants as $highlight) {
            $xml .= '<g:product_highlight>' . htmlspecialchars($highlight['attribute_name']) . ' : ' . htmlspecialchars($highlight['attrValue']) . '</g:product_highlight>';
        }

        if ($product->benefits_features) {
            $benefits = is_array($product->benefits_features) ? $product->benefits_features : json_decode($product->benefits_features, true) ?? [];
            foreach ($benefits as $features) {
                $xml .= '<g:product_highlight>' . htmlspecialchars($features['benefit']) . ' : ' . htmlspecialchars($features['feature']) . '</g:product_highlight>';
            }
        }

        $xml .= '<g:store_code> </g:store_code>';
        $xml .= '<g:identifier_exists>no</g:identifier_exists>';
        $xml .= '<g:condition>new</g:condition>';
        $xml .= '<g:google_product_category>' . htmlspecialchars($google_product_category) . '</g:google_product_category>';
        $xml .= '<g:sale_price_effective_date></g:sale_price_effective_date>';
        $xml .= '<g:product_type>' . htmlspecialchars($product_type) . '</g:product_type>';
        $xml .= '</item>';

        return $xml;
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