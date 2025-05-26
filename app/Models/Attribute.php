<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
	// protected $guarded = [];
	// protected $fillable = ['name', 'code', 'type', 'is_required', 'validations'];
	protected $fillable = ['name', 'code', 'type', 'attribute_group_id','validations'];

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function updator()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function attributeGroup()
	{
		return $this->belongsTo(AttributeGroup::class);
	}

	/* Relation with AttributeValue */
	public function attributeValues()
	{
		return $this->hasMany(AttributeValue::class, 'attribute_id');
	}

	public function measurementUnits()
	{
		return $this->belongsToMany(
			MeasurementUnit::class,
			'attribute_measurements',
			'attribute_id',
			'measurement_unit_id'
		);
	}
}
