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
		$logoUrl = $backendURL . '/logo.png';
		$name = $claim->customer->name ?? 'User';
		$rightPngURL = $backendURL. '/right.png';
		$claimId = $claim->id;

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
