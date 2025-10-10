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

		$logoUrl = $backendUrl . '/logo.png';

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'sales@thehorecastore.com',
			'UAE'  => 'hello@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$params = [
			'name' => $name,
			'resetUrl' => $resetUrl,
			'frontEndUrl' => $frontEndUrl,
			'logoUrl' => $logoUrl,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject('Important: Reset Your HorecaStore Password')
		->markdown('emails.auth.common-reset-password')
		->with($params);
	}
}
