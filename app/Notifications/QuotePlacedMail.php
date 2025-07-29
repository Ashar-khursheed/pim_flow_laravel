<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Traits\GeneratesQuotePdf;

class QuotePlacedMail extends Notification implements ShouldQueue
{
	use Queueable, GeneratesQuotePdf;
	public $timeout = 43200;

	public $quote;
	public $sendToCc;

	/* Constructor with default value for $sendToCc */
	public function __construct($quote, bool $sendToCc = true)
	{
		$this->quote = $quote;
		$this->sendToCc = $sendToCc;
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @return array<int, string>
	 */
	public function via($notifiable)
	{
		return ['mail'];
	}

	/**
	 * Get the mail representation of the notification.
	 */
	public function toMail($notifiable)
	{
		$pdfParams = $this->generateQuotePdfParams($this->quote->id);

		$quoteNumber = $this->quote->quote_number;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $notifiable->type === 'Private' ? $notifiable->name : $notifiable->business_name;
		$rightPngURL = $backendURL. '/right.png';
		$mailIconURL = $backendURL. '/right.png';

		$downloadLink = url('/my-quotes');
		$orderLink = url('/checkout');
		$siteName = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';
		$mailParams = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'rightPngURL' => $rightPngURL,
			'mailIconURL' => $mailIconURL,
			'downloadLink' => $downloadLink,
			'orderLink' => $orderLink,
			'siteName' => $siteName,
			'siteEmail' => $siteEmail,
		];

		$ccEmails = $this->quote->quoteEmails->pluck('email')->toArray();

		$pdf = Pdf::loadView('pdf.quote', $pdfParams);

		if ($this->sendToCc) {
			return (new MailMessage)
			->subject("Your HorecaStore Quote #{$quoteNumber} Has Been Successfully Placed")
			->cc($ccEmails)
			->attachData($pdf->output(), "Quote_{$quoteNumber}.pdf", [
				'mime' => 'application/pdf',
			])
			->markdown('emails.quotes.quote-placed', $mailParams);
		} else {
			return (new MailMessage)
			->subject("Your HorecaStore Quote #{$quoteNumber} Has Been Successfully Placed")
			->attachData($pdf->output(), "Quote_{$quoteNumber}.pdf", [
				'mime' => 'application/pdf',
			])
			->markdown('emails.quotes.quote-placed', $mailParams);
		}
	}

	/**
	 * Get the array representation of the notification.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(object $notifiable): array
	{
		return [
			//
		];
	}
}
