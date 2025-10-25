<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\FrontEnd\GlitchError;

class GlitchErrorMail extends Mailable
{
	use Queueable, SerializesModels;

	public $record;

	public function __construct(GlitchError $record)
	{
		$this->record = $record;
	}

	public function build()
	{
		return $this->subject('New Glitch Error Reported')
		->markdown('emails.glitch_error')
		->with([
			'description' => $this->record->description,
			'email' => $this->record->email,
			'contact' => $this->record->mobile_number,
			'device' => $this->record->device,
			'images' => json_decode($this->record->images, true),
		]);
	}
}
