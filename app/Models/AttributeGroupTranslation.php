<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeGroupTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'locale',
		'attribute_group_id',
		'name_tr',
	];
}
