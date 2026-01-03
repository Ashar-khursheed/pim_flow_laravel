<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class MeasurementUnit extends Model implements TranslatableContract
{
	use Translatable;

	public $translatedAttributes = [];
	// public $translatedAttributes = ['name_tr'];

	public function type()
	{
		return $this->belongsTo(MeasurementType::class, 'measurement_type_id');
	}

	public function measurementUnitAttributes()
	{
		return $this->belongsToMany(Attribute::class, 'attribute_measurements')->using(AttributeMeasurement::class);
	}

	public function productAttributes()
	{
		return $this->hasMany(ProductAttribute::class, 'measurement_unit_id');
	}
}
