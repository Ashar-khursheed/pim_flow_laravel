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
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $claim->customer->name ?? 'User';
		$rightPngURL = $backendURL. '/right.png';
		$claimId = $claim->id;

		$siteName = config('app.website') == 'UAE' ? 'UAE':'USA';
		$siteTollFreeContact = config('app.website') == 'UAE' ? '800 - HORECA (467-322)':'1-866-4-HORECA (1-866-446-7322)';
		$siteInternationalContact = config('app.website') == 'UAE' ? '+971 4 224 5818':'';

		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

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
