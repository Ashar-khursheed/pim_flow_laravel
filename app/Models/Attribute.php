<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class Attribute extends Model implements TranslatableContract
{
	use Translatable;

	public $translatedAttributes = ['name_tr'];

	protected $fillable = ['name', 'code', 'type', 'attribute_group_id', 'validations', 'created_by', 'updated_by','images'];

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
		return $this->belongsToMany(MeasurementUnit::class, 'attribute_measurements')->using(AttributeMeasurement::class);
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
