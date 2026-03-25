<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Helpers\CurrencyConverter;

use App\Models\FrontEnd\CustomerCart;
use App\Models\ProductSupplier;

class CartCreationMail extends Mailable
{
	use Queueable, SerializesModels;

	public $customerCart;
	public $randomPassword;
	public $isNewCustomer;

	public function __construct(CustomerCart $customerCart, $randomPassword, $isNewCustomer)
	{
		$this->customerCart = $customerCart;
		$this->randomPassword = $randomPassword;
		$this->isNewCustomer = $isNewCustomer;
	}

	private function getPricingBreakdown($products, $currencyConversionRate)
	{
		$customerCart = $this->customerCart;
		$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']);

		/* Total price before discount */
		$totalPriceWithoutDiscount = $products->sum(fn($p) => (float) $p->priceBeforeDiscount * $p->quantity);

		/* Total saved = original total - actual subtotal */
		$totalSaved = max(0, $totalPriceWithoutDiscount - ($customerCart->amount ?? 0));

		/* Surcharges */
		$liftGateCharge = $customerCart->is_lift_gate ? 75 : 0;
		$residentialAddressCharge = $customerCart->is_residential_address ? 199 : 0;
		$insideDeliveryCharge = $customerCart->is_inside_delivery ? 249 : 0;

		/* Amounts */
		$subTotal = $customerCart->amount ?? 0;
		$discount = 0;
		$additionalDiscountAmount = 0;
		$additionalDiscountReason = null;
		$additionalDiscountPercentage = 0;
		$chequeDiscount = 0;
		$chequeDiscountPercentage = 0;

		/* Tax */
		$taxName = $isUAE ? 'VAT' : 'SALES TAX';
		$taxPercent = ($customerCart->tax_percentage ?? 0) + 0;
		$taxAmount = $customerCart->tax_amount ?? 0;

		/* Shipping & Total */
		$shippingCharge = $customerCart->shipping_charge ?? 0;
		$total = $customerCart->total_amount ?? 0;

		/* Amount before tax */
		$amountBeforeTax = $subTotal - $discount - $chequeDiscount - $additionalDiscountAmount + $liftGateCharge + $residentialAddressCharge + $insideDeliveryCharge + ($isUAE ? 0 : $shippingCharge);

		return [
			'totalSaved' => $totalSaved * $currencyConversionRate,
			'subTotal' => $subTotal * $currencyConversionRate,
			'discount' => $discount * $currencyConversionRate,
			'chequeDiscount' => $chequeDiscount * $currencyConversionRate,
			'chequeDiscountPercentage' => $chequeDiscountPercentage,
			'additionalDiscountAmount' => $additionalDiscountAmount * $currencyConversionRate,
			'additionalDiscountReason' => $additionalDiscountReason,
			'additionalDiscountPercentage' => $additionalDiscountPercentage,
			'liftGateCharge' => $liftGateCharge * $currencyConversionRate,
			'residentialAddressCharge' => $residentialAddressCharge * $currencyConversionRate,
			'insideDeliveryCharge' => $insideDeliveryCharge * $currencyConversionRate,
			'shippingCharge' => $shippingCharge * $currencyConversionRate,
			'amountBeforeTax' => $amountBeforeTax * $currencyConversionRate,
			'taxName' => $taxName,
			'taxPercent' => $taxPercent,
			'taxAmount' => $taxAmount * $currencyConversionRate,
			'total' => $total * $currencyConversionRate,
		];
	}

