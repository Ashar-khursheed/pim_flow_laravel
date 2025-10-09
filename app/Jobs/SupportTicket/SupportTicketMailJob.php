<?php

namespace App\Jobs\SupportTicket;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\RateLimited;

use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\SupportTicket;
use App\Mail\SupportTicket\SupportTicketMail;

class SupportTicketMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $ticketId;

	public function __construct($data)
	{
		$this->ticketId = $data['recordId'];
	}

	public function middleware(): array
	{
		return [new RateLimited('emails')];
	}

	public function handle(): void
	{
		$ticket = SupportTicket::find($this->ticketId);

		if (!$ticket) {
			$this->fail(new \Exception("Support ticket {$this->ticketId} not found"));
			return;
		}

		if (!empty($ticket)) {
			$fromEmail = match (config('app.website')) {
				'US'  => 'sales@thehorecastore.com',
				'UAE'  => 'hello@horecastore.ae',
				'US_T' => 'test_us@thehorecastore.co',
				'UAE_T' => 'test_uae@thehorecastore.co',
				default => 'test@thehorecastore.co',
			};

			$fromName = 'HorecaStore Support Team';
			$replyToEmail = $fromEmail;

			$to = $ticket->customer->email;
			Mail::to($to)->send(
				(
					new SupportTicketMail($ticket)
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
