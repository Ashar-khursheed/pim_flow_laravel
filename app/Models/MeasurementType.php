<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeasurementType extends Model
{
	public function units()
	{
		return $this->hasMany(MeasurementUnit::class);
	}
}