	public function build()
	{
		$customerCart = $this->customerCart;
		$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']); /* Resolved once — used throughout */

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $customerCart->customer->name ?? 'User';
		$username = $customerCart->customer->email;
		$password = $this->randomPassword;
		$isNewCustomer = $this->isNewCustomer;

		$paymentMode = 'online';
		$paymentType = 'online';
		$paymentUrl = url('/login-source');

		$referenceNumber = $customerCart->reference_number;
		$createdAt = Carbon::parse($customerCart->created_at)->format('D, M d, Y');

		$customerAddress = $customerCart->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';

		/* Resolve source and target currency */
		$sourceCurrencySymbol = $isUAE ? 'AED' : '$';
		$sourceCurrencyTitle = $isUAE ? 'AED' : 'USD';
		$currency = $customerAddress->relatedCountry->currency->symbol ?? $sourceCurrencySymbol;
		$targetCurrencyTitle = $customerAddress->relatedCountry->currency->title ?? $sourceCurrencyTitle;
		$currencyConversionRate = CurrencyConverter::getRate($sourceCurrencyTitle, $targetCurrencyTitle) ?? 1; /* Fallback to 1 if rate unavailable */

		/* Batch-fetch vendor product suppliers — not a relation, so with() cannot be used */
		$cartProducts = $customerCart->customerCartProducts;
		$vendorSuppliers = collect();

		if ($cartProducts->isNotEmpty()) {
			$vendorSuppliers = ProductSupplier::where(function ($query) use ($cartProducts) {
				foreach ($cartProducts as $cartProduct) {
					$query->orWhere(function ($q) use ($cartProduct) {
						$q->where('product_id', $cartProduct->product_id)
						->where('vendor_id', $cartProduct->vendor_id);
					});
				}
			})
			->select('id', 'product_id', 'vendor_id', 'price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy')
			->get()
			->keyBy(fn($item) => $item->product_id . '_' . $item->vendor_id);
		}

		/* Build products collection using pre-fetched suppliers */
		$products = collect();

		foreach ($cartProducts as $cartProduct) {
			$productDetail = $cartProduct->product;
			if (!$productDetail) continue;

			/* Attach supplier from batch-fetched collection */
			$key = $cartProduct->product_id . '_' . $cartProduct->vendor_id;
			$productSupplierDetail = $vendorSuppliers->get($key);

			$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);

			/* Original price before discount */
			$originalPrice = ($productSupplierDetail->price ?? $cartProduct->unit_price) * $currencyConversionRate;

			$product = new \stdClass();
			$product->image = $images[0] ?? null;
			$product->name = $productDetail->name;
			$product->delivery_days = $productSupplierDetail->delivery_days ?? null;
			$product->priceBeforeDiscount = $originalPrice;
			$product->unitPrice = $cartProduct->unit_price * $currencyConversionRate;
			$product->quantity = (int) $cartProduct->quantity;
			$product->total = $cartProduct->amount * $currencyConversionRate;

			/* Discount percentage — only if supplier price is higher than unit price */
			$product->discount = (
				$productSupplierDetail &&
				$productSupplierDetail->price > $cartProduct->unit_price &&
				$productSupplierDetail->price > 0 &&
				$cartProduct->unit_price > 0
			) ? (($productSupplierDetail->price - $cartProduct->unit_price) / $productSupplierDetail->price) * 100 : 0;

			$products->push($product);
		}

		/* Pricing breakdown */
		$pricingBreakdown = $this->getPricingBreakdown($products, $currencyConversionRate);

		/* Site identity based on deployment */
		$siteUrl = match (config('app.website')) {
			'UAE' => 'HorecaStore.ae',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'UAE' => 'hello@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'sales@thehorecastore.com',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'isNewCustomer' => $isNewCustomer,
			'username' => $username,
			'password' => $password,
			'paymentMode' => $paymentMode,
			'paymentType' => $paymentType,
			'paymentUrl' => $paymentUrl,
			'referenceNumber' => $referenceNumber,
			'createdAt' => $createdAt,
			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,
			'products' => $products,
			'currency' => $currency,
			...$pricingBreakdown,
			'additionalAmountName' => $customerCart->additional_amount_name,
			'additionalAmountPrice' => ($customerCart->additional_amount_price ?? 0) * $currencyConversionRate,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order Ref {$referenceNumber} is Reserved — Awaiting Payment")
		->markdown('emails.orders.cart-creation')
		->with($params);
	}
}