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
		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $quote->customer->type === 'Private' ? $quote->customer->name : $quote->customer->business_name;
		$rightPngURL = $backendURL. '/right.png';
		$mailIconURL = $backendURL. '/right.png';

		$downloadLink = config('app.url') . '/my-quotes';
		$orderLink = config('app.url') . '/download-quotation/' . $quote->id;

		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'yourquote@thehorecastore.co':'yourquote@thehorecastore.com';

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

		$mail = $this->subject("Your HorecaStore Quote #{$quoteNumber} Has Been Successfully Generated")
		->markdown('emails.quotes.quote-placed')
		->with($mailParams)
		->attachData($pdf->output(), "Quote_{$quoteNumber}.pdf", [
			'mime' => 'application/pdf',
		]);

		return $mail;
	}
}
