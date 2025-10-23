<?php

namespace App\Mail\Welcome;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Customer;

class PreClaimWelcomeMail extends Mailable
{
	use Queueable, SerializesModels;

	public $customer;
	public $randomPassword;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Customer $customer, $randomPassword)
	{
		$this->customer = $customer;
		$this->randomPassword = $randomPassword;
	}

	public function build()
	{
		$customer = $this->customer;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $customer->name ?? 'User';
		$email = $customer->email;
		$randomPassword = $this->randomPassword ?? 'User';
		$loginUrl = url("/login");
		$rightPngURL = $backendURL. '/right.png';
		$websiteUrl = url("/");

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE', 'SA'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'sales@thehorecastore.com',
			'UAE', 'SA'  => 'hello@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'email' => $email,
			'randomPassword' => $randomPassword,
			'rightPngURL' => $rightPngURL,
			'loginUrl' => $loginUrl,
			'websiteUrl' => $websiteUrl,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Welcome to HorecaStore. Your account is ready")
		->markdown('emails.welcome.pre-claim-welcome')
		->with($params);
	}
}
