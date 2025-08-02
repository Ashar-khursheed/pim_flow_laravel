<?php

namespace App\Jobs;


use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\GlitchError;
use App\Mail\GlitchErrorMail;

class SendGlitchErrorReportMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
	public $timeout = 600;
	public $glitchErrorId;

	public function __construct($data)
	{
		$this->glitchErrorId = $data['recordId'];
	}
	/**
	 * Execute the job.
	 */
	public function handle(): void
	{
		$glitchError = GlitchError::find($this->glitchErrorId);

		if (!$glitchError) {
			$this->fail(new \Exception("GlitchError {$this->glitchErrorId} not found"));
			return;
		}

		$recipients = glitch_error_reporting_mails(); // returns email array

		if (!empty($recipients)) {
			$to = array_shift($recipients); // first is To
			$cc = $recipients;              // rest as CC

			Mail::to($to)->cc($cc)->send(new GlitchErrorMail($glitchError));
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
