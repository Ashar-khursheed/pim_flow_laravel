<?php

namespace App\Jobs\Quote;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use App\Models\FrontEnd\Quote;
use App\Mail\Quote\QuotePlacedMail;
use App\Traits\SendsQuoteMails;

class QuotePlacedMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable, SendsQuoteMails;

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

		$ccEmails = [];
		if ($this->sendToCc) {
			$ccEmails = $quote->quoteEmails->pluck('email')->filter()->unique()->toArray();
		}

		$this->sendQuoteMail(
			$quote->customer->email,
			new QuotePlacedMail($quote),
			$ccEmails
		);
	}

	public function failed(\Throwable $exception): void
	{
		$jobName = class_basename($this);

		logger()->error("{$jobName} failed", [
			'job'     => $jobName,
			'message' => $exception->getMessage(),
			'file'    => $exception->getFile(),
			'line'    => $exception->getLine(),
			'trace'   => $exception->getTraceAsString(),
		]);
	}
}
