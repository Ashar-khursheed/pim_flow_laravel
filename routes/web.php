<?php
 
use Illuminate\Support\Facades\Route;
use Barryvdh\DomPDF\Facade\Pdf;
 use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


Route::get('/test-proxy-image', function () {
	$imageUrl = 'https://horecastore-s3-storage.s3.us-west-1.amazonaws.com/production/products/1005222_c73efdd4-db40-486b-95cd-8ef9cbf41139.webp'; // Replace with the real image URL
 
	$proxyUrl = route('proxy-image', ['url' => $imageUrl]);
 
	return view('test-proxy', compact('proxyUrl'));
});

Route::get('/proxy-image', function () {
    $url = request('url');

    try {
        $response = Http::timeout(10)->get($url);

        if ($response->successful()) {
            return response($response->body(), 200)
                ->header('Content-Type', $response->header('Content-Type'));
        }
    } catch (\Exception $e) {
        return abort(404, 'Image not found.');
    }
})->name('proxy-image');
 
Route::get('/test-quote-pdf', function () {
	$quote = \App\Models\FrontEnd\Quote::with([
		'quoteProducts.product.brand',
		'quoteProducts.product.currency',
		'customerAddress',
	])->latest()->first();
 
	// Simulate the same notification logic
	$products = collect();
	$currency = config('app.website') == 'UAE' ? 'AED' : '$';
 
	foreach ($quote->quoteProducts as $index => $quoteProduct) {
		$productDetail = $quoteProduct->product;
		$supplier = $quoteProduct->vendorProductSupplier;
 
		if ($productDetail) {
			$product = new \stdClass();
			$product->count = $index + 1;
			$product->name = $productDetail->name;
			$product->brandName = $productDetail->brand->name ?? null;
			$product->sku = $productDetail->sku;
			$product->warrantyInfo = $supplier->warranty_information ?? null;
            $product->deliveryDays = $supplier->delivery_days ?? null;
			$product->shippingCharge = $quoteProduct->shipping_charge == 0
			? 'FREE SHIPPING'
			: $currency . ' ' . number_format($quoteProduct->shipping_charge, 2, '.', ',');
 
			$product->productURL = url('/product/' . $productDetail->id);
 
 
			$images = is_array($productDetail->images)
			? $productDetail->images
			: (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
 
			$product->image = is_array($images) ? ($images[0] ?? null) : null;
			// $product->proxyUrl = $product->image ? route('proxy-image', ['url' => $product->image]) : null;
 
         if ($product->image) {
            try {
                // Create temp directory if it doesn't exist
                if (!Storage::disk('public')->exists('temp')) {
                    Storage::disk('public')->makeDirectory('temp');
                }
                
                $imageContent = file_get_contents($product->image);
                $filename = basename(parse_url($product->image, PHP_URL_PATH));
                
                if (empty(pathinfo($filename)['extension'])) {
                    $filename .= '.webp';
                }
                
                Storage::disk('public')->put('temp/' . $filename, $imageContent);
                
                // Use the correct path - storage_path instead of public_path
                $product->localImagePath = storage_path('app/public/temp/' . $filename);
                
                // Debug: Check if file was created
                Log::info('Image saved to: ' . $product->localImagePath);
                Log::info('File exists: ' . (file_exists($product->localImagePath) ? 'Yes' : 'No'));
                
            } catch (\Exception $e) {
                Log::error('Failed to download image: ' . $e->getMessage());
                $product->localImagePath = null;
            }
        } else {
            $product->localImagePath = null;
        }

			$product->quantity = (int) $quoteProduct->quantity;
 
			$fullValue = $productDetail->sellingUnitAttribute->attribute_value ?? '';
			$product->sellingType = $productDetail->sellingUnitAttribute && $fullValue
			? (strpos($fullValue, '/') !== false
				? trim(explode('/', $fullValue)[1])
				: trim($fullValue))
			: '';
 
			$product->unitPrice = number_format($quoteProduct->unit_price, 2, '.', ',');
			$product->total = number_format($quoteProduct->amount, 2, '.', ',');
 
			$products->push($product);
		}
	}
 
	$pdfParams = [
		'logoUrl' => config('app.backend_url') . '/uae_logo.png',
		'companyName' => 'THE HORECA STORE INC',
		'street' => '8800 Bissonnet Street, Ste A,',
		'city' => 'Houston, Texas 77074',
		'phone' => '1 (866) 446-7322',
		'siteEmail' => 'hello@horecastore.ae',
		'siteURL' => url('/'),
 
		'name' => 'Test Customer',
		'address' => $quote->customerAddress->address ?? '',
		'city' => $quote->customerAddress->city ?? '',
		'country' => $quote->customerAddress->country ?? '',
		'email' => 'test@example.com',
 
		'createdAt' => now()->format('M d Y'),
		'expiredAt' => now()->addDays(7)->format('M d Y'),
		'quoteNumber' => $quote->quote_number,
		'paymentMode' => 'Net 30',
		'quoteType' => 'Online',
		'currency' => $currency,
 
		'products' => $products,
		'subTotal' => number_format($quote->amount ?? 0, 2),
		'shippingCharge' => number_format($quote->shipping_charge ?? 0, 2),
		'taxName' => 'VAT',
		'taxPercent' => $quote->tax_percentage,
		'taxAmount' => number_format($quote->tax_amount ?? 0, 2),
		'total' => number_format($quote->total_amount ?? 0, 2),
		'totalInWords' => 'Sample in words',
 
		'beneficiaryAddress' => '8800 BISSONNET ST STE A, HOUSTON TX 77074-2435',
		'accountNo' => '6130 9953 3',
		'bankName' => 'JP Morgan Chase Bank',
		'routingCode' => '1110 0061 4',
	];
 
	return Pdf::loadView('pdf.quote', $pdfParams)->stream();
});