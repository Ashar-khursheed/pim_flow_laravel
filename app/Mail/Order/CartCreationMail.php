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

	/**
	 * Create a new message instance.
	 */
	public function __construct(CustomerCart $customerCart, $randomPassword)
	{
		$this->customerCart = $customerCart;
		$this->randomPassword = $randomPassword;
	}

	public function build()
	{
		$customerCart = $this->customerCart;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $customerCart->customer->name ?? 'User';
		$username = $customerCart->customer->email;
		$password = $this->randomPassword;
		$paymentUrl = url("/login-source");

		$referenceNumber = $customerCart->reference_number;
		$customerCartDate = Carbon::parse($customerCart->created_at)->format('D, M d, Y');
		$currency = config('app.website') == 'UAE' ? 'AED' : '$';

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

		/* Total price before discount (raw value) */
		$totalPriceWithoutDiscount = $products->sum(function ($p) {
			return (float) $p->priceBeforeDiscount * $p->quantity;
		});

		/* Total saved = original total - actual subtotal */
		$totalSaved = max(0, ($totalPriceWithoutDiscount ?? 0) - ($customerCart->amount ?? 0));

		$liftGateCharge = $customerCart->is_lift_gate ? 75 : 0;
		$residentialAddressCharge = $customerCart->is_residential_address ? 199 : 0;

		$subTotal = $customerCart->amount ?? 0;
		$shippingCharge = $customerCart->shipping_charge ?? 0;
		$taxName = config('app.website') == 'UAE' ? 'VAT' : 'SALES TAX';
		$taxPercent = $customerCart->tax_percentage;
		$taxAmount = $customerCart->tax_amount ?? 0;
		$total = $customerCart->total_amount ?? 0;

		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'carts@horecastore.ae':'carts@thehorecastore.com';

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'username' => $username,
			'password' => $password,
			'paymentUrl' => $paymentUrl,

			'referenceNumber' => $referenceNumber,
			'customerCartDate' => $customerCartDate,
			'currency' => $currency,

			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,

			'products' => $products,
			'totalSaved' => $totalSaved,

			'liftGateCharge' => $liftGateCharge,
			'residentialAddressCharge' => $residentialAddressCharge,
			'subTotal' => $subTotal,
			'shippingCharge' => $shippingCharge,
			'taxName' => $taxName,
			'taxPercent' => $taxPercent,
			'taxAmount' => $taxAmount,
			'total' => $total,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order Ref {$referenceNumber} is Reserved — Awaiting Payment")
		->markdown('emails.orders.cart-creation')
		->with($params);
	}
}
