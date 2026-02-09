<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Chat extends Model
{
	const UPDATED_AT = null;

	protected $fillable = [
		'chatbot_contact_id',
		'message',
		'created_by',
		'created_by_type',
		'is_read',
		'read_at'
	];

	protected $casts = [
		'is_read' => 'boolean',
		'read_at' => 'datetime'
	];

	public function chatbotContact()
	{
		return $this->belongsTo(ChatbotContact::class, 'chatbot_contact_id');
	}

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}
}