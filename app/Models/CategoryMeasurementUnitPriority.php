<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryMeasurementUnitPriority extends Model
{
	protected $table = 'category_measurement_unit_priorities';

	protected $fillable = [
		'measurement_type_id',
		'category_id',
		'measurement_unit_primary_id',
		'measurement_unit_secondary_id',
		'created_by',
		'updated_by',
	];

	/* Relationships */

	public function measurementType()
	{
		return $this->belongsTo(MeasurementType::class, 'measurement_type_id');
	}

	public function category()
	{
		return $this->belongsTo(Category::class, 'category_id');
	}

	public function primaryMeasurementUnit()
	{
		return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_primary_id');
	}

	public function secondaryMeasurementUnit()
	{
		return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_secondary_id');
	}

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function updater()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	/**
	 * Prepare a date for array / JSON serialization.
	 *
	 * @param  \DateTimeInterface  $date
	 * @return string
	 */
	protected function serializeDate(\DateTimeInterface $date)
	{
		return $date->format('Y-m-d H:i:s');
	}
}
