<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommonPasswordResetMail extends Notification implements ShouldQueue
{
	use Queueable;

	public $token;

	public function __construct($token)
	{
		$this->token = $token;
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @return array
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
		$name = $notifiable->name ?? 'Customer';

		$resetUrl = url("/reset-password?token={$this->token}&email={$notifiable->email}&type=customer");

		$frontEndUrl = config('app.url');
		$backendUrl = config('app.backend_url');

		$logoUrl = $backendUrl . (config('app.website') === 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$siteUrl = config('app.website') === 'UAE' ? 'HorecaStore.ae' : 'Thehorecastore.com';
		$siteEmail = config('app.website') === 'UAE' ? 'hello@horecastore.ae' : 'sales@thehorecastore.com';

		return (new MailMessage)
			->subject('Important: Reset Your HORECA Store Password')
			->markdown('emails.common-reset-password', [
				'name' => $name,
				'resetUrl' => $resetUrl,
				'frontEndUrl' => $frontEndUrl,
				'logoUrl' => $logoUrl,
				'siteUrl' => $siteUrl,
				'siteEmail' => $siteEmail,
			]);
	}

	/**
	 * Get the array representation of the notification.
	 *
	 * @return array
	 */
	public function toArray($notifiable): array
	{
		return [];
	}
}
