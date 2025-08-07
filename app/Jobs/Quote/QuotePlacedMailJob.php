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
	public $quoteId;

	public function __construct($data)
	{
		$this->quoteId = $data['recordId'];
	}

	public function handle(): void
	{
		$quote = Quote::find($this->quoteId);

		if (!$quote) {
			$this->fail(new \Exception("Quote {$this->quoteId} not found"));
			return;
		}

		if (!empty($quote)) {
			$to = $quote->customer->email;
			Mail::to($to)->send(new QuotePlacedMail($quote));
			// Mail::to($to)->send(
			// 	(
			// 		new QuoteCancelledMail($quote)
			// 	)
			// 	->from('quotes@thehorecastore.com', 'HorecaStore Quote Updates')
			// 	->replyTo('quotes@thehorecastore.com')
			// );
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
