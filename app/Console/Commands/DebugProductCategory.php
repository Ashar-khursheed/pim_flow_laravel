<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Category;

class DebugProductCategory extends Command
{
    protected $signature = 'debug:product-category {sku}';
    protected $description = 'Debug product category logic';

    public function handle()
    {
        $sku = $this->argument('sku');
        $product = Product::where('sku', $sku)->first();

        if (!$product) {
            $this->error("Product not found");
            return;
        }

        $this->info("Product ID: " . $product->id);
        $this->info("Product SKU: " . $product->sku);

        $categories = $product->categories;
        $this->info("Assigned Categories:");
        foreach ($categories as $cat) {
            $hasChildren = $cat->children()->count() > 0;
            $this->info("- ID: {$cat->id}, Name: {$cat->name}, Slug: {$cat->slug}, PID: {$cat->parent_id}, HasChildren: " . ($hasChildren ? 'YES' : 'NO'));
            if ($hasChildren) {
                 $this->info("  Children count: " . $cat->children()->count());
                 foreach($cat->children as $child) {
                     $this->info("    - Child ID: {$child->id}, Name: {$child->name}");
                 }
            }
        }

        $latestChild = $product->latestChildCategory();
        $this->info("\nlatestChildCategory() returns:");
        if ($latestChild) {
            $this->info("ID: {$latestChild->id}, Name: {$latestChild->name}, Slug: {$latestChild->slug}");
             $this->info("Category URL: " . $product->category_url());
        } else {
            $this->error("NULL");
        }
        
        // Debug the query
        $query = $product->categories()->whereDoesntHave('children')->toSql();
        $this->info("\nQuery: " . $query);
    }
}
