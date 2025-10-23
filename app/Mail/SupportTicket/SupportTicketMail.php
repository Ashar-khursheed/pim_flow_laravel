<?php

namespace App\Mail\SupportTicket;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\SupportTicket;

class SupportTicketMail extends Mailable
{
	use Queueable, SerializesModels;

	public $ticket;

	/**
	 * Create a new message instance.
	 */
	public function __construct(SupportTicket $ticket)
	{
		$this->ticket = $ticket;
	}

	public function build()
	{
		$ticket = $this->ticket;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $ticket->customer->name ?? 'User';
		$ticketNumber = $ticket->ticket_number;
		$ticketDate = Carbon::parse($ticket->created_at)->format('D, M d, Y');
		$subject = $ticket->subject;
		$description = $ticket->description;
		$responseDays = $ticket->response_days;

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE', 'SA'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'sales@thehorecastore.com',
			'UAE', 'SA'  => 'hello@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'ticketNumber' => $ticketNumber,
			'ticketDate' => $ticketDate,
			'subject' => $subject,
			'description' => $description,
			'responseDays' => $responseDays,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Ticket #{$ticketNumber} Created – We’ve Received Your Request")
		->markdown('emails.tickets.ticket-creation')
		->with($params);
	}
}
