<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Customer;

class CommonPasswordResetMail extends Mailable
{
	use Queueable, SerializesModels;

	public $customer;
	public $token;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Customer $customer, $token)
	{
		$this->customer = $customer;
		$this->token = $token;
	}

	public function build()
	{
		$customer = $this->customer;

		$name = $customer->name ?? 'Customer';

		$resetUrl = url("/reset-password?token={$this->token}&email={$customer->email}&type=customer");

		$frontEndUrl = config('app.url');
		$backendUrl = config('app.backend_url');

		$logoUrl = $backendUrl . (config('app.website') === 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$siteUrl = config('app.website') === 'UAE' ? 'HorecaStore.ae' : 'Thehorecastore.com';
		$siteEmail = config('app.website') === 'UAE' ? 'hello@horecastore.ae' : 'sales@thehorecastore.com';

		$params = [
			'name' => $name,
			'resetUrl' => $resetUrl,
			'frontEndUrl' => $frontEndUrl,
			'logoUrl' => $logoUrl,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject('Important: Reset Your HORECA Store Password')
		->markdown('emails.auth.common-reset-password')
		->with($params);
	}
}
