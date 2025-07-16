<?php

namespace App\Models\FrontEnd;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use App\Models\PasswordResetToken;
use App\Models\User;


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
		'type',
		'dob',
		'country_code',
		'mobile_number',
		'profile_img',
		'created_by',
		'is_social_login',
		'apple_id',
		'business_name'
		
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

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function customerAddress()
	{
		return $this->hasMany(CustomerAddress::class);
	}

	public function orders()
	{
		return $this->hasMany(Order::class);
	}

	public function passwordResetToken()
	{
		return $this->morphOne(PasswordResetToken::class, 'resettable');
	}

	/**
	 * Prepare a date for array / JSON serialization.
	 *
	 * @param  \DateTimeInterface  $date
	 * @return string
	 */
	protected function serializeDate(\DateTimeInterface $date)
	{
		return $date->format('Y-m-d H:i:s');
	}

	public function questions()
	{
		return $this->hasMany(ProductQuestion::class);
	}

}
