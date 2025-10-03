<?php

namespace App\Mail\Quote;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

use App\Models\FrontEnd\Quote;
use App\Traits\GeneratesQuotePdf;

class QuotePlacedMail extends Mailable
{
	use Queueable, SerializesModels, GeneratesQuotePdf;
	public $tries = 1;
	public $quote;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Quote $quote)
	{
		$this->quote = $quote;
	}

	public function build()
	{
		$quote = $this->quote;

		$pdfParams = $this->generateQuotePdfParams($quote->id);

		$quoteNumber = $quote->quote_number;
		$quoteName = $quote->quote_name;
		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = strtolower(optional($quote->customer)->type) === 'private' ? $quote->customer->name : $quote->customer->business_name;
		$rightPngURL = $backendURL. '/right.png';
		$mailIconURL = $backendURL. '/right.png';

		$downloadLink = config('app.url') . '/my-quotes';
		$orderLink = config('app.url') . '/download-quotation/' . $quote->id;

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'yourquote@thehorecastore.com',
			'UAE'  => 'yourquote@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$mailParams = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'rightPngURL' => $rightPngURL,
			'mailIconURL' => $mailIconURL,
			'downloadLink' => $downloadLink,
			'orderLink' => $orderLink,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		$pdf = Pdf::loadView('pdf.quote', $pdfParams);

		$quoteName = "HorecaStore_Quote_{$name}_{$quoteName}_{$quoteNumber}.pdf";

		$mail = $this->subject("Your HorecaStore Quote #{$quoteNumber} Has Been Successfully Generated")
		->markdown('emails.quotes.quote-placed')
		->with($mailParams)
		->attachData($pdf->output(), $quoteName, [
			'mime' => 'application/pdf',
		]);

		return $mail;
	}
}
