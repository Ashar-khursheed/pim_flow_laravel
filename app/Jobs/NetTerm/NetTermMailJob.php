<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\RateLimited;

use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\Finance;
use App\Mail\NetTerm\NetTermMail;

class NetTermMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $financeID;

	public function __construct($data)
	{
		$this->financeID = $data['recordId'];
	}

	public function middleware(): array
	{
		return [new RateLimited('emails')];
	}

	public function handle(): void
	{
		$finance = Finance::find($this->financeID);

		if (!$finance) {
			$this->fail(new \Exception("Finance {$this->financeID} not found"));
			return;
		}

		if (!empty($finance)) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'support@thehorecastore.com',
				'UAE'  => 'support@horecastore.ae',
				'US_T' => 'test_us_support@thehorecastore.co',
				'UAE_T' => 'test_uae_support@thehorecastore.co',
				default => 'test_support@thehorecastore.co',
			};

			$fromName = 'HorecaStore Support';
			$replyToEmail = $fromEmail;

			$to = $finance->customer->email;
			Mail::to($to)->send(
				(
					new NetTermMail($finance)
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
						new NetTermMail($finance)
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
