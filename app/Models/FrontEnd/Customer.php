<?php

namespace App\Models\FrontEnd;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class Customer extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $guard_name = 'api';
    protected $table = 'ec_customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable = [
        'name',
        'email',
        'first_name',
        'last_name',
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

  /**
     * Relationship: User has many roles.
     *
     * @return BelongsToMany
     */
    // public function roles(): BelongsToMany
    // {
    //     return $this->belongsToMany(Role::class, 'role_users');
    // }


    // Relationship: User has many media files
    public function mediaFiles()
    {
        return $this->hasMany(MediaFile::class, 'user_id');
    }

    protected function getNameAttribute()
    {
        return ucfirst($this->first_name) . ' ' . ucfirst($this->last_name);
    }
}
