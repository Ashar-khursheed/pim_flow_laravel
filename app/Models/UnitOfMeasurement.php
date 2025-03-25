<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitOfMeasurement extends Model
{
	protected $fillable = ['name'];

	public function products()
	{
		return $this->hasMany(Product::class, 'unit_of_measurement_id');
	}
}
