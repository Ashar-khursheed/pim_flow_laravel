<?php

namespace App\Mail\Welcome;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\PostPurchaseClaim;

class PostClaimMail extends Mailable
{
	use Queueable, SerializesModels;

	public $claim;

	/**
	 * Create a new message instance.
	 */
	public function __construct(PostPurchaseClaim $claim)
	{
		$this->claim = $claim;
	}

	public function build()
	{
		$claim = $this->claim;

		$orderNumber = $claim->order->order_number;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $claim->customer->name ?? 'User';
		$rightPngURL = $backendURL. '/right.png';
		$claimId = $claim->id;

		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@thehorecastore.co':'sales@thehorecastore.com';

		$params = [
			'orderNumber' => $orderNumber,
			'logoUrl' => $logoUrl,
			'name' => $name,
			'rightPngURL' => $rightPngURL,
			'claimId' => $claimId,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your Price Match claim has been submitted")
		->markdown('emails.welcome.post-claim')
		->with($params);
	}
}
