<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class AttributeValue extends Model implements TranslatableContract
{
	use Translatable;

	public $translatedAttributes = ['attribute_value_tr'];
	protected $fillable = ['attribute_id', 'attribute_value'];

	public function attribute()
	{
		return $this->belongsTo(Attribute::class);
	}
}
