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
use App\Mail\Order\OrderPlacedMail;

class OrderPlacedMailJob implements ShouldQueue
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
			$to = $order->customer->email;
			$cc = order_cc_mails();

			Mail::to($to)->cc($cc)->send(new OrderPlacedMail($order));
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
