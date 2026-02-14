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
//use App\Http\Controllers\FrontEnd\StripeController as F_StripeController;
//Route::post('/api/stripe/webhook', [F_StripeController::class, 'handleWebhook']) ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
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

Route::get('/ccavenue-proxy', function (Request $request) {
	$targetUrl = $request->query('url');

	// ✅ Security: allow only valid CCAvenue URLs
	if (!$targetUrl || !preg_match('#^https://(secure|test)\.ccavenue\.com/#i', $targetUrl)) {
		return response('Invalid or unauthorized URL', 403);
	}

	try {
		$response = Http::withHeaders([
			'User-Agent' => 'Mozilla/5.0 (ProxyBot)',
		])->get($targetUrl);

		// ✅ Return content with headers safe for iframe
		return response($response->body(), $response->status())
		->header('Content-Type', $response->header('Content-Type', 'text/html'))
		->header('Cache-Control', 'no-cache, no-store, must-revalidate')
		->header('Pragma', 'no-cache')
		->header('Expires', '0');
	} catch (\Throwable $e) {
		\Log::error('CCAvenue Proxy Error', ['error' => $e->getMessage()]);
		return response('Proxy failed: ' . $e->getMessage(), 500);
	}
});

Route::get('/frontend/data-feed.xml', function () {
    $path = storage_path('app/public/data-feed.xml');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, [
        'Content-Type' => 'application/xml',
    ]);
});

Route::get('/frontend/llms.txt', function () {
    $path = storage_path('app/public/llms.txt');

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'text/plain',
    ]);
});


Route::get('/payment/touras/redirect/{order_no}', function($orderNo) {
//copy from api response
    return view('touras-payment-form', [
        'postUrl' => 'https://uatcheckout.tourasuae.com/ms-transaction-core-1-0/paymentRedirection/checksumGatewayPage',
        'meId' => '202406130002',
        'merchantRequest' => 'bpr4vcIWm8pNDVfcI7LIeBCpiw41Tx38u/EJc3UVDofAA9pzeM7XK4uoFW7fH9+FhTRfty7Hjo1TIYChxz3/Vtz+CYMm9EmOKmCQ1rv9ATs/yszYwMzgx+5j8nIy2p3Fbcsn6GmIZHT2EivisBr6b67xdJ9sfoAa29D89KLuWs/jYmflv1QIJ6gsfkrfvff8NtGaHG0GaX4wW/BdsXoQW3l9mA1o21mVs5B2h265HlWhck1rQXfsFO+a4Sl2NSZWGCvy4kbOuZqBQQBQdySp76zaL994Zs9eKqphSFDPDUY=',
        'hash' => 'tnBW8NjOjZvn3JPT1nSjRxVdM+oK602gRjxoCetpGnyXgMDoluuy67JbfmTweBNVruA7H9ge8oiGjrgnm61ZSqosMUZsrIRkfbBYFTtjdL4=',
    ]);
});

Route::get('/chatbot-test', function () {
	return view('chatbot-test');
});
