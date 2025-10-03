<?php

namespace App\Jobs\Auth;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\RateLimited;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

use App\Models\FrontEnd\Customer;
use App\Mail\Auth\CommonPasswordResetMail;

class CommonPasswordResetMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $customerId;

	public function __construct($data)
	{
		$this->customerId = $data['recordId'];
	}

	public function middleware(): array
	{
		return [new RateLimited('emails')];
	}

	public function handle(): void
	{
		$customer = Customer::find($this->customerId);

		if (!$customer) {
			$this->fail(new \Exception("Customer {$this->customerId} not found"));
			return;
		}

		if (!empty($customer)) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'sales@thehorecastore.com',
				'UAE'  => 'hello@horecastore.ae',
				'US_T' => 'test_us@thehorecastore.co',
				'UAE_T' => 'test_uae@thehorecastore.co',
				default => 'test@thehorecastore.co',
			};

			$fromName = 'Horeca Store';
			$replyToEmail = $fromEmail;

			$token = Str::random(60);
			$customer->passwordResetToken()->updateOrCreate([], [
				'token' => Hash::make($token),
				'created_at' => now(),
			]);

			$to = $customer->email;

			Mail::to($to)->send(
				(
					new CommonPasswordResetMail($customer, $token)
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
