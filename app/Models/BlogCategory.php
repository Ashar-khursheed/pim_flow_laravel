<?php
// app/Models/BlogCategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * @OA\Schema(
 *     schema="BlogCategory",
 *     type="object",
 *     title="Blog Category",
 *     required={"id", "name", "slug", "status"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Technology"),
 *     @OA\Property(property="slug", type="string", example="technology"),
 *     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="description", type="string", nullable=true, example="All about tech"),
 *     @OA\Property(property="status", type="string", example="published"),
 *     @OA\Property(property="created_by", type="integer", nullable=true, example=5),
 *     @OA\Property(property="order", type="integer", example=0),
 *     @OA\Property(property="is_featured", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-29T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-29T10:00:00Z")
 * )
 */

class BlogCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'description',
        'status',
        'created_by',
        'order',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    /**
     * Parent category relation
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'parent_id');
    }

    /**
     * User who created this category
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
