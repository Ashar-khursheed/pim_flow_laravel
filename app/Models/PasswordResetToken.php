<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetToken extends Model
{
	protected $fillable = ['token', 'created_at'];
	public $timestamps = false;

	public function resettable()
	{
		return $this->morphTo();
	}
}

