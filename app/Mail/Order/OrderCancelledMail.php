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
		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@thehorecastore.co':'orders@thehorecastore.com';

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
