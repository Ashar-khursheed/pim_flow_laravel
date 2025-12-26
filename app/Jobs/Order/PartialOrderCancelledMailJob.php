<?php

namespace App\Jobs\Order;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\RateLimited;

use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\OrderProduct;
use App\Mail\Order\PartialOrderCancelledMail;

class PartialOrderCancelledMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $orderProductId;
	public $reason;

	public function __construct($data)
	{
		$this->orderProductId = $data['recordId'];
		$this->reason = $data['reason'];
	}

	public function middleware(): array
	{
		return [new RateLimited('emails')];
	}

	public function handle(): void
	{
		$orderProduct = OrderProduct::find($this->orderProductId);

		if (!$orderProduct) {
			$this->fail(new \Exception("Order Product {$this->orderProductId} not found"));
			return;
		}

		if (!empty($orderProduct)) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'orders@thehorecastore.com',
				'UAE', 'SA'  => 'orders@horecastore.ae',
				'US_T' => 'test_us@thehorecastore.co',
				'UAE_T' => 'test_uae@thehorecastore.co',
				default => 'test@thehorecastore.co',
			};

			$fromName = 'HorecaStore Order Updates';
			$replyToEmail = $fromEmail;

			$to = $orderProduct->order->customer->email;
			Mail::to($to)->send(
				(
					new PartialOrderCancelledMail($orderProduct, $this->reason)
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
						new PartialOrderCancelledMail($orderProduct, $this->reason)
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
