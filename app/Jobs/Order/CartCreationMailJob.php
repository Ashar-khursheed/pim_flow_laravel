<?php

namespace App\Jobs\Order;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\CustomerCart;
use App\Mail\Order\CartCreationMail;

class CartCreationMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
	public $timeout = 600;
	public $customerCartID;
	public $randomPassword;

	public function __construct($data)
	{
		$this->customerCartID = $data['recordId'];
		$this->randomPassword = $data['randomPassword'];
	}

	public function handle(): void
	{
		$customerCart = CustomerCart::find($this->customerCartID);

		if (!$customerCart) {
			$this->fail(new \Exception("Customer cart {$this->customerCartID} not found"));
			return;
		}

		if (!empty($customerCart)) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'carts@thehorecastore.com',
				'UAE'  => 'carts@thehorecastore.co',
				'TEST' => 'test_carts@thehorecastore.com',
				default => 'carts@thehorecastore.com',
			};
			$fromEmail = config('app.website') === 'UAE' ? 'cart@thehorecastore.co' : 'cart@thehorecastore.com';
			$fromName = 'HorecaStore Cart Updates';
			$replyToEmail = $fromEmail;

			$to = $customerCart->customer->email;
			Mail::to($to)->send(
				(
					new CartCreationMail($customerCart, $this->randomPassword)
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
