<?php

namespace App\Jobs\Quote;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\Quote;
use App\Mail\Quote\QuotePlacedMail;

class QuotePlacedMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $timeout = 600;

	protected int $quoteId;
	protected bool $sendToCc;

	public function __construct(array $data)
	{
		$this->quoteId = $data['recordId'];
		$this->sendToCc = $data['sendToCc'] ?? false;
	}

	public function handle(): void
	{
		$quote = Quote::find($this->quoteId);

		if (!$quote) {
			$this->fail(new \Exception("Quote {$this->quoteId} not found"));
			return;
		}

		$to = $quote->customer->email ?? null;

		if ($to) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'yourquote@thehorecastore.com',
				'UAE'  => 'yourquote@horecastore.ae',
				'TEST' => 'test@thehorecastore.co',
				default => 'test@thehorecastore.co',
			};
			$fromName = 'Best Price | HorecaStore';
			$replyToEmail = $fromEmail;

			$ccEmails = [];

			if ($this->sendToCc) {
				$ccEmails = $quote->quoteEmails->pluck('email')->filter()->unique()->toArray();
			}

			Mail::to($to)->cc($ccEmails)->send(
				(
					new QuotePlacedMail($quote)
				)
				->from($fromEmail, $fromName)
				->replyTo($replyToEmail)
			);

			if (config('app.website') !== 'TEST') {
				$recipients = quote_cc_mails();
				$to = array_shift($recipients);
				$cc = $recipients;
				Mail::to($to)->cc($cc)->send(
					(
						new QuotePlacedMail($quote)
					)
					->from($fromEmail, $fromName)
					->replyTo($replyToEmail)
				);
			}
		}
	}

	public function failed(\Throwable $exception): void
	{
		$jobName = class_basename($this);

		$errorDetails = [
			'job' => $jobName,
			'message' => $exception->getMessage(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		];

		logger()->error("{$jobName} failed", $errorDetails);
	}
}
