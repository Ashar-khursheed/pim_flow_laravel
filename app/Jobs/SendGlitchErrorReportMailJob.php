<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\FrontEnd\GlitchError;
use App\Mail\GlitchErrorMail;

class SendGlitchErrorReportMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $glitchErrorId;

	public function __construct($glitchErrorId)
	{
		$this->glitchErrorId = $glitchErrorId;
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

	public function failed($exception)
	{
		\Log::error("Glitch error mail job failed for ID {$this->glitchErrorId}: " . $exception->getMessage());
	}
}

