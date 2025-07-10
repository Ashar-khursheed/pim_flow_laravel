<?php

namespace App\Notifications\Orders;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class OutForDeliveryMail extends Notification implements ShouldQueue
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
		$carrier = optional($this->order->shipments()->latest()->first())->carrier ?? '';
		$orderNumber = $this->order->order_number;
		$estimatedDelivery = optional($this->order->shipments()->latest()->first())->estimated_delivery_date;
		if ($estimatedDelivery) {
			$estimatedDeliveryDate = Carbon::parse($estimatedDelivery);

			$estimatedDeliveryFormatted = $estimatedDeliveryDate->isToday()
				? 'Today'
				: $estimatedDeliveryDate->translatedFormat('l, j F'); // e.g. Friday, 6 June
		} else {
			$estimatedDeliveryFormatted = 'N/A';
		}
		$paymentMethod = optional($this->order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery';

		$customerAddress = $this->order->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';
		$orderDetailUrl = url("/order-details/{$this->order->id}");
		$rightPngURL = $backendURL. '/right.png';

		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

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

			'siteEmail' => $siteEmail,
		];

		return (new MailMessage)
		->subject('Your Horeca Order is Out for Delivery')
		->markdown('emails.orders.out-for-delivery', $params);
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
