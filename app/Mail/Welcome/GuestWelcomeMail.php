<?php

namespace App\Mail\Welcome;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Customer;

class GuestWelcomeMail extends Mailable
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
		$randomPassword = $this->randomPassword ?? 'User';
		$resetPasswordUrl = url("/");
		$websiteUrl = url("/");
		$regionName = in_array(config('app.website'), ['UAE', 'UAE_T']) ? "Middle East’s":"America’s";

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
			'logoUrl' => $logoUrl,
			'name' => $name,
			'randomPassword' => $randomPassword,
			'resetPasswordUrl' => $resetPasswordUrl,
			'websiteUrl' => $websiteUrl,
			'regionName' => $regionName,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Welcome to HorecaStore — Here's Your Login Credentials")
		->markdown('emails.welcome.guest-welcome')
		->with($params);
	}
}
