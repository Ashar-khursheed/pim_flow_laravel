<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Order;

class OrderReservedMail extends Mailable
{
	use Queueable, SerializesModels;

	public $order;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Order $order)
	{
		$this->order = $order;
	}

	public function build()
	{
		$order = $this->order;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $order->customer->name ?? 'User';
		$username = $order->customer->email;
		$isNewCustomer = $this->isNewCustomer;
		$password = $this->randomPassword;
		$paymentUrl = $order->customer->payment_link ?? url(/);

		$referenceNumber = $order->order_number;
		$createdAt = Carbon::parse($order->created_at)->format('D, M d, Y');
		$currency = config('app.website') == 'UAE' ? 'AED' : '$';

		$customerAddress = $order->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';$products = collect();

		foreach ($order->orderProducts as $orderProduct) {
			$productSupplierDetail = $orderProduct->vendorProductSupplier;
			$productDetail = $orderProduct->product;

			if ($productDetail) {
				$product = new \stdClass();

				$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;
				$product->name = $productDetail->name;
				$product->delivery_days = $productSupplierDetail
				? $productSupplierDetail->delivery_days
				: null;

				/* Original Price (before discount) */
				$originalPrice = $productSupplierDetail->price ?? $orderProduct->unit_price;

				$product->priceBeforeDiscount = $originalPrice;
				$product->unitPrice = $orderProduct->unit_price;

				if (
					$productSupplierDetail &&
					$productSupplierDetail->price > $orderProduct->unit_price &&
					$productSupplierDetail->price > 0 &&
					$orderProduct->unit_price > 0
				) {
					$product->discount = (($productSupplierDetail->price - $orderProduct->unit_price) / $productSupplierDetail->price) * 100;

				} else {
					$product->discount = 0;
				}

				$product->quantity = (int) $orderProduct->quantity;
				$product->total = $orderProduct->amount;

				$products->push($product);
			}
		}

		/* Total price before discount (raw value) */
		$totalPriceWithoutDiscount = $products->sum(function ($p) {
			return (float) $p->priceBeforeDiscount * $p->quantity;
		});

		/* Total saved = original total - actual subtotal */
		$totalSaved = max(0, ($totalPriceWithoutDiscount ?? 0) - ($order->amount ?? 0));

		$liftGateCharge = $order->is_lift_gate ? 75 : 0;
		$residentialAddressCharge = $order->is_residential_address ? 199 : 0;

		$subTotal = $order->amount ?? 0;
		$shippingCharge = $order->shipping_charge ?? 0;
		$taxName = config('app.website') == 'UAE' ? 'VAT' : 'SALES TAX';
		$taxPercent = $order->tax_percentage;
		$taxAmount = $order->tax_amount ?? 0;
		$total = $order->total_amount ?? 0;

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'sales@thehorecastore.com',
			'UAE'  => 'hello@horecastore.ae',
			'TEST' => 'test@thehorecastore.co',
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
