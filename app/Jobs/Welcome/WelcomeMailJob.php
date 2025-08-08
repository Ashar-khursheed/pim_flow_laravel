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

use App\Models\FrontEnd\Customer;
use App\Mail\Welcome\WelcomeMail;

class WelcomeMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
	public $timeout = 600;
	public $customerId;

	public function __construct($data)
	{
		$this->customerId = $data['recordId'];
	}

	public function handle(): void
	{
		$customer = Customer::find($this->customerId);

		if (!$customer) {
			$this->fail(new \Exception("Customer {$this->customerId} not found"));
			return;
		}

		if (!empty($customer)) {
			$to = $customer->email;
			Mail::to($to)->send(new WelcomeMail($customer));
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
