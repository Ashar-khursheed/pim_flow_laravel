<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

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
	private function getPricingBreakdown($products)
	{
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

		/* Currency */
		$currency = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$';

		return [
			'totalSaved' => $totalSaved,
			'currency' => $currency,
			'subTotal' => $subTotal,
			'discount' => $discount,
			'chequeDiscount' => $chequeDiscount,
			'chequeDiscountPercentage' => $chequeDiscountPercentage,
			'additionalDiscountAmount' => $additionalDiscountAmount,
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
		$paymentUrl = url("/login-source");

		$referenceNumber = $customerCart->reference_number;
		$createdAt = Carbon::parse($customerCart->created_at)->format('D, M d, Y');
		$currency = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$';

		$customerAddress = $customerCart->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';

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
				$originalPrice = $productSupplierDetail->price ?? $customerCartProduct->unit_price;

				$product->priceBeforeDiscount = $originalPrice;
				$product->unitPrice = $customerCartProduct->unit_price;

				if (
					$productSupplierDetail &&
					$productSupplierDetail->price > $customerCartProduct->unit_price &&
					$productSupplierDetail->price > 0 &&
					$customerCartProduct->unit_price > 0
				) {
					$product->discount = (($productSupplierDetail->price - $customerCartProduct->unit_price) / $productSupplierDetail->price) * 100;

				} else {
					$product->discount = 0;
				}

				$product->quantity = (int) $customerCartProduct->quantity;
				$product->total = $customerCartProduct->amount;

				$products->push($product);
			}
		}

		/* Get pricing breakdown variables */
		$pricingBreakdown = $this->getPricingBreakdown($products);

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
			'paymentUrl' => $paymentUrl,

			'referenceNumber' => $referenceNumber,
			'createdAt' => $createdAt,

			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,

			'products' => $products,

			/* Merge pricing breakdown variables */
			...$pricingBreakdown,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order Ref {$referenceNumber} is Reserved — Awaiting Payment")
		->markdown('emails.orders.cart-creation')
		->with($params);
	}
}
