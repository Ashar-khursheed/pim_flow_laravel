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
use App\Models\FrontEnd\Order;
use App\Mail\Order\OrderPlacedMail;

class OrderPlacedMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $orderId;

	public function __construct($data)
	{
		$this->orderId = $data['recordId'];
	}

	public function middleware(): array
	{
		return [new RateLimited('emails')];
	}

	public function handle(): void
	{
		$order = Order::find($this->orderId);

		if (!$order) {
			$this->fail(new \Exception("Order {$this->orderId} not found"));
			return;
		}

		if (!empty($order)) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'orders@thehorecastore.com',
				'UAE', 'SA'  => 'orders@horecastore.ae',
				'US_T' => 'test_us@thehorecastore.co',
				'UAE_T' => 'test_uae@thehorecastore.co',
				default => 'test@thehorecastore.co',
			};

			$fromName = 'HorecaStore Order Updates';
			$replyToEmail = $fromEmail;

			$to = $order->customer->email;
			Mail::to($to)->send(
				(
					new OrderPlacedMail($order)
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
						new OrderPlacedMail($order)
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
