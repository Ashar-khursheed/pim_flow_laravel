<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoManagementTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		"locale",
		"seo_management_id",
		"primary_keyword_tr",
		"title_tag_tr",
		"meta_title_tr",
		"meta_description_tr",
		"og_title_tr",
		"og_description_tr",
		"og_image_url_tr",
		"og_image_alt_text_tr",
		"og_image_name_tr",
		"paragraph_1_tr_tr",
		"paragraph_2_tr_tr",
		"paragraph_3_tr_tr",
		"paragraph_4_tr_tr",
		"banner_image_file_tr",
		"banner_image_alt_text_tr",
	];
}
