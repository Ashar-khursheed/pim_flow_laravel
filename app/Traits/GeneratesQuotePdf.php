<?php

namespace App\Traits;

use App\Models\FrontEnd\Quote;
use App\Models\FrontEnd\QuoteProduct;
use App\Models\FrontEnd\AccessoryCharge;
use App\Models\ProductSupplier;
use App\Helpers\CurrencyConverter;
use Carbon\Carbon;

trait GeneratesQuotePdf
{
	public function generateQuotePdfParams($quoteId)
	{
		/* Load quote with all relationships in single optimized query */
		$quote = Quote::with([
			'customer:id,name,business_name,email,type,country_code,mobile_number',
			'customerAddress:id,address,city,country',
			'customerAddress.relatedCountry:id,name,currency_id',
			'customerAddress.relatedCountry.currency:id,title,symbol,major_unit_name,minor_unit_name',
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

		/* Resolve source and target currency */
		$sourceCurrencySymbol = $isUAE ? 'AED' : '$';
		$sourceCurrencyTitle = $isUAE ? 'AED' : 'USD';
		$customerAddress = $quote->customerAddress;
		$currencyModel = $customerAddress->relatedCountry->currency ?? null;
		$currency = $currencyModel->symbol ?? $sourceCurrencySymbol;
		$targetCurrencyTitle = $currencyModel->title ?? $sourceCurrencyTitle;
		$currencyConversionRate = CurrencyConverter::getRate($sourceCurrencyTitle, $targetCurrencyTitle) ?? 1; /* Fallback to 1 if rate unavailable */

		/* Batch-fetch accessory charges grouped by quote product id */
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

		/* Batch-fetch vendor product suppliers — not a relation, so with() cannot be used */
		$allQuoteProducts = $quote->quoteProducts;
		$vendorSuppliers = collect();

		if ($allQuoteProducts->isNotEmpty()) {
			$vendorSuppliers = ProductSupplier::where(function ($query) use ($allQuoteProducts) {
				foreach ($allQuoteProducts as $quoteProduct) {
					$query->orWhere(function ($q) use ($quoteProduct) {
						$q->where('product_id', $quoteProduct->product_id)
							->where('vendor_id', $quoteProduct->vendor_id);
					});
				}
			})
			->select('id', 'product_id', 'vendor_id', 'delivery_days')
			->get()
			->keyBy(fn($item) => $item->product_id . '_' . $item->vendor_id);
		}

		/* Company info */
		$pdfLogoUrl = public_path('logo.png');
		$companyName = $isUAE ? 'HORECA TRADING CO LLC.' : 'THE HORECA STORE INC';
		$companyStreet = $isUAE ? 'Showroom 01 - Building No 9 19th Street' : '8800 Bissonnet Street, Ste A,';
		$companyCity = $isUAE ? 'Dubai - United Arab Emirates' : 'Houston, Texas 77074';
		$companyPhone = $isUAE ? '800-467-322' : '1 (866) 446-7322';

		/* Site identity based on deployment */
		$siteUrl = match ($website) {
			'UAE' => 'HorecaStore.ae',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match ($website) {
			'UAE' => 'hello@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'sales@thehorecastore.com',
		};

		/* Customer info */
		$customer = $quote->customer;
		$customerAddressLine = $customerAddress->address ?? '';
		$customerCity = $customerAddress->city ?? '';
		$customerCountry = $customerAddress->country ?? '';
		$customerPhone = ($customer->country_code && $customer->mobile_number) ? $customer->country_code . ' ' . $customer->mobile_number : '';

		/* Quote info */
		$createdAt = $quote->created_at->format('M d Y');
		$expiredAt = Carbon::parse($quote->expired_at)->format('M d Y');
		$quoteNumber = $quote->quote_number;
		$paymentMode = $quote->payment_terms;
		$quoteType = 'Online';

		/* Process products using pre-fetched suppliers — no N+1 */
		$products = $quote->quoteProducts->filter(fn($quoteProduct) => $quoteProduct->product !== null)->map(function ($quoteProduct, $index) use ($accessoryCharges, $appUrl, $vendorSuppliers, $currencyConversionRate) {
			$productDetail = $quoteProduct->product;

			/* Decode images */
			$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
			$imageUrl = $images[0] ?? null;

			/* Selling unit */
			$fullValue = $productDetail->sellingUnitAttribute->attribute_value ?? '';
			$sellingType = '';
			if ($fullValue) {
				$sellingType = strpos($fullValue, '/') !== false ? trim(explode('/', $fullValue)[1]) : trim($fullValue);
			}

			/* Build product URL */
			$productUrl = $appUrl . '/' .
				$productDetail->parent_category_url() . '/' .
				$productDetail->category_url() . '/' .
				($productDetail->seoProductUrl->url ?? $productDetail->id);

			/* Attach supplier from batch-fetched collection */
			$key = $quoteProduct->product_id . '_' . $quoteProduct->vendor_id;
			$supplier = $vendorSuppliers->get($key);

			$accessoryCharge = ($accessoryCharges[$quoteProduct->id] ?? 0) * $currencyConversionRate;

			return (object) [
				'count' => $index + 1,
				'name' => $productDetail->name,
				'brandName' => $productDetail->brand->name ?? null,
				'sku' => $productDetail->sku,
				'warrantyInfo' => $productDetail->warrantyAttribute->attribute_value ?? '',
				'deliveryDays' => $supplier->delivery_days ?? null,
				'productURL' => $productUrl,
				'image' => $imageUrl,
				'base64_image' => getBase64Image($imageUrl),
				'quantity' => (int) $quoteProduct->quantity,
				'accessoryCharge' => $accessoryCharge,
				'sellingType' => $sellingType,
				'unitPrice' => $quoteProduct->unit_price * $currencyConversionRate,
				'total' => ($quoteProduct->amount + ($accessoryCharges[$quoteProduct->id] ?? 0)) * $currencyConversionRate,
			];
		})->values();

		/* Amounts and discounts */
		$subTotal = ($quote->amount ?? 0) * $currencyConversionRate;
		$discount = ($quote->discount ?? 0) * $currencyConversionRate;
		$additionalDiscountAmount = ($quote->additional_discount_amount ?? 0) * $currencyConversionRate;
		$additionalDiscountReason = $quote->additional_discount_reason ?? null;
		$additionalDiscountPercentage = $quote->additional_discount_percentage ?? 0;
		$additionalAmountPrice = ($quote->additional_amount_price ?? 0) * $currencyConversionRate;

		/* Surcharges */
		$liftGateCharge = ($quote->is_lift_gate ? 75 : 0) * $currencyConversionRate;
		$residentialAddressCharge = ($quote->is_residential_address ? 199 : 0) * $currencyConversionRate;
		$insideDeliveryCharge = ($quote->is_inside_delivery ? 249 : 0) * $currencyConversionRate;

		$shippingChargeName = $isUAE && $customerCountry == 'United Arab Emirates' ?  'Shipping Charge' : 'Operational & Fuel Surcharge';
		$shippingCharge = ($quote->shipping_charge ?? 0) * $currencyConversionRate;

		/* Amount before tax */
		$amountBeforeTax = $subTotal - $discount - $additionalDiscountAmount + $liftGateCharge + $residentialAddressCharge + $insideDeliveryCharge + ($isUAE ? 0 : $shippingCharge);

		/* Tax info */
		$taxName = $isUAE ? 'VAT' : 'Sales Tax';
		$taxPercent = ($quote->tax_percentage ?? 0) + 0;
		$taxAmount = ($quote->tax_amount ?? 0) * $currencyConversionRate;
		$total = ($quote->total_amount ?? 0) * $currencyConversionRate;

		/* Total in words — only if currency unit names are available */
		$baseMajorUnitName = $currencyModel->major_unit_name ?? null;
		$baseMinorUnitName = $currencyModel->minor_unit_name ?? null;
		$totalInWords = ($baseMajorUnitName && $baseMinorUnitName) ? convertNumberToWords($total, $baseMajorUnitName, $baseMinorUnitName) : '';

		/* Banking details */
		$beneficiaryAddress = $isUAE ? '' : '8800 BISSONNET ST STE A, HOUSTON TX 77074-2435';
		$accountNo = $isUAE ? '1015 9086 9400 1' : '6130 9953 3';
		$bankName = $isUAE ? 'Emirates NBD' : 'JP Morgan Chase Bank';
		$routingCode = $isUAE ? '' : '1110 0061 4';
		$ibanNumber = $isUAE ? 'AE48 0260 0010 1590 8694 001' : '';
		$swiftCode = $isUAE ? 'EBILAEADXX' : '';

		return [
			'pdfLogoUrl' => $pdfLogoUrl,
			'companyName' => $companyName,
			'companyStreet' => $companyStreet,
			'companyCity' => $companyCity,
			'companyPhone' => $companyPhone,
			'siteEmail' => $siteEmail,
			'siteURL' => $siteUrl,
			'customerType' => $customer->type,
			'customerBusinessName' => $customer->business_name,
			'customerName' => $customer->name,
			'customerAddress' => $customerAddressLine,
			'customerCity' => $customerCity,
			'customerCountry' => $customerCountry,
			'customerPhone' => $customerPhone,
			'customerEmail' => $customer->email ?? '',
			'createdAt' => $createdAt,
			'expiredAt' => $expiredAt,
			'quoteNumber' => $quoteNumber,
			'paymentMode' => $paymentMode,
			'quoteType' => $quoteType,
			'currency' => $currency,
			'products' => $products,
			'additionalAmountName' => $quote->additional_amount_name,
			'additionalAmountPrice' => $additionalAmountPrice,
			'subTotal' => $subTotal,
			'discount' => $discount,
			'additionalDiscountAmount' => $additionalDiscountAmount,
			'additionalDiscountReason' => $additionalDiscountReason,
			'additionalDiscountPercentage' => $additionalDiscountPercentage,
			'liftGateCharge' => $liftGateCharge,
			'residentialAddressCharge' => $residentialAddressCharge,
			'insideDeliveryCharge' => $insideDeliveryCharge,
			'shippingChargeName' => $shippingChargeName,
			'shippingCharge' => $shippingCharge,
			'amountBeforeTax' => $amountBeforeTax,
			'taxName' => $taxName,
			'taxPercent' => $taxPercent,
			'taxAmount' => $taxAmount,
			'total' => $total,
			'totalInWords' => $totalInWords,
			'payNowUrl' => $appUrl . '/download-quotation/' . $quote->id,
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