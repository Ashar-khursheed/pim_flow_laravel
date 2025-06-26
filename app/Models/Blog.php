<?php
// app/Models/Blog.php

namespace App\Models;
use App\Models\FrontEnd\BlogComment;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Blog",
 *     type="object",
 *     title="Blog",
 *     required={"id", "title", "slug", "status"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="How to use Laravel"),
 *     @OA\Property(property="slug", type="string", example="how-to-use-laravel"),
 *     @OA\Property(property="content", type="string", example="This is the content of the blog post."),
 *     @OA\Property(property="status", type="string", example="published"),
 *     @OA\Property(property="author_id", type="integer", example=5),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-29T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-29T10:00:00Z")
 * )
 */

class Blog extends Model
{
    protected $fillable = [
        'name', 'slug', 'desktop_banner', 'desktop_banner_alt',
        'mobile_banner', 'mobile_banner_alt', 'thumbnail', 'thumbnail_alt',
        'description', 'created_by', 'blog_category_id',
        'faqs', 'tags', 'total_views', 'total_likes', 'total_shares',
        'status', 'is_featured','image','created_date','written_by'
    ];

        protected $casts = [
            'description' => 'array',
            'faqs' => 'array',
            'tags' => 'array',
            'status' => 'string', // ✅ FIXED
            'is_featured' => 'boolean',
        ];

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function comments()
    {
        return $this->hasMany(BlogComment::class)
                    ->whereNull('parent_id')
                    ->with('replies', 'creator');
    }

}
