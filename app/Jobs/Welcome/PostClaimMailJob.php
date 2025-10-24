<?php

namespace App\Jobs\Welcome;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\RateLimited;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

use App\Models\FrontEnd\PostPurchaseClaim;
use App\Mail\Welcome\PostClaimMail;

class PostClaimMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $claimId;

	public function __construct($data)
	{
		$this->claimId = $data['recordId'];
	}

	public function middleware(): array
	{
		return [new RateLimited('emails')];
	}

	public function handle(): void
	{
		$claim = PostPurchaseClaim::find($this->claimId);

		if (!$claim) {
			$this->fail(new \Exception("Post purchase claim {$this->claimId} not found"));
			return;
		}

		if (!empty($claim)) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'sales@thehorecastore.com',
				'UAE', 'SA'  => 'hello@horecastore.ae',
				'US_T' => 'test_us@thehorecastore.co',
				'UAE_T' => 'test_uae@thehorecastore.co',
				default => 'test@thehorecastore.co',
			};
			$fromName = 'HorecaStore';
			$replyToEmail = $fromEmail;

			$to = $claim->customer->email;
			Mail::to($to)->send(
				(
					new PostClaimMail($claim)
				)
				->from($fromEmail, $fromName)
				->replyTo($replyToEmail)
			);
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
