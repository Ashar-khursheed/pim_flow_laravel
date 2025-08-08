<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Customer;

class ResetPasswordMail extends Mailable
{
	use Queueable, SerializesModels;

	public $entity;
	public $token;
	public $type;

	/**
	 * Create a new message instance.
	 */
	public function __construct($entity, $token, string $type)
	{
		$this->entity = $entity; // Can be Customer or User
		$this->token = $token;
		$this->type = $type;
	}

	/**
	 * Build the message.
	 */
	public function build()
	{
		$entity = $this->entity;
		$name = $entity->name ?? ucfirst($this->type);

		$resetUrl = url("/reset-password?token={$this->token}&email={$entity->email}&type={$this->type}");

		$frontEndUrl = config('app.url');
		$backendUrl = config('app.backend_url');

		$logoUrl = $backendUrl . (config('app.website') === 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		// $siteUrl = config('app.website') === 'UAE' ? 'HorecaStore.ae' : 'Thehorecastore.com';
		// $siteEmail = config('app.website') === 'UAE' ? 'hello@horecastore.ae' : 'sales@thehorecastore.com';

		return $this->subject('Reset Your HORECA Store Password')
			->markdown('emails.auth.reset-password')
			->with([
				'name' => $name,
				'resetUrl' => $resetUrl,
				'frontEndUrl' => $frontEndUrl,
				'logoUrl' => $logoUrl,
				// 'siteUrl' => $siteUrl,
				// 'siteEmail' => $siteEmail,
			]);
	}
}
