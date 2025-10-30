<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'locale',
		'attribute_id',
		'name_tr',
	];
}
