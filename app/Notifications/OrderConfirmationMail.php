<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

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
		$logoUrl = config('app.logo_url');
		$name = $notifiable->name ?? 'User';
		$orderNumber = $this->order->order_number;
		$orderUrl = url("/registration/all-orders");
		$siteName = env('APP_WEBSITE') == 'UAE' ? 'UAE':'USA';
		$siteContact = env('APP_WEBSITE') == 'UAE' ? '800 Horeca (467322)':'866-4HORECA';
		$siteEmail = env('APP_WEBSITE') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderNumber' => $orderNumber,
			'orderUrl' => $orderUrl,
			'siteName' => $siteName,
			'siteContact' => $siteContact,
			'siteEmail' => $siteEmail,
		];

		return (new MailMessage)
		->subject('Your Horeca Order is Confirmed')
		->markdown('emails.order-confirmed', $params);
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
