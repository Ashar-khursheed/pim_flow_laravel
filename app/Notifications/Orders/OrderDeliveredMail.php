<?php

namespace App\Notifications\Orders;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDeliveredMail extends Notification implements ShouldQueue
{
	use Queueable;

	public $order;

	public function __construct($order)
	{
		$this->order = $order;
	}

	/**3
	 * Get the notification's delivery channels.
	 *
	 * @return array<int, string>
	 */
	public function via($notifiable)
	{
		return ['mail'];
	}

	/**
	 * Get the mail representation of the notification.
	 */
	public function toMail($notifiable)
	{
		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');

		$name = $notifiable->name ?? 'User';

		$products = collect();
		foreach ($this->order->orderProducts as $orderProduct) {
			$productDetail = $orderProduct->product;
			if ($productDetail) {
				$product = new \stdClass();
				$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;
				$product->name = $productDetail->name;
				$product->quantity = (int) $orderProduct->quantity;
				$products->push($product);
			}
		}

		$rightPngURL = $backendURL. '/right.png';
		$checkoutURL = url("/checkout");
		$orderDetailUrl = url("/order-details/{$this->order->id}");
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'products' => $products,
			'rightPngURL' => $rightPngURL,
			'orderDetailUrl' => $orderDetailUrl,

			'siteEmail' => $siteEmail,
		];

		return (new MailMessage)
		->subject('Your Horeca Order Has Been Delivered')
		->markdown('emails.orders.partial-order-cancelled', $params);
	}


	/**
	 * Get the array representation of the notification.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(object $notifiable): array
	{
		return [
			//
		];
	}
}
