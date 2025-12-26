<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttributeTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'locale',
		'product_attribute_id',
		'attribute_value_tr',
	];
}
