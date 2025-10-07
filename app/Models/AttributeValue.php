<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class AttributeValue extends Model implements TranslatableContract
{
	// use Translatable;

	// public $translatedAttributes = ['title'];
	protected $fillable = ['attribute_id', 'attribute_value'];
}
