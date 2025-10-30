<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'locale',
		'product_id',
		'name_tr',
		'description_tr',
		'benefits_features_tr',
		'images_tr',
	];
}
