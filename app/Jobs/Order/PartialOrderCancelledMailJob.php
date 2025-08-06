<?php

namespace App\Jobs\Order;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\OrderProduct;
use App\Mail\Order\PartialOrderCancelledMail;

class PartialOrderCancelledMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
	public $timeout = 600;
	public $orderProductId;
	public $reason;

	public function __construct($data)
	{
		$this->orderProductId = $data['recordId'];
		$this->reason = $data['reason'];
	}

	public function handle(): void
	{
		$orderProduct = OrderProduct::find($this->orderProductId);

		if (!$orderProduct) {
			$this->fail(new \Exception("Order Product {$this->orderProductId} not found"));
			return;
		}

		if (!empty($orderProduct)) {
			$to = $orderProduct->order->customer->email;
			// Mail::to($to)->send(new PartialOrderCancelledMail($orderProduct, $this->reason));
			Mail::to($to)->send(
				(
					new PartialOrderCancelledMail($orderProduct, $this->reason)
				)
				->from('orders@thehorecastore.com', 'HorecaStore Order Updates')
				->replyTo('orders@thehorecastore.com')
			);

			$recipients = order_cc_mails();
			$to = array_shift($recipients);
			$cc = $recipients;
			// Mail::to($to)->cc($cc)->send(new PartialOrderCancelledMail($orderProduct, $this->reason));
			Mail::to($to)->cc($cc)->send(
				(
					new PartialOrderCancelledMail($orderProduct, $this->reason)
				)
				->from('orders@thehorecastore.com', 'HorecaStore Order Updates')
				->replyTo('orders@thehorecastore.com')
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
