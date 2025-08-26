<?php

namespace App\Jobs\Order;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\Order;
use App\Mail\Order\OrderConfirmationMail;

class OrderConfirmationMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
	public $timeout = 600;
	public $orderId;

	public function __construct($data)
	{
		$this->orderId = $data['recordId'];
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
				'UAE'  => 'orders@horecastore.ae',
				'TEST' => 'test_orders@thehorecastore.com',
				default => 'orders@thehorecastore.com',
			};
			$fromName = 'HorecaStore Order Updates';
			$replyToEmail = $fromEmail;

			$to = $order->customer->email;
			Mail::to($to)->send(
				(
					new OrderConfirmationMail($order)
				)
				->from($fromEmail, $fromName)
				->replyTo($replyToEmail)
			);

			if (config('app.website') !== 'TEST') {
				$recipients = order_cc_mails();
				$to = array_shift($recipients);
				$cc = $recipients;
				Mail::to($to)->cc($cc)->send(
					(
						new OrderConfirmationMail($order)
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
