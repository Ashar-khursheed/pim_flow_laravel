<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoManagement extends Model
{
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
		'paragraph_1',
		'paragraph_2',
		'paragraph_3',
		'paragraph_4',
		'popular_tags',
	];

	public function relational()
	{
		return $this->morphTo();
	}
	
	protected $casts = [
		'popular_tags' => 'array', // Ensures it's handled as array in Laravel
		'indexing' => 'boolean',

	];

	public function secondaryKeywordDetails()
	{
		return $this->hasMany(SeoSecondaryKeyword::class, 'primary_keyword_id');
	}
}
