<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class ProductAttribute extends Model implements TranslatableContract
{
	use Translatable;

	public $translatedAttributes = ['attribute_value_tr'];

	protected $fillable = ['product_id', 'attribute_id', 'attribute_value', 'measurement_unit_id'];

	public function attributeDetails()
	{
		return $this->belongsTo(Attribute::class, 'attribute_id');
	}

	public function measurementUnit()
	{
		return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id');
	}
}
