<?php

namespace App\Traits;

use Illuminate\Support\Facades\Mail;

trait SendsOrderMails
{
	protected function sendOrderMail($recipientEmail, $mailable)
	{
		$fromEmail = config('app.website') === 'UAE' ? 'orders@horecastore.ae' : 'orders@thehorecastore.com';
		$fromName = 'HorecaStore Order Updates';
		$replyToEmail = $fromEmail;

		Mail::to($recipientEmail)->send(
			($mailable)->from($fromEmail, $fromName)->replyTo($replyToEmail)
		);

		$recipients = order_cc_mails();
		if (!empty($recipients)) {
			$to = array_shift($recipients);
			$cc = $recipients;

			Mail::to($to)->cc($cc)->send(
				($mailable)->from($fromEmail, $fromName)->replyTo($replyToEmail)
			);
		}
	}
}
