<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class ChatbotContact extends Model
{
	protected $fillable = [
		'name',
		'email',
		'phone_number',
		'control'
	];

	protected $casts = [
		'control' => 'boolean'
	];

	public function chats()
	{
		return $this->hasMany(Chat::class, 'chatbot_contact_id');
	}

	public function unreadChats()
	{
		return $this->hasMany(Chat::class, 'chatbot_contact_id')
		->where('created_by_type', 'customer')
		->where('is_read', false);
	}
}