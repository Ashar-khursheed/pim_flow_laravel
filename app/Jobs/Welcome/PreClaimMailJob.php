<?php

namespace App\Jobs\Welcome;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

use App\Models\FrontEnd\PrePurchaseClaim;
use App\Mail\Welcome\PreClaimMail;

class PreClaimMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
	public $timeout = 600;
	public $claimId;

	public function __construct($data)
	{
		$this->claimId = $data['recordId'];
	}

	public function handle(): void
	{
		$claim = PrePurchaseClaim::find($this->claimId);

		if (!$claim) {
			$this->fail(new \Exception("Pre purchase claim {$this->claimId} not found"));
			return;
		}

		if (!empty($claim)) {
			$fromEmail = config('app.website') === 'UAE' ? 'hello@thehorecastore.co':'sales@thehorecastore.com';
			$fromName = 'HorecaStore';
			$replyToEmail = $fromEmail;

			$to = $claim->customer->email;
			Mail::to($to)->send(
				(
					new PreClaimMail($claim)
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
