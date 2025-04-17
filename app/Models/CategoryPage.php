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
        'related_products', 'top_picks_in_santos' , 'explore_top_picks' , 'hot_new_releases',
        'products_you_may_also_like','inspired_by_your_browsing_history', 'top_deals_from_our_sellers'
    ];

    protected $casts = [
        'inner_categories' => 'array',
        'six_images' => 'array',
        'four_banners' => 'array',
        'twelve_images' => 'array',
        'related_products' => 'array',
        'top_picks_in_santos' => 'array',
        'top_deals_from_our_sellers' => 'array',
        'explore_top_picks' => 'array',
        'hot_new_releases' => 'array',
        'products_you_may_also_like' => 'array',
        'inspired_by_your_browsing_history' => 'array',

    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
