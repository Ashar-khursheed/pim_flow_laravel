<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Order;

class OutDeliveryMail extends Mailable
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
		$carrier = optional($order->shipments()->latest()->first())->carrier ?? '';
		$orderNumber = $order->order_number;
		$estimatedDelivery = optional($order->shipments()->latest()->first())->estimated_delivery_date;
		if ($estimatedDelivery) {
			$estimatedDeliveryDate = Carbon::parse($estimatedDelivery);

			$estimatedDeliveryFormatted = $estimatedDeliveryDate->isToday()
				? 'Today'
				: $estimatedDeliveryDate->translatedFormat('l, j F'); // e.g. Friday, 6 June
		} else {
			$estimatedDeliveryFormatted = 'N/A';
		}
		$paymentMethod = optional($order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery';

		$customerAddress = $order->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';
		$orderDetailUrl = url("/order-details/{$order->id}");
		$rightPngURL = $backendURL. '/right.png';

		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@thehorecastore.co':'orders@thehorecastore.com';

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'carrier' => $carrier,
			'orderNumber' => $orderNumber,
			'estimatedDeliveryFormatted' => $estimatedDeliveryFormatted,
			'paymentMethod' => $paymentMethod,

			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,
			'orderDetailUrl' => $orderDetailUrl,
			'rightPngURL' => $rightPngURL,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order #{$orderNumber} is Out for Delivery")
		->markdown('emails.orders.out-for-delivery')
		->with($params);
	}
}
