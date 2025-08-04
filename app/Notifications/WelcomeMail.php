<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeMail extends Notification implements ShouldQueue
{
	use Queueable;

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
		$websiteUrl = url("/");
		$regionName = config('app.website') == 'UAE' ? "Middle East’s":"America’s";
		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

		return (new MailMessage)
		->subject("Welcome to HorecaStore — Let’s Bring Your Dream to Life")
		->markdown('emails.welcome', [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'websiteUrl' => $websiteUrl,
			'regionName' => $regionName,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		]);
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
