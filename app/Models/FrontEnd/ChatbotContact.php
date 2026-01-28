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

	public function chats()
	{
		return $this->hasMany(Chat::class);
	}
}