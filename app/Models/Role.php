<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Role",
 *     title="Role",
 *     description="Role model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="slug", type="string", example="admin"),
 *     @OA\Property(property="name", type="string", example="Administrator"),
 *     @OA\Property(property="permissions", type="object", example={"create": true, "delete": false}),
 *     @OA\Property(property="description", type="string", example="Admin role with all permissions"),
 *     @OA\Property(property="is_default", type="boolean", example=true),
 *     @OA\Property(property="created_by", type="integer", example=1),
 *     @OA\Property(property="updated_by", type="integer", example=2),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-08-01T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-08-02T12:00:00Z")
 * )
 */
class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';
    protected $fillable = ['slug', 'name', 'permissions', 'description', 'is_default', 'created_by', 'updated_by'];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_users');
    }
}
