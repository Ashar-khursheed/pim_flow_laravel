<?php

namespace App\Jobs\Auth;

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
use App\Models\User;
use App\Mail\Auth\ResetPasswordMail;

class ResetPasswordMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $recordId;
	public $userType;

	public function __construct($data)
	{
		$this->recordId = $data['recordId'];
		$this->userType = $data['userType'];
	}

	public function handle(): void
	{
		try {
			if ($this->userType === 'customer') {
				$entity = Customer::find($this->recordId);
				$type = 'customer';
			} elseif ($this->userType === 'user') {
				$entity = User::find($this->recordId);
				$type = 'user';
			} else {
				throw new \Exception("Invalid user type: {$this->userType}");
			}

			if (!$entity) {
				throw new \Exception(ucfirst($this->userType) . " not found with given ID");
			}

			$token = Str::random(60);

			$entity->passwordResetToken()->updateOrCreate([
				'resettable_id' => $entity->id,
				'resettable_type' => get_class($entity),
			], [
				'token' => Hash::make($token),
				'created_at' => now(),
			]);

			Mail::to($entity->email)->send(new ResetPasswordMail($entity, $token, $type));

		} catch (\Throwable $e) {
			$this->fail($e);
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
