<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Order;

class OrderConfirmationMail extends Mailable
{
	use Queueable, SerializesModels;

	public $order;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Order $order)
	{
		$this->order = $order;
	}

	public function build()
	{
		$order = $this->order;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $order->customer->name ?? 'User';
		$orderNumber = $order->order_number;

		$rightPngURL = $backendURL. '/right.png';

		$orderUrl = url("/view-order/{$order->id}");
		$siteName = in_array(config('app.website'), ['UAE', 'UAE_T', 'SA']) ? 'UAE':'USA';
		$siteTollFreeContact = in_array(config('app.website'), ['UAE', 'UAE_T', 'SA']) ? '800 - HORECA (467-322)':'1-866-4-HORECA (1-866-446-7322)';
		$siteInternationalContact = in_array(config('app.website'), ['UAE', 'UAE_T', 'SA']) ? '+971 4 224 5818':'';

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE', 'SA'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'orders@thehorecastore.com',
			'UAE', 'SA'  => 'orders@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderNumber' => $orderNumber,

			'rightPngURL' => $rightPngURL,

			'orderUrl' => $orderUrl,
			'siteName' => $siteName,
			'siteTollFreeContact' => $siteTollFreeContact,
			'siteInternationalContact' => $siteInternationalContact,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order #{$orderNumber} Is Now Confirmed and in Progress")
		->markdown('emails.orders.order-confirmed')
		->with($params);
	}
}
