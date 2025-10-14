<?php

namespace App\Mail\Inquiry;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Inquiry;

class InquiryMail extends Mailable
{
	use Queueable, SerializesModels;

	public $inquiry;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Inquiry $inquiry)
	{
		$this->inquiry = $inquiry;
	}

	public function build()
	{
		$inquiry = $this->inquiry;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';

		$name = $inquiry->full_name;
		$phone = $inquiry->phone;
		$email = $inquiry->email;
		$companyName = $inquiry->company_name;
		$restaurantType = $inquiry->restaurant_type;
		$notes = $inquiry->notes;

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
			'phone' => $phone,
			'email' => $email,
			'companyName' => $companyName,
			'restaurantType' => $restaurantType,
			'notes' => $notes,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Thanks for Contacting HorecaStore – We’ve Received Your Request")
		->markdown('emails.inquiries.inquiry')
		->with($params);
	}
}
