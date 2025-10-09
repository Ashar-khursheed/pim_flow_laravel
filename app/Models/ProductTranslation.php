<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'locale',
		'product_id',
		'name',
		'description',
		'benefits_features',
		'images',
	];
}
