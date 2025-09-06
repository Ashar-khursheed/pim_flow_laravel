<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Order;

class OrderCancelledMail extends Mailable
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
		$logoUrl = $backendURL . (config('app.website') === 'UAE' ? '/uae_logo.png' : '/us_logo.png');

		$name = $order->customer->name ?? 'User';
		$orderNumber = $order->order_number;

		$checkoutURL = url("/view-order/{$order->id}");
		$rightPngURL = $backendURL. '/right.png';

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'orders@thehorecastore.com',
			'UAE'  => 'orders@horecastore.ae',
			'TEST' => 'test@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderNumber' => $orderNumber,

			'checkoutURL' => $checkoutURL,
			'rightPngURL' => $rightPngURL,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order #{$orderNumber} Has Been Cancelled – We're Here to Help")
		->markdown('emails.orders.order-cancelled')
		->with($params);
	}
}
