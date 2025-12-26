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
		$currency = match (config('app.website')) {
			'UAE', 'UAE_T' => 'AED',
			'US', 'US_T' => '$',
			'SA' => 'SAR',
			default => '$',
		};

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
		$insideDeliveryCharge = $customerCart->is_inside_delivery ? 249 : 0;
		$additionalAmountName = $customerCart->additional_amount_name;
		$additionalAmountPrice = $customerCart->additional_amount_price;

		$subTotal = $customerCart->amount ?? 0;
		$shippingCharge = $customerCart->shipping_charge ?? 0;
		$taxName = in_array(config('app.website'), ['UAE', 'UAE_T', 'SA']) ? 'VAT' : 'SALES TAX';
		$taxPercent = $customerCart->tax_percentage;
		$taxPercent = $taxPercent + 0;
		$taxAmount = $customerCart->tax_amount ?? 0;
		$discount = 0;
		$total = $customerCart->total_amount ?? 0;

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE', 'SA'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'sales@thehorecastore.com',
			'UAE', 'SA'  => 'hello@horecastore.ae',
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
			'currency' => $currency,

			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,

			'products' => $products,
			'totalSaved' => $totalSaved,

			'liftGateCharge' => $liftGateCharge,
			'residentialAddressCharge' => $residentialAddressCharge,
			'insideDeliveryCharge' => $insideDeliveryCharge,
			'additionalAmountName' => $additionalAmountName,
			'additionalAmountPrice' => $additionalAmountPrice,
			'subTotal' => $subTotal,
			'shippingCharge' => $shippingCharge,
			'taxName' => $taxName,
			'taxPercent' => $taxPercent,
			'taxAmount' => $taxAmount,
			'discount' => $discount,
			'total' => $total,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order Ref {$referenceNumber} is Reserved — Awaiting Payment")
		->markdown('emails.orders.cart-creation')
		->with($params);
	}
}
