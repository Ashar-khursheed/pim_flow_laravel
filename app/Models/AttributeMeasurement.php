<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AttributeMeasurement extends Pivot
{
	protected $fillable = ['attribute_id', 'measurement_unit_id'];
}
