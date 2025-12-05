<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class SeoManagement extends Model implements TranslatableContract
{
    use Translatable;

    // public $translatedAttributes = ["primary_keyword_tr", "title_tag_tr", "meta_title_tr", "meta_description_tr", "og_title_tr", "og_description_tr", "og_image_url_tr", "og_image_alt_text_tr", "og_image_name_tr", "paragraph_1_tr", "paragraph_2_tr", "paragraph_3_tr", "paragraph_4_tr", "banner_image_file_tr", "banner_image_alt_text_tr"];
    public $translatedAttributes = [];

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
		'google_shopping_feed_title',
		'google_shopping_feed_description',
		'short_title_variant',
		'gen_type',
		'cat_desc',
		'banner_image_alt_text',
		'banner_image_file',
		'banner_slug',
		'popularTag_details',
	];

	protected static function boot()
	{
		parent::boot();
		Relation::enforceMorphMap([
			'Product'  => \App\Models\Product::class,
			'Category' => \App\Models\Category::class,
			'Brand'    => \App\Models\Brand::class,
			'Blog'     => \App\Models\Blog::class,
		]);
	}

	public function relational()
	{
		return $this->morphTo();
	}

	protected $casts = [
		'popular_tags' => 'array',
		'tags' => 'array',
		'schema' => 'array',
		'popularTag_details' => 'array', // ✅ NEW FIELD cast
	];

	public function secondaryKeywordDetails()
	{
		return $this->hasMany(SeoSecondaryKeyword::class, 'primary_keyword_id');
	}

	public function seo_secondary_keywords()
	{
		return $this->hasMany(SeoSecondaryKeyword::class, 'primary_keyword_id', 'id');
	}
}
