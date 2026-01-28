<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
	const UPDATED_AT = null;

	protected $fillable = [
		'chatbot_contact_id',
		'message',
		'created_by',
		'created_by_type'
	];

	public function chatbotContact()
	{
		return $this->belongsTo(ChatbotContact::class);
	}
}