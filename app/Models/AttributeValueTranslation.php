<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeValueTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'locale',
		'attribute_value_id',
		'attribute_value_tr',
	];

}
