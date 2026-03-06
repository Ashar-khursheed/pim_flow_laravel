<?php

namespace App\Traits;

use App\Models\FrontEnd\Quote;
use App\Models\FrontEnd\QuoteProduct;
use App\Models\FrontEnd\AccessoryCharge;
use Carbon\Carbon;

trait GeneratesQuotePdf
{
	/**
	 * Generate quote PDF with embedded base64 images
	 *
	 * @param int|\App\Models\Quote $quoteOrId
	 * @param array $options [ 'save_to' => 'path/to/save.pdf', 'download' => true ]
	 * @return \Illuminate\Http\Response|string|null
	 */
	public function generateQuotePdfParams($quoteId)
	{
		$quote = Quote::find($quoteId);

		/* Load relationships */
		$quote->load([
			'customer:id,name,business_name,email,type,country_code,mobile_number',
			'customerAddress',
			'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
			'quoteProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			'quoteProducts.product.brand:id,name',
			'quoteProducts.product.currency:id,symbol',
			'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'quoteProducts.product.seoProductUrl:id,relational_id,url',
			'quoteEmails',
		]);

		$customer = $quote->customer;

		$backendURL = config('app.backend_url');
		$pdfLogoUrl = public_path('logo.png');

		$companyName = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'HORECA TRADING CO LLC.' : 'THE HORECA STORE INC';
		$companyStreet = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'Showroom 01 - Building No 9 19th Street' : '8800 Bissonnet Street, Ste A,';
		$companyCity = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'Dubai - United Arab Emirates' : 'Houston, Texas 77074';
		$companyPhone = in_array(config('app.website'), ['UAE', 'UAE_T']) ? '800-467-322' : '1 (866) 446-7322';

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'sales@thehorecastore.com',
			'UAE'  => 'hello@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$customerType = $customer->type;
		$customerBusinessName = $customer->business_name;
		$customerName = $customer->name;
		$customerAddressDetail = $quote->customerAddress;
		$customerAddress = $customerAddressDetail->address ?? '';
		$customerCity = $customerAddressDetail->city ?? '';
		$customerCountry = $customerAddressDetail->country ?? '';
		$customerPhone = ($customer->country_code && $customer->mobile_number) ? $customer->country_code . ' ' . $customer->mobile_number : '';
		$customerEmail = $customer->email ?? '';

		$createdAt = $quote->created_at->format('M d Y');
		$expiredAt = Carbon::parse($quote->expired_at)->format('M d Y');
		$quoteNumber = $quote->quote_number;
		$paymentMode = $quote->payment_terms;
		$quoteType = 'Online';
		$currency = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$';

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
				$product->warrantyInfo = $productDetail->warrantyAttribute->attribute_value ?? '';
				// $product->shippingCharge = $quoteProduct->shipping_charge == 0
				// ? 'FREE SHIPPING'
				// : $currency . ' ' . number_format($quoteProduct->shipping_charge, 2, '.', ',');

				$product->deliveryDays = $productSupplierDetail->delivery_days ?? null;

				$product->productURL = config('app.url') . '/' . $productDetail->parent_category_url() . '/' . $productDetail->category_url() . '/' . ($productDetail->seoProductUrl->url ?? $productDetail->id);

				$images = is_array($productDetail->images)
				? $productDetail->images
				: (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);

				$product->image = is_array($images) ? ($images[0] ?? null) : null;

				$product->base64_image = getBase64Image($product->image);
				$product->quantity = (int) $quoteProduct->quantity;

				$product->accessoryCharge = AccessoryCharge::where('relation_type', QuoteProduct::class)
				->where('relation_id', $quoteProduct->id)
				->sum('amount');

				$fullValue = $productDetail->sellingUnitAttribute->attribute_value ?? '';
				$product->sellingType = $productDetail->sellingUnitAttribute && $fullValue
				? (strpos($fullValue, '/') !== false
					? trim(explode('/', $fullValue)[1])
					: trim($fullValue))
				: '';

				$product->unitPrice = $quoteProduct->unit_price;
				// $product->total = $quoteProduct->amount;
				$product->total = $quoteProduct->amount + $product->accessoryCharge;

				$products->push($product);
			}
		}

		$subTotal = $quote->amount ?? 0;
		$shippingCharge = $quote->shipping_charge ?? 0;
		$taxName = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'VAT' : 'Sales Tax';
		$taxPercent = $quote->tax_percentage;
		$taxPercent = $taxPercent + 0;
		$taxAmount = $quote->tax_amount ?? 0;
		$discount = $quote->discount ?? 0;
		$total = $quote->total_amount ?? 0;

		$totalInWords = in_array(config('app.website'), ['UAE', 'UAE_T'])
		? convertNumberToWords($total, "AED", "Fils")
		: convertNumberToWords($total, "U.S. Dollars", "Cents");

		$payNowUrl = config('app.url') . '/download-quotation/' . $quote->id;
		$siteName = config('app.website');

		$beneficiaryAddress = in_array(config('app.website'), ['UAE', 'UAE_T']) ? '' : '8800 BISSONNET ST STE A, HOUSTON TX 77074-2435';
		$accountNo = in_array(config('app.website'), ['UAE', 'UAE_T']) ? '1015 9086 9400 1' : '6130 9953 3';
		$bankName = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'Emirates NBD' : 'JP Morgan Chase Bank';
		$routingCode = in_array(config('app.website'), ['UAE', 'UAE_T']) ? '' : '1110 0061 4';
		$ibanNumber = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AE48 0260 0010 1590 8694 001' : '';
		$swiftCode = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'EBILAEADXX' : '';

		$pdfParams = [
			'pdfLogoUrl' => $pdfLogoUrl,

			'companyName' => $companyName,
			'companyStreet' => $companyStreet,
			'companyCity' => $companyCity,
			'companyPhone' => $companyPhone,

			'siteEmail' => $siteEmail,
			'siteURL' => $siteUrl,

			'customerType' => $customerType,
			'customerBusinessName' => $customerBusinessName,
			'customerName' => $customerName,
			'customerAddress' => $customerAddress,
			'customerCity' => $customerCity,
			'customerCountry' => $customerCountry,
			'customerPhone' => $customerPhone,
			'customerEmail' => $customerEmail,

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
			'discount' => $discount,
			'total' => $total,
			'totalInWords' => $totalInWords,
			'payNowUrl' => $payNowUrl,

			'siteName' => $siteName,
			'beneficiaryAddress' => $beneficiaryAddress,
			'accountNo' => $accountNo,
			'bankName' => $bankName,
			'routingCode' => $routingCode,
			'ibanNumber' => $ibanNumber,
			'swiftCode' => $swiftCode,
		];

		return $pdfParams;
	}
}
