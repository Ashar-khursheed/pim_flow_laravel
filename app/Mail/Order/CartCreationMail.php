<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Helpers\CurrencyConverter;

use App\Models\FrontEnd\CustomerCart;

class CartCreationMail extends Mailable
{
	use Queueable, SerializesModels;

	public $customerCart;
	public $randomPassword;
	public $isNewCustomer;

	/**
	 * Create a new message instance.
	 */
	public function __construct(CustomerCart $customerCart, $randomPassword, $isNewCustomer)
	{
		$this->customerCart = $customerCart;
		$this->randomPassword = $randomPassword;
		$this->isNewCustomer = $isNewCustomer;
	}

	/**
	 * Get pricing breakdown variables
	 */
	private function getPricingBreakdown($products, $currencyConversionRate)
	{
		$sourceCurrencyTitle = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : 'USD';
		$customerCart = $this->customerCart;

		/* Total price before discount (raw value) */
		$totalPriceWithoutDiscount = $products->sum(function ($p) {
			return (float) $p->priceBeforeDiscount * $p->quantity;
		});

		/* Total saved = original total - actual subtotal */
		$totalSaved = max(0, ($totalPriceWithoutDiscount ?? 0) - ($customerCart->amount ?? 0));

		/* Charges */
		$liftGateCharge = $customerCart->is_lift_gate ? 75 : 0;
		$residentialAddressCharge = $customerCart->is_residential_address ? 199 : 0;
		$insideDeliveryCharge = $customerCart->is_inside_delivery ? 249 : 0;

		/* Discounts & Amounts */
		$subTotal = $customerCart->amount ?? 0;

		/* Not Exist */
		$discount = $customerCart->discount ?? 0;
		$additionalDiscountAmount = $customerCart->additional_discount_amount ?? 0;
		$additionalDiscountReason = $order->additional_discount_reason ?? null;
		$additionalDiscountPercentage = $customerCart->additional_discount_percentage ?? 0;
		$chequeDiscount = $customerCart->cheque_discount ?? 0;
		$chequeDiscountPercentage = $customerCart->cheque_discount_percentage ?? 0;
		/* Not Exist */

		/* Tax */
		$taxName = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'VAT' : 'SALES TAX';
		$taxPercent = ($customerCart->tax_percentage ?? 0) + 0;
		$taxAmount = $customerCart->tax_amount ?? 0;

		/* Shipping & Total */
		$shippingCharge = $customerCart->shipping_charge ?? 0;
		$total = $customerCart->total_amount ?? 0;

		/* Amount Before Tax */
		$amountBeforeTax = $subTotal - $discount - $chequeDiscount - $additionalDiscountAmount + $liftGateCharge + $residentialAddressCharge + $insideDeliveryCharge + (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 0 : $shippingCharge);

		return [
			'totalSaved' => $totalSaved * $currencyConversionRate,
			'subTotal' => $subTotal * $currencyConversionRate,
			'discount' => $discount * $currencyConversionRate,
			'chequeDiscount' => $chequeDiscount,
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

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $customerCart->customer->name ?? 'User';

		$username = $customerCart->customer->email;
		$isNewCustomer = $this->isNewCustomer;
		$password = $this->randomPassword;

		$paymentMode = 'online';
		$paymentType = 'online';
		$paymentUrl = url("/login-source");

		$referenceNumber = $customerCart->reference_number;
		$createdAt = Carbon::parse($customerCart->created_at)->format('D, M d, Y');

		$customerAddress = $customerCart->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';

		/* Currency */
		$sourceCurrencySymbol = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$';
		$sourceCurrencyTitle = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : 'USD';
		$currency = $customerAddress->relatedCountry->currency->symbol ?? $sourceCurrencySymbol;
		$targetCurrencyTitle = $customerAddress->relatedCountry->currency->title ?? $sourceCurrencyTitle;

		$currencyConversionRate = CurrencyConverter::getRate($sourceCurrencyTitle, $targetCurrencyTitle);

		$products = collect();

		foreach ($customerCart->customerCartProducts as $customerCartProduct) {
			$productSupplierDetail = $customerCartProduct->vendorProductSupplier;
			$productDetail = $customerCartProduct->product;

			if ($productDetail) {
				$product = new \stdClass();

				$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;
				$product->name = $productDetail->name;
				$product->delivery_days = $productSupplierDetail
				? $productSupplierDetail->delivery_days
				: null;

				/* Original Price (before discount) */
				$originalPrice = ($productSupplierDetail->price ?? $customerCartProduct->unit_price) * $currencyConversionRate;

				$product->priceBeforeDiscount = $originalPrice;
				$product->unitPrice = $customerCartProduct->unit_price * $currencyConversionRate;

				if (
					$productSupplierDetail &&
					$productSupplierDetail->price > $customerCartProduct->unit_price &&
					$productSupplierDetail->price > 0 &&
					$customerCartProduct->unit_price > 0
				) {
					$product->discount = ((($productSupplierDetail->price - $customerCartProduct->unit_price) / $productSupplierDetail->price) * 100) * $currencyConversionRate;

				} else {
					$product->discount = 0;
				}

				$product->quantity = (int) $customerCartProduct->quantity;
				$product->total = $customerCartProduct->amount * $currencyConversionRate;

				$products->push($product);
			}
		}

		/* Get pricing breakdown variables */
		$pricingBreakdown = $this->getPricingBreakdown($products, $currencyConversionRate);

		/* Additional charges (if exists) */
		$additionalAmountName = $customerCart->additional_amount_name;
		$additionalAmountPrice = $customerCart->additional_amount_price;

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

			/* Merge pricing breakdown variables */
			'currency' => $currency,
			...$pricingBreakdown,

			'additionalAmountName' => $additionalAmountName,
			'additionalAmountPrice' => $additionalAmountPrice * $currencyConversionRate,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order Ref {$referenceNumber} is Reserved — Awaiting Payment")
		->markdown('emails.orders.cart-creation')
		->with($params);
	}
}
