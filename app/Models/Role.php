<?php

// namespace App\Models; // Ensure correct namespace

// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// class Role extends Model
// {
//     protected $table = 'roles';

//     public function users(): BelongsToMany
//     {
//         return $this->belongsToMany(User::class, 'role_users');
//     }
// }


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';
    protected $fillable = ['slug', 'name', 'permissions', 'description', 'is_default', 'created_by', 'updated_by'];

    protected $casts = [
        'permissions' => 'array', // Auto-cast JSON to an array
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_users');
    }
}
