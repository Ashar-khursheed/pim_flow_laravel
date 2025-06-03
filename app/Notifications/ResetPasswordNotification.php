<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public $token;
	public $email;
	public $type;

	public function __construct($token, $email, $type = 'user')
	{
		$this->token = $token;
		$this->email = $email;
		$this->type = $type;
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
		$name = $notifiable->name ?? 'User';
		$resetUrl = url("/reset-password?token={$this->token}&email={$this->email}&type={$this->type}");

		return (new MailMessage)
		->subject('Reset Your Password')
		->markdown('emails.reset-password', [
			'name' => $name,
			'resetUrl' => $resetUrl,
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
