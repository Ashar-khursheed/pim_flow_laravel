<?php

namespace App\Notifications\Orders;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class OrderCancelledMail extends Notification implements ShouldQueue
{
	use Queueable;

	public $order;
	public $cancelledOrderProducts;
	public $cancellationReason;

	public function __construct($order, $cancelledOrderProducts, $cancellationReason)
	{
		$this->order = $order;
		$this->cancelledOrderProducts = $cancelledOrderProducts;
		$this->cancellationReason = $cancellationReason;
	}

	/**
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
		$logoUrl = $backendURL . (config('app.website') === 'UAE' ? '/uae_logo.png' : '/us_logo.png');

		$name = $notifiable->name ?? 'User';
		$orderNumber = $this->order->order_number;

		$checkoutUrl = url("/checkout");
		$rightPngURL = $backendURL. '/right.png';
		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderNumber' => $orderNumber,

			'checkoutUrl' => $checkoutUrl,
			'rightPngURL' => $rightPngURL,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return (new MailMessage)
		->subject("Your Order #{$orderNumber} Has Been Cancelled – We're Here to Help")
		->markdown('emails.orders.order-cancelled', $params);
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
