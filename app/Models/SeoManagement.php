<?php
// app/Models/SeoManagement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoManagement extends Model
{
    protected $table = 'seo_management';

    protected $fillable = [
        'relational_id',
        'relational_type',
        'url',
        'primary_keyword',
        'monthly_search_volume',
        'title_tag',
        'meta_title',
        'meta_description',
        'internal_links',
        'indexing',
        'og_title',
        'og_description',
        'og_image_url',
        'og_image_alt_text',
        'og_image_name',
        'tags',
        'schema',
        'schema_rating',
        'schema_reviews_count',
        'created_by',
        'updated_by',
    ];

    public function secondaryKeywords()
    {
        return $this->hasMany(SeoSecondaryKeyword::class, 'primary_keyword_id', 'id');
    }
}
