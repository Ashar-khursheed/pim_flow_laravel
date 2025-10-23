<?php

namespace App\Mail\Welcome;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Customer;

class WelcomeMail extends Mailable
{
	use Queueable, SerializesModels;

	public $customer;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Customer $customer)
	{
		$this->customer = $customer;
	}

	public function build()
	{
		$customer = $this->customer;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $customer->name ?? 'User';
		$websiteUrl = url("/");
		$regionName = in_array(config('app.website'), ['UAE', 'UAE_T', 'SA']) ? "Middle East’s":"America’s";

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
			'websiteUrl' => $websiteUrl,
			'regionName' => $regionName,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Welcome to HorecaStore — Let’s Bring Your Dream to Life")
		->markdown('emails.welcome.welcome')
		->with($params);
	}
}
