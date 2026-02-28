<?php

use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Product;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Find White Chinaware ID
$cat = Category::where('name', 'LIKE', '%White Chinaware%')->first();

if (!$cat) {
    echo "Category 'White Chinaware' NOT FOUND.\n";
    exit;
}

echo "Category: " . $cat->name . " (ID: " . $cat->id . ")\n";

// 2. Get all descendant IDs
$descendants = $cat->getLeafCategories()->pluck('id')->push($cat->id);
echo "Category IDs (including descendants): " . implode(',', $descendants->toArray()) . "\n";

// 3. Count sale products in these categories
$query = Product::where('status', 'published')
    ->whereHas('productSuppliers', function ($q) {
        $q->whereNotNull('sale_price')
          ->where('sale_price', '>', 0)
          ->where('updated_at', '>=', '2026-02-05');
    })
    ->whereHas('categories', function ($q) use ($descendants) {
        $q->whereIn('category_id', $descendants);
    });

$count = $query->count();
echo "Sale Products Check: Found $count products for 'White Chinaware' tree.\n";

if ($count > 0) {
    echo "Sample Product: " . $query->first()->name . "\n";
}
