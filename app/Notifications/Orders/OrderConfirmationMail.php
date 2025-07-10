<?php

namespace App\Notifications\Orders;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmationMail extends Notification implements ShouldQueue
{
	use Queueable;

	public $order;

	public function __construct($order)
	{
		$this->order = $order;
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
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $notifiable->name ?? 'User';
		$orderNumber = $this->order->order_number;

		$rightPngURL = $backendURL. '/right.png';

		$orderUrl = url("/registration/all-orders");
		$siteName = config('app.website') == 'UAE' ? 'UAE':'USA';
		$siteTollFreeContact = config('app.website') == 'UAE' ? '800 - HORECA (467-322)':'866-4 HORECA';
		$siteInternationalContact = config('app.website') == 'UAE' ? '+971 4 224 5818':'';
		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderNumber' => $orderNumber,

			'rightPngURL' => $rightPngURL,

			'orderUrl' => $orderUrl,
			'siteName' => $siteName,
			'siteTollFreeContact' => $siteTollFreeContact,
			'siteInternationalContact' => $siteInternationalContact,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return (new MailMessage)
		->subject('Your Horeca Order is Confirmed')
		->markdown('emails.orders.order-confirmed', $params);
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
