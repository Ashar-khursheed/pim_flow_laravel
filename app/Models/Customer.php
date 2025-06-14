<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use Notifiable;

    protected $table = 'customers';

    protected $fillable = [
        'name',
		'email',
		'password',
		'dob',
		'mobile_number',
		'profile_img'
    ];

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
