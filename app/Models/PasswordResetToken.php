<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetToken extends Model
{
	public $timestamps = false;
	protected $fillable = ['token', 'created_at'];

	public function resettable()
	{
		return $this->morphTo();
	}
}