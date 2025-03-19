<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Newsletter",
 *     type="object",
 *     title="Newsletter",
 *     required={"email"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="email", type="string", example="user@example.com"),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="status", type="string", example="subscribed"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Newsletter extends Model
{
    use HasFactory;

    protected $table = 'newsletters';

    protected $fillable = ['email', 'name', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
