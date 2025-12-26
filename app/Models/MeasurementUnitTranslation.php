<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeasurementUnitTranslation extends Model
{
	public $timestamps = false;

	protected $fillable = [
		'locale',
		'measurement_unit_id',
		'name_tr',
	];

	public function measurementUnit()
	{
		return $this->belongsTo(MeasurementUnit::class);
	}
}
