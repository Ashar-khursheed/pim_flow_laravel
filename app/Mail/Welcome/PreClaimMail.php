<?php

namespace App\Mail\Welcome;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\PrePurchaseClaim;

class PreClaimMail extends Mailable
{
	use Queueable, SerializesModels;

	public $claim;

	/**
	 * Create a new message instance.
	 */
	public function __construct(PrePurchaseClaim $claim)
	{
		$this->claim = $claim;
	}

	public function build()
	{
		$claim = $this->claim;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $claim->customer->name ?? 'User';
		$rightPngURL = $backendURL. '/right.png';
		$claimId = $claim->id;

		$siteName = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'UAE':'USA';
		$siteTollFreeContact = in_array(config('app.website'), ['UAE', 'UAE_T']) ? '800 - HORECA (467-322)':'1-866-4-HORECA (1-866-446-7322)';
		$siteInternationalContact = in_array(config('app.website'), ['UAE', 'UAE_T']) ? '+971 4 224 5818':'';

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
			'rightPngURL' => $rightPngURL,
			'claimId' => $claimId,
			'siteName' => $siteName,
			'siteTollFreeContact' => $siteTollFreeContact,
			'siteInternationalContact' => $siteInternationalContact,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("We’ve received your Price Match request")
		->markdown('emails.welcome.pre-claim')
		->with($params);
	}
}
