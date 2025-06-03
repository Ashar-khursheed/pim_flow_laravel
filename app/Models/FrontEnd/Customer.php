<?php

namespace App\Models\FrontEnd;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use App\Models\PasswordResetToken;

class Customer extends Authenticatable
{
	use HasApiTokens, Notifiable;

	protected $guard_name = 'api';
	protected $table = 'customers';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var list<string>
	 */

	protected $fillable = [
		'name',
		'email',
		'password',
		'profile_img'
	];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var list<string>
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'email_verified_at' => 'datetime',
			'password' => 'hashed',
		];
	}

	public function passwordResetToken()
	{
		return $this->morphOne(PasswordResetToken::class, 'resettable');
	}
}
