<?php

namespace App\Models\FrontEnd;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Models\Newsletter;
use App\Models\Frontend\Wishlist;

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
		'business_name',
		'business_licence',
		'trn_number',
		'vat_certificate',
		'email',
		'password',
		'type',
		'dob',
		'country_code',
		'mobile_number',
		'profile_img',
		'is_tax_free',
		'approval_action_by',
		'approval_action_notes',
		'approval_action_at',
		'created_by',
		'is_social_login',
		'apple_id',
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

	public function newsLetter()
	{
		return $this->hasOne(Newsletter::class, 'email', 'email');
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

	public function customerCarts()
	{
		return $this->hasMany(CustomerCart::class, 'customer_id', 'id');
	}

	public function wishlist()
	{
		return $this->hasMany(Wishlist::class, 'customer_id');
	}
}
