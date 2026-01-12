<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\AccessoryCharge;

class OrderPlacedMail extends Mailable
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
		$orderUrl = url("/my-order");

		$orderNumber = $order->order_number;
		$payWithCheque = $order->pay_with_cheque;

		$checkIncomplete = $order->pay_with_cheque && $order->is_reserved;

		$orderDate = Carbon::parse($order->created_at)->format('D, M d, Y');
		$currency = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$';
		$paidAmount = $order->paid_amount ?? 0;
		// $paymentMethod = optional($order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery';
		$paymentMethod = $order->payment_mode ? $order->payment_mode : ($payWithCheque ? 'Check' : (optional($order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery'));

		$customerAddress = $order->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';
		$customerEmail = $order->customer->email;

		$products = collect();
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
				$product->expectedShippingDate = $productSupplierDetail
					? getDateRange($order->created_at, $productSupplierDetail->delivery_days)
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
				// Apply per-product Texas shippings
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
		$discount = $order->discount ?? 0;
		$additionalDiscountAmount = $order->additional_discount_amount ?? 0;
		$additionalDiscountPercentage = $order->additional_discount_percentage ?? 0;

		$chequeDiscount = $order->cheque_discount ?? 0;
		$chequeDiscountPercentage = $order->cheque_discount_percentage ?? 0;

		$taxName = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'VAT' : 'SALES TAX';
		$taxPercent = ($order->tax_percentage ?? 0) + 0;
		$taxAmount = $order->tax_amount ?? 0;

		$shippingCharge = $order->shipping_charge ?? 0;
		$total = $order->total_amount ?? 0;


		/* Amount Before Tax */
		$amountBeforeTax = $subTotal - $discount - $chequeDiscount - $additionalDiscountAmount + $liftGateCharge + $residentialAddressCharge + $insideDeliveryCharge + (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 0 : $shippingCharge);


		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'orders@thehorecastore.com',
			'UAE'  => 'orders@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderUrl' => $orderUrl,

			'orderNumber' => $orderNumber,
			'checkIncomplete' => $checkIncomplete,
			'chequeDiscount' => $chequeDiscount,
			'chequeDiscountPercentage' => $chequeDiscountPercentage,

			'orderDate' => $orderDate,
			'currency' => $currency,
			'paidAmount' => $paidAmount,
			'paymentMethod' => $paymentMethod,

			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,
			'customerEmail' => $customerEmail,

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
			'additionalDiscountAmount' => $additionalDiscountAmount,
			'additionalDiscountPercentage' => $additionalDiscountPercentage,
			'total' => $total,
  		    'amountBeforeTax' => $amountBeforeTax,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		if ($checkIncomplete) {
			$subject = "We Received Your Check Image – Your Order#{$orderNumber} Is Reserved";
		} else {
			$subject = "Your HorecaStore Order #{$orderNumber} Has Been Successfully Placed";
		}
		return $this->subject($subject)
		->markdown('emails.orders.order-placed')
		->with($params);
	}
}
