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
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');

		$name = $order->customer->name ?? 'User';
		$orderNumber = $order->order_number;
		$currency = config('app.website') == 'UAE' ? 'AED' : '$';

		$products = collect();
		foreach ($order->orderProducts as $orderProduct) {
			$productDetail = $orderProduct->product;
			if ($productDetail) {
				$product = new \stdClass();
				$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;
				$product->name = $productDetail->name;
				$product->quantity = (int) $orderProduct->quantity;
				$product->total = $orderProduct->amount;
				$products->push($product);
			}
		}

		$rightPngURL = $backendURL. '/right.png';
		$checkoutURL = url("/view-order/{$order->id}");
		$orderDetailUrl = url("/order-details/{$order->id}");
		$siteEmail = config('app.website') == 'UAE' ? 'hello@thehorecastore.co':'sales@thehorecastore.com';

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
