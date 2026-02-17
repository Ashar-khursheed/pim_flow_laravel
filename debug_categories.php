<?php

use Illuminate\Support\Facades\DB;
use App\Models\Category;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $categories = Category::pluck('name')->toArray();
    echo json_encode($categories);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
