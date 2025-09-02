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
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $customer->name ?? 'User';
		$email = $customer->email;
		$randomPassword = $this->randomPassword ?? 'User';
		$loginUrl = url("/login");
		$rightPngURL = $backendURL. '/right.png';
		$websiteUrl = url("/");
		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@thehorecastore.co':'sales@thehorecastore.com';

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
