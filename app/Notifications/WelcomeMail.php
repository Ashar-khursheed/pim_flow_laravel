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
		$logoUrl = config('app.logo_url');
		$name = $notifiable->name ?? 'User';
		$websiteUrl = url("/");

		return (new MailMessage)
		->subject('Welcome Email')
		->markdown('emails.welcome', [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'websiteUrl' => $websiteUrl,
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
