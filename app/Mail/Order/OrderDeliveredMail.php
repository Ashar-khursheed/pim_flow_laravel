<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Order;

class OrderDeliveredMail extends Mailable
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
		$orderNumber = $order->order_number;
		$currency = match (config('app.website')) {
			'UAE', 'UAE_T' => 'AED',
			'US', 'US_T' => '$',
			'SA' => 'SAR',
			default => '$',
		};

		$products = collect();
		foreach ($order->orderProducts as $orderProduct) {
			$productDetail = $orderProduct->product;
			if ($productDetail) {
				$product = new \stdClass();
				$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;
				$product->name = $productDetail->name;
				$product->quantity = (int) $orderProduct->quantity;
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
				$product->total = $orderProduct->amount;
				$products->push($product);
			}
		}

		$rightPngURL = $backendURL. '/right.png';
		$checkoutURL = url("/view-order/{$order->id}");
		$orderDetailUrl = url("/view-order/{$order->id}");

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
			'orderNumber' => $orderNumber,
			'currency' => $currency,
			'products' => $products,
			'rightPngURL' => $rightPngURL,

			'checkoutURL' => $checkoutURL,
			'orderDetailUrl' => $orderDetailUrl,

			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order #{$orderNumber} Has Been Delivered")
		->markdown('emails.orders.order-delivered')
		->with($params);
	}
}
