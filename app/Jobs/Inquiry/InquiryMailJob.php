<?php

namespace App\Jobs\Inquiry;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\RateLimited;

use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\Inquiry;
use App\Mail\Inquiry\InquiryMail;

class InquiryMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $inquiryID;

	public function __construct($data)
	{
		$this->inquiryID = $data['recordId'];
	}

	public function middleware(): array
	{
		return [new RateLimited('emails')];
	}

	public function handle(): void
	{
		$inquiry = Inquiry::find($this->inquiryID);

		if (!$inquiry) {
			$this->fail(new \Exception("Inquiry {$this->inquiryID} not found"));
			return;
		}

		if (!empty($inquiry)) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'sales@thehorecastore.com',
				'UAE', 'SA'  => 'hello@horecastore.ae',
				'US_T' => 'test_us@thehorecastore.co',
				'UAE_T' => 'test_uae@thehorecastore.co',
				default => 'test@thehorecastore.co',
			};

			$fromName = 'HorecaStore Team';
			$replyToEmail = $fromEmail;

			$to = $inquiry->email;
			Mail::to($to)->send(
				(
					new InquiryMail($inquiry)
				)
				->from($fromEmail, $fromName)
				->replyTo($replyToEmail)
			);

			if (in_array(config('app.website'), ['UAE', 'US', 'UAE_T', 'US_T'])) {
				$recipients = inquiry_cc_mails();
				$to = array_shift($recipients);
				$cc = $recipients;
				Mail::to($to)->cc($cc)->send(
					(
						new InquiryMail($inquiry)
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
