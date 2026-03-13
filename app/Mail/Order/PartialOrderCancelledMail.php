<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\OrderProduct;

class PartialOrderCancelledMail extends Mailable
{
	use Queueable, SerializesModels;

	public $orderProduct;
	public $reason;

	/**
	 * Create a new message instance.
	 */
	public function __construct(OrderProduct $orderProduct, $reason)
	{
		$this->orderProduct = $orderProduct;
		$this->reason = $reason;
	}

	public function build()
	{
		$orderProduct = $this->orderProduct;
		$order = $orderProduct->order;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';

		$name = $notifiable->name ?? 'User';
		$orderNumber = $order->order_number;
		$orderDate = Carbon::parse($order->created_at)->format('D, M d, Y');
		$paidAmount = $order->paid_amount ?? 0;
		$paymentMethod = optional($order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery';

		/* Currency */
		$baseCurrency = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$';
		$currency = $order->customerAddress->relatedCountry->currency->symbol ?? $baseCurrency;

		$cancelledItems = collect();

		$productModel = $orderProduct->product;
		if ($productModel) {
			$item = new \stdClass();

			$images = is_array($productModel->images)
			? $productModel->images
			: (is_array($decoded = json_decode($productModel->images, true)) ? $decoded : null);

			$item->image = is_array($images) ? ($images[0] ?? null) : null;
			$item->name = $productModel->name;
			$item->quantity = (int) $orderProduct->quantity;
			$item->reason = $this->reason;

			$cancelledItems->push($item);
		}

		$pendingItems = collect();
		foreach ($order->orderProducts as $orderItem) {
			/* Skip cancelled items */
			if ($orderItem->id == $orderProduct->id) {
				continue;
			}

			/* Skip delivered or completed products */
			if (in_array($orderItem->status, ['Delivered', 'Partially Delivered', 'Completed'])) {
				continue;
			}

			$productModel = $orderItem->product;
			$supplier = $orderItem->vendorProductSupplier;

			if ($productModel) {
				$item = new \stdClass();

				$images = is_array($productModel->images)
				? $productModel->images
				: (is_array($decoded = json_decode($productModel->images, true)) ? $decoded : null);

				$item->image = is_array($images) ? ($images[0] ?? null) : null;
				$item->name = $productModel->name;
				$item->quantity = (int) $orderItem->quantity;
				$item->expectedDelivery = $supplier
				? getDateRange($order->created_at, $supplier->delivery_days)
				: null;

				$pendingItems->push($item);
			}
		}

		$orderListUrl = url("/my-order");
		$checkoutURL = url("/view-order/{$order->id}");

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

			'orderNumber' => $orderNumber,
			'orderDate' => $orderDate,
			'currency' => $currency,
			'paidAmount' => $paidAmount,
			'paymentMethod' => $paymentMethod,

			'cancelledItems' => $cancelledItems,
			'pendingItems' => $pendingItems,

			'orderUrl' => $orderListUrl,
			'checkoutURL' => $checkoutURL,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Some Products from Your HorecaStore Order #{$orderNumber} Have Been Cancelled")
		->markdown('emails.orders.partial-order-cancelled')
		->with($params);
	}
}
