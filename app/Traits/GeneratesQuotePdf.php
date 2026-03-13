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
		/* Load quote with ALL relationships in single optimized query */
		$quote = Quote::with([
			'customer:id,name,business_name,email,type,country_code,mobile_number',
			'customerAddress:id,address,city,country',
			'customerAddress.relatedCountry:id,name,currency_id',
			'customerAddress.relatedCountry.currency:id,symbol',
			'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
			'quoteProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			'quoteProducts.product.brand:id,name',
			'quoteProducts.product.currency:id,symbol',
			'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'quoteProducts.product.warrantyAttribute:id,product_id,attribute_value',
			'quoteProducts.product.seoProductUrl:id,relational_id,url',
			'quoteEmails',
		])->find($quoteId);

		if (!$quote) {
			throw new \Exception('Quote not found');
		}

		/* Cache commonly used values */
		$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']);
		$appUrl = config('app.url');
		$website = config('app.website');

		/* PRE-LOAD all accessory charges in ONE query */
		$quoteProductIds = $quote->quoteProducts->pluck('id')->toArray();

		$accessoryCharges = [];
		if (!empty($quoteProductIds)) {
			$accessoryCharges = AccessoryCharge::where('relation_type', QuoteProduct::class)
			->whereIn('relation_id', $quoteProductIds)
			->select('relation_id', \DB::raw('SUM(amount) as total_amount'))
			->groupBy('relation_id')
			->pluck('total_amount', 'relation_id')
			->toArray();
		}

		/* Company Info */
		$pdfLogoUrl = public_path('logo.png');
		$companyName = $isUAE ? 'HORECA TRADING CO LLC.' : 'THE HORECA STORE INC';
		$companyStreet = $isUAE ? 'Showroom 01 - Building No 9 19th Street' : '8800 Bissonnet Street, Ste A,';
		$companyCity = $isUAE ? 'Dubai - United Arab Emirates' : 'Houston, Texas 77074';
		$companyPhone = $isUAE ? '800-467-322' : '1 (866) 446-7322';

		$siteUrl = match ($website) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match ($website) {
			'US'  => 'sales@thehorecastore.com',
			'UAE'  => 'hello@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		/* Customer Info */
		$customer = $quote->customer;
		$customerAddress = $quote->customerAddress;

		$customerType = $customer->type;
		$customerBusinessName = $customer->business_name;
		$customerName = $customer->name;
		$customerAddressLine = $customerAddress->address ?? '';
		$customerCity = $customerAddress->city ?? '';
		$customerCountry = $customerAddress->country ?? '';
		$customerPhone = ($customer->country_code && $customer->mobile_number)
		? $customer->country_code . ' ' . $customer->mobile_number
		: '';
		$customerEmail = $customer->email ?? '';

		/* Quote Info */
		$createdAt = $quote->created_at->format('M d Y');
		$expiredAt = Carbon::parse($quote->expired_at)->format('M d Y');
		$quoteNumber = $quote->quote_number;
		$paymentMode = $quote->payment_terms;
		$quoteType = 'Online';

		/* Currency */
		$baseCurrency = $isUAE ? 'AED' : '$';
		$currency = $customerAddress->relatedCountry->currency->symbol ?? $baseCurrency;

		/* Process Products (optimized loop) */
		$products = $quote->quoteProducts->filter(function($quoteProduct) {
			return $quoteProduct->product !== null;
		})->map(function($quoteProduct, $index) use ($accessoryCharges, $appUrl) {
			$productDetail = $quoteProduct->product;

			/* Parse images once */
			$images = is_array($productDetail->images)
			? $productDetail->images
			: (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);

			$imageUrl = $images[0] ?? null;

			/* Selling unit */
			$fullValue = $productDetail->sellingUnitAttribute->attribute_value ?? '';
			$sellingType = '';
			if ($fullValue) {
				$sellingType = strpos($fullValue, '/') !== false
				? trim(explode('/', $fullValue)[1])
				: trim($fullValue);
			}

			/* Build product URL */
			$productUrl = $appUrl . '/' .
			$productDetail->parent_category_url() . '/' .
			$productDetail->category_url() . '/' .
			($productDetail->seoProductUrl->url ?? $productDetail->id);

			return (object) [
				'count' => $index + 1,
				'name' => $productDetail->name,
				'brandName' => $productDetail->brand->name ?? null,
				'sku' => $productDetail->sku,
				'warrantyInfo' => $productDetail->warrantyAttribute->attribute_value ?? '',
				'deliveryDays' => $quoteProduct->vendorProductSupplier->delivery_days ?? null,
				'productURL' => $productUrl,
				'image' => $imageUrl,
				'base64_image' => getBase64Image($imageUrl),
				'quantity' => (int) $quoteProduct->quantity,
				'accessoryCharge' => $accessoryCharges[$quoteProduct->id] ?? 0,
				'sellingType' => $sellingType,
				'unitPrice' => $quoteProduct->unit_price,
				'total' => $quoteProduct->amount + ($accessoryCharges[$quoteProduct->id] ?? 0),
			];
		})->values();

		/* Additional amounts and discounts */
		$additionalAmountName = $quote->additional_amount_name;
		$additionalAmountPrice = $quote->additional_amount_price;
		$subTotal = $quote->amount ?? 0;
		$discount = $quote->discount ?? 0;
		$additionalDiscountAmount = $quote->additional_discount_amount ?? 0;
		$additionalDiscountReason = $quote->additional_discount_reason ?? null;
		$additionalDiscountPercentage = $quote->additional_discount_percentage ?? 0;

		/* Charges (use ternary for clarity) */
		$liftGateCharge = $quote->is_lift_gate ? 75 : 0;
		$residentialAddressCharge = $quote->is_residential_address ? 199 : 0;
		$insideDeliveryCharge = $quote->is_inside_delivery ? 249 : 0;
		$shippingCharge = $quote->shipping_charge ?? 0;

		/* Calculate amount before tax */
		$amountBeforeTax = $subTotal
		- $discount
		- $additionalDiscountAmount
		+ $liftGateCharge
		+ $residentialAddressCharge
		+ $insideDeliveryCharge
		+ ($isUAE ? 0 : $shippingCharge);

		/* Tax info */
		$taxName = $isUAE ? 'VAT' : 'Sales Tax';
		$taxPercent = ($quote->tax_percentage ?? 0) + 0;
		$taxAmount = $quote->tax_amount ?? 0;
		$total = $quote->total_amount ?? 0;

		/* Total in words */
		$totalInWords = $isUAE
		? convertNumberToWords($total, "AED", "Fils")
		: convertNumberToWords($total, "U.S. Dollars", "Cents");

		/* URLs and payment info */
		$payNowUrl = $appUrl . '/download-quotation/' . $quote->id;

		/* Banking details */
		$beneficiaryAddress = $isUAE ? '' : '8800 BISSONNET ST STE A, HOUSTON TX 77074-2435';
		$accountNo = $isUAE ? '1015 9086 9400 1' : '6130 9953 3';
		$bankName = $isUAE ? 'Emirates NBD' : 'JP Morgan Chase Bank';
		$routingCode = $isUAE ? '' : '1110 0061 4';
		$ibanNumber = $isUAE ? 'AE48 0260 0010 1590 8694 001' : '';
		$swiftCode = $isUAE ? 'EBILAEADXX' : '';

		/* Return all parameters */
		return [
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
			'customerAddress' => $customerAddressLine,
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

			'additionalAmountName' => $additionalAmountName,
			'additionalAmountPrice' => $additionalAmountPrice,
			'subTotal' => $subTotal,
			'discount' => $discount,
			'additionalDiscountAmount' => $additionalDiscountAmount,
			'additionalDiscountReason' => $additionalDiscountReason,
			'additionalDiscountPercentage' => $additionalDiscountPercentage,
			'liftGateCharge' => $liftGateCharge,
			'residentialAddressCharge' => $residentialAddressCharge,
			'insideDeliveryCharge' => $insideDeliveryCharge,
			'shippingCharge' => $shippingCharge,
			'amountBeforeTax' => $amountBeforeTax,
			'taxName' => $taxName,
			'taxPercent' => $taxPercent,
			'taxAmount' => $taxAmount,
			'total' => $total,
			'totalInWords' => $totalInWords,
			'payNowUrl' => $payNowUrl,

			'siteName' => $website,
			'beneficiaryAddress' => $beneficiaryAddress,
			'accountNo' => $accountNo,
			'bankName' => $bankName,
			'routingCode' => $routingCode,
			'ibanNumber' => $ibanNumber,
			'swiftCode' => $swiftCode,
		];
	}
}
