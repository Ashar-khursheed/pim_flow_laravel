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
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $customer->name ?? 'User';
		$randomPassword = $this->randomPassword ?? 'User';
		$resetPasswordUrl = url("/");
		$websiteUrl = url("/");
		$regionName = config('app.website') == 'UAE' ? "Middle East’s":"America’s";
		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@thehorecastore.co':'sales@thehorecastore.com';

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
