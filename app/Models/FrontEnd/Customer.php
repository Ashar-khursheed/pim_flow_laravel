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
	/**
 * @OA\Schema(
 *     schema="Coupon",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="SAVE10"),
 *     @OA\Property(property="value", type="number", format="float", example=10.00),
 *     @OA\Property(property="type", type="string", example="fixed"),
 *     @OA\Property(property="min_order_price", type="number", format="float", example=50.00),
 *     @OA\Property(property="start_date", type="string", format="date-time", example="2025-01-01T00:00:00Z"),
 *     @OA\Property(property="end_date", type="string", format="date-time", example="2025-12-31T23:59:59Z")
 * )
 */

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
		'mobile_number',
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
}
