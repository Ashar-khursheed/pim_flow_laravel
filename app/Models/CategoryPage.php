<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'title', 'description', 'banner_image', 'banner_link',
        'inner_categories', 'section_title', 'section_description', 'six_images',
        'four_banners', 'extra_heading', 'extra_description', 'twelve_images',
        'related_products'
    ];

    protected $casts = [
        'inner_categories' => 'array',
        'six_images' => 'array',
        'four_banners' => 'array',
        'twelve_images' => 'array',
        'related_products' => 'array'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
