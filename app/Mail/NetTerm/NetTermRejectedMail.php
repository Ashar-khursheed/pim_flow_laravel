<?php

namespace App\Mail\NetTerm;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Finance;

class NetTermRejectedMail extends Mailable
{
	use Queueable, SerializesModels;

	public $finance;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Finance $finance)
	{
		$this->finance = $finance;
	}

	public function build()
	{
		$finance = $this->finance;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $finance->customer->name ?? 'User';

		$siteEmail = match (config('app.website')) {
			'US'  => 'support@thehorecastore.com',
			'UAE'  => 'support@horecastore.ae',
			'US_T' => 'test_us_support@thehorecastore.co',
			'UAE_T' => 'test_uae_support@thehorecastore.co',
			default => 'test_support@thehorecastore.co',
		};

		$phoneNumber = in_array(config('app.website'), ['UAE', 'UAE_T']) ? '+1 (866) 446-7322' : '+1 (866) 446-7322';

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'siteEmail' => $siteEmail,
			'phoneNumber' => $phoneNumber,
			'siteUrl' => $siteUrl,
		];

		return $this->subject("Update on Your Net Payment Terms Application")
		->markdown('emails.net-terms.net-terms-rejection')
		->with($params);
	}
}
