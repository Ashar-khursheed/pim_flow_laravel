<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeasurementUnit extends Model
{
	public function type()
	{
		return $this->belongsTo(MeasurementType::class, 'measurement_type_id');
	}

	public function measurementUnitAttributes()
	{
		return $this->belongsToMany(Attribute::class, 'attribute_measurements')->using(AttributeMeasurement::class);
	}
}
