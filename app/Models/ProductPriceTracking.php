<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceTracking extends Model
{
	protected $fillable = [
		'product_price_id',
		'field',
		'old_value',
		'new_value',
		'created_by',
	];

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function productPrice()
	{
		return $this->belongsTo(ProductSupplier::class, 'product_price_id');
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
