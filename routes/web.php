<?php

use Illuminate\Support\Facades\Route;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Controllers\SitemapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\Product;
use App\Models\Category;
use App\Models\Blog;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\DocumentUploadController;

// Route::get('/{any}', [SeoController::class, 'renderWithSeo'])
//     ->where('any', '.*');

Route::get('/media/{filename}', function ($filename) {
    $path = "production/documents/{$filename}";

    if (!Storage::disk('s3')->exists($path)) {
        abort(404, 'File not found');
    }

    $fileContent = Storage::disk('s3')->get($path);
    $mimeType = Storage::disk('s3')->mimeType($path);

    return response($fileContent, 200)
        ->header('Content-Type', $mimeType)
        ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
});
use App\Http\Controllers\FrontEnd\StripeController as F_StripeController;
Route::post('/stripe/webhook', [F_StripeController::class, 'handleWebhook']) ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
Route::get('/robots.txt', function (Request $request) {
    $host = $request->getHost();

    if ($host === 'thehorecastore.co') {
        // Disallow all crawling
        return response("User-agent: *\nDisallow: /", 200)
            ->header('Content-Type', 'text/plain');
    }

    // Allow all crawling for thehorecastore.com or other domains
    return response("User-agent: *\nDisallow:", 200)
        ->header('Content-Type', 'text/plain');
});

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok'], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'db' => $e->getMessage()
        ], 500);
    }
});

Route::get('/compress-pdf', [DocumentUploadController::class, 'compress']);




