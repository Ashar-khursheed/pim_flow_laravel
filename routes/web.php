<?php

use Illuminate\Support\Facades\Route;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

Route::get('/test-quote-pdf', function () {
	$quote = \App\Models\FrontEnd\Quote::with([
		'customer:id,name,email,type,country_code,mobile_number',
		'customerAddress',
		'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
		'quoteProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
		'quoteProducts.product.brand:id,name',
		'quoteProducts.product.currency:id,symbol',
		'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
		'quoteEmails',
	])->latest()->first();

	$customer = $quote->customer;

	$backendURL = config('app.backend_url');
	$pdfLogoUrl = public_path((config('app.website') == 'UAE' ? 'uae_logo.png' : 'us_logo.png'));

	$companyName = config('app.website') == 'UAE' ? 'THE HORECA STORE INC' : 'THE HORECA STORE INC';
	$street = config('app.website') == 'UAE' ? '8800 Bissonnet Street, Ste A,' : '8800 Bissonnet Street, Ste A,';
	$city = config('app.website') == 'UAE' ? 'Houston, Texas 77074' : 'Houston, Texas 77074';
	$phone = config('app.website') == 'UAE' ? '1 (866) 446-7322' : '1 (866) 446-7322';
	$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';
	$siteURL = url('/');

	$name = $customer->type === 'Private' ? $customer->name : $customer->business_name;
	$customerAddress = $quote->customerAddress;
	$address = $customerAddress->address ?? '';
	$customerCity = $customerAddress->city ?? '';
	$country = $customerAddress->country ?? '';
	$email = $customer->email ?? '';

	$createdAt = $quote->created_at->format('M d Y');
	$expiredAt = Carbon::parse($quote->expired_at)->format('M d Y');
	$quoteNumber = $quote->quote_number;
	$paymentMode = $quote->payment_terms;
	$quoteType = 'Online';
	$currency = config('app.website') == 'UAE' ? 'AED' : '$';

	$products = collect();
	foreach ($quote->quoteProducts as $index => $quoteProduct) {
		$productSupplierDetail = $quoteProduct->vendorProductSupplier;
		$productDetail = $quoteProduct->product;

		if ($productDetail) {
			$product = new \stdClass();
			$product->count = $index + 1;
			$product->name = $productDetail->name;
			$product->brandName = $productDetail->brand->name ?? null;
			$product->sku = $productDetail->sku;
			$product->warrantyInfo = $productSupplierDetail->warranty_information ?? null;
			$product->shippingCharge = $quoteProduct->shipping_charge == 0
			? 'FREE SHIPPING'
			: $currency . ' ' . number_format($quoteProduct->shipping_charge, 2, '.', ',');

			$product->deliveryDays = $productSupplierDetail->delivery_days ?? null;
			$product->productURL = url('/product/' . $productDetail->id);

			$images = is_array($productDetail->images)
			? $productDetail->images
			: (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);

			$product->image = is_array($images) ? ($images[0] ?? null) : null;

			$product->base64_image = getBase64Image($product->image);

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

	$subTotal = number_format($quote->amount ?? 0, 2, '.', ',');
	$shippingCharge = number_format($quote->shipping_charge ?? 0, 2, '.', ',');
	$taxName = config('app.website') == 'UAE' ? 'VAT' : 'Sales Tax';
	$taxPercent = $quote->tax_percentage;
	$taxAmount = number_format($quote->tax_amount ?? 0, 2, '.', ',');
	$total = number_format($quote->total_amount ?? 0, 2, '.', ',');

	$totalInWords = config('app.website') == 'UAE'
	? convertNumberToWords($total, "AED", "Fils")
	: convertNumberToWords($total, "U.S. Dollars", "Cents");

	$beneficiaryAddress = config('app.website') == 'UAE' ? '8800 BISSONNET ST STE A, HOUSTON TX 77074-2435' : '8800 BISSONNET ST STE A, HOUSTON TX 77074-2435';
	$accountNo = config('app.website') == 'UAE' ? '6130 9953 3' : '6130 9953 3';
	$bankName = config('app.website') == 'UAE' ? 'JP Morgan Chase Bank' : 'JP Morgan Chase Bank';
	$routingCode = config('app.website') == 'UAE' ? '1110 0061 4' : '1110 0061 4';

	$pdfParams = [
		'pdfLogoUrl' => $pdfLogoUrl,
		'companyName' => $companyName,
		'street' => $street,
		'city' => $city,
		'phone' => $phone,
		'siteEmail' => $siteEmail,
		'siteURL' => $siteURL,

		'name' => $name,
		'address' => $address,
		'city' => $customerCity,
		'country' => $country,
		'email' => $email,

		'createdAt' => $createdAt,
		'expiredAt' => $expiredAt,
		'quoteNumber' => $quoteNumber,
		'paymentMode' => $paymentMode,
		'quoteType' => $quoteType,
		'currency' => $currency,

		'products' => $products,

		'subTotal' => $subTotal,
		'shippingCharge' => $shippingCharge,
		'taxName' => $taxName,
		'taxPercent' => $taxPercent,
		'taxAmount' => $taxAmount,
		'total' => $total,
		'totalInWords' => $totalInWords,

		'beneficiaryAddress' => $beneficiaryAddress,
		'accountNo' => $accountNo,
		'bankName' => $bankName,
		'routingCode' => $routingCode,
	];

	return Pdf::loadView('pdf.quote1', $pdfParams)->stream();
});


Route::get('/generate-sitemap', [SitemapController::class, 'generate']);