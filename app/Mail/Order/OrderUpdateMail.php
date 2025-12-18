<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\AccessoryCharge;

class OrderUpdateMail extends Mailable
{
	use Queueable, SerializesModels;

	public $order;
	public $originalTotalAmount;
	public $updateReason;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Order $order, $originalTotalAmount, $updateReason)
	{
		$this->order = $order;
		$this->originalTotalAmount = $originalTotalAmount;
		$this->updateReason = $updateReason;
	}

	public function build()
	{
		$order = $this->order;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $order->customer->name ?? 'User';

		$currency = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$';
		$originalTotalAmount = $this->originalTotalAmount;
		$total = $order->total_amount ?? 0;
		$paidAmount = $order->paid_amount ?? 0;
		$pendingAmount = $order->pending_amount ?? 0;
		$updateReason = $this->updateReason;

		$paymentUrl = $order->payment_link ?? url("/");
		$orderNumber = $order->order_number;
		$orderDate = Carbon::parse($order->created_at)->format('D, M d, Y');

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
		$additionalAmountName = $order->additional_amount_name;
		$additionalAmountPrice = $order->additional_amount_price;

		$subTotal = $order->amount ?? 0;
		$shippingCharge = $order->shipping_charge ?? 0;
		$taxName = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'VAT' : 'SALES TAX';
		$taxPercent = $order->tax_percentage;
		$taxPercent = $taxPercent + 0;
		$taxAmount = $order->tax_amount ?? 0;
		$discount = $order->discount ?? 0;
		$additionalDiscount = $order->additional_discount ?? 0;

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

			'currency' => $currency,
			'originalTotalAmount' => $originalTotalAmount,
			'total' => $total,
			'paidAmount' => $paidAmount,
			'pendingAmount' => $pendingAmount,
			'updateReason' => $updateReason,

			'paymentUrl' => $paymentUrl,
			'orderNumber' => $orderNumber,
			'orderDate' => $orderDate,

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
			'additionalAmountName' => $additionalAmountName,
			'additionalAmountPrice' => $additionalAmountPrice,

			'subTotal' => $subTotal,
			'shippingCharge' => $shippingCharge,
			'taxName' => $taxName,
			'taxPercent' => $taxPercent,
			'taxAmount' => $taxAmount,
			'discount' => $discount,
			'additionalDiscount' => $additionalDiscount,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		/* Determine email subject and template based on pending amount */
		if ($pendingAmount > 0) {
			/* Customer needs to pay remaining amount */
			$subject = "Update on Your HorecaStore Order #{$orderNumber} – Action Required";
			$bladeName = "order-update-pending";
		} elseif ($pendingAmount < 0) {
			/* Customer will receive a refund */
			$subject = "Update on Your HorecaStore Order #{$orderNumber} – Refund Processing";
			$bladeName = "order-update-refund";
		} elseif ($pendingAmount == 0 && $additionalDiscount > 0) {
			/* Discount applied, no payment needed */
			$subject = "Update on Your HorecaStore Order #{$orderNumber} – No Action Required";
			$bladeName = "order-update";
		} else {
			/* Default: standard order update */
			$subject = "Update on Your HorecaStore Order #{$orderNumber} – No Action Required";
			$bladeName = "order-update";
		}

		return $this->subject($subject)
		->markdown("emails.orders.{$bladeName}")
		->with($params);
	}
}
