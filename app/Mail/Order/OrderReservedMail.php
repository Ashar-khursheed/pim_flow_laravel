<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\AccessoryCharge;

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
		$logoUrl = $backendURL . '/logo.png';
		$name = $order->customer->name ?? 'User';
		$username = $order->customer->email;
		$isNewCustomer = false;
		$password = null;

		$paymentUrl = $order->payment_link ?? url("/");

		$referenceNumber = $order->order_number;
		$createdAt = Carbon::parse($order->created_at)->format('D, M d, Y');
		$currency = match (config('app.website')) {
			'UAE', 'UAE_T' => 'AED',
			'US', 'US_T' => '$',
			'SA' => 'SAR',
			default => '$',
		};

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

				$images = is_array($productDetail->images)
				? $productDetail->images
				: (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
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

				$product->accessories = [];

				$accessoryCharges = AccessoryCharge::where('relation_type', OrderProduct::class)
				->where('relation_id', $orderProduct->id)
				->get();
				if ($accessoryCharges->isNotEmpty()) {
					$product->accessories = $accessoryCharges->map(function ($charge) {
						return [
							'id' => $charge->id,
							'accessory_item_id' => $charge->accessory_item_id,
							'accessory_item_name' => $charge->accessoryItem->name ?? null,
							'accessory_item_price' => $charge->accessoryItem->price ?? null,
							'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
							'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
							'amount' => $charge->amount,
						];
					});
				}

				// ===========================
				// Apply per-product Texas shipping
				// ===========================
				$productShipping = $orderProduct->shipping_charge ?? 0;

				if (in_array(config('app.website'), ['US', 'US_T'])) {
					$state = $order->customerAddress->state ?? null;

					if (!$order->is_customer_pickup) {
						if ($state === 'Texas') {
							$productShipping = ($productShipping > 0) ? $productShipping : 99;
						} else {
							$productShipping = ($productShipping > 0) ? $productShipping : 199;
						}
					} else {
						$productShipping = 0;
					}
				}

				$product->shippingCharge = $productShipping;

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
		$insideDeliveryCharge = $order->is_inside_delivery ? 249 : 0;

		$subTotal = $order->amount ?? 0;
		$shippingCharge = $order->shipping_charge ?? 0;
		$taxName = in_array(config('app.website'), ['UAE', 'UAE_T', 'SA']) ? 'VAT' : 'SALES TAX';
		$taxPercent = $order->tax_percentage;
		$taxPercent = $taxPercent + 0;
		$taxAmount = $order->tax_amount ?? 0;
		$discount = $order->discount ?? 0;
		$total = $order->total_amount ?? 0;

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
