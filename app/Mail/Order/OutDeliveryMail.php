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
		$logoUrl = $backendURL . '/logo.png';
		$name = $order->customer->name ?? 'User';
		$customerEmail = $order->customer->email ?? 'User';
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
		$orderDetailUrl = url("/track-order/{$order->id}");
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
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

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
			'customerEmail' => $customerEmail,
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
