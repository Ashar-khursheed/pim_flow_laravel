<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use Notifiable;

    protected $table = 'ec_customers';

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'dob',
        'phone',
        'remember_token',
        'confirmed_at',
        'email_verify_token',
        'status',
        'private_notes',
        'is_vendor',
        'vendor_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verify_token',
        'private_notes',
    ];

    protected $casts = [
        'dob' => 'date',
        'confirmed_at' => 'datetime',
        'vendor_verified_at' => 'datetime',
        'is_vendor' => 'boolean',
    ];
}
