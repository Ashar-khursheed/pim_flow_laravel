<?php

use Illuminate\Support\Facades\DB;
use App\Models\Category;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$keywords = [
    'Glass Racks',
    'Salt',
    'Pepper',
    'Bread',
    'Pizza',
    'Pastry',
    'Bakery',
    'Disposable',
    'Napkins'
];

echo "Searching for category matches...\n";

foreach ($keywords as $keyword) {
    $results = Category::where('name', 'LIKE', '%' . $keyword . '%')->pluck('name');
    if ($results->isNotEmpty()) {
        echo "Matches for '$keyword':\n";
        foreach ($results as $r) {
            echo " - $r\n";
        }
    } else {
        echo "No matches for '$keyword'\n";
    }
}
