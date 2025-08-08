<?php

namespace App\Traits;

use Illuminate\Support\Facades\Mail;

trait SendsQuoteMails
{
	protected function sendQuoteMail($recipientEmail, $mailable, $ccEmails)
	{
		$fromEmail = config('app.website') === 'UAE' ? 'quotes@horecastore.ae' : 'quotes@thehorecastore.com';
		$fromName = 'HorecaStore Quote Updates';
		$replyToEmail = $fromEmail;

		Mail::to($recipientEmail)->cc($ccEmails)->send(
			$mailable->from($fromEmail, $fromName)->replyTo($replyToEmail)
		);

		// $recipients = order_cc_mails();
		// if (!empty($recipients)) {
		// 	$to = array_shift($recipients);
		// 	$cc = $recipients;

		// 	Mail::to($to)->cc($cc)->send(
		// 		$mailable->from($fromEmail, $fromName)->replyTo($replyToEmail)
		// 	);
		// }
	}
}
