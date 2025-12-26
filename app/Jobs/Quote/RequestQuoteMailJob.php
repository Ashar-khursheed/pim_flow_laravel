<?php

namespace App\Jobs\Quote;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\RateLimited;

use Illuminate\Support\Facades\Mail;
use App\Models\MadeToOrder;
use App\Mail\Quote\RequestQuoteMail;

class RequestQuoteMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $reqQuoteID;

	public function __construct($data)
	{
		$this->reqQuoteID = $data['recordId'];
	}

	public function middleware(): array
	{
		return [new RateLimited('emails')];
	}

	public function handle(): void
	{
		$reqQuote = MadeToOrder::find($this->reqQuoteID);

		if (!$reqQuote) {
			$this->fail(new \Exception("Request Quote {$this->reqQuoteID} not found"));
			return;
		}

		if (!empty($reqQuote)) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'orders@thehorecastore.com',
				'UAE'  => 'orders@horecastore.ae',
				'US_T' => 'test_us@thehorecastore.co',
				'UAE_T' => 'test_uae@thehorecastore.co',
				default => 'test@thehorecastore.co',
			};

			$fromName = 'HorecaStore Request Quote Updates';
			$replyToEmail = $fromEmail;

			$to = $reqQuote->email;
			Mail::to($to)->send(
				(
					new RequestQuoteMail($reqQuote)
				)
				->from($fromEmail, $fromName)
				->replyTo($replyToEmail)
			);

			if (in_array(config('app.website'), ['UAE', 'US', 'UAE_T', 'US_T'])) {
				$recipients = order_cc_mails();
				$to = array_shift($recipients);
				$cc = $recipients;
				Mail::to($to)->cc($cc)->send(
					(
						new RequestQuoteMail($reqQuote)
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
