<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempProduct extends Model
{
	protected $fillable = [
		'name',
		'category_id',
		'brand_id',
		'vendor_id',
		'sku',
		'status_id',
		'created_by',
	];

	public function category()
	{
		return $this->belongsTo(Category::class, 'category_id');
	}

	public function brand()
	{
		return $this->belongsTo(Brand::class, 'brand_id');
	}

	public function vendor()
	{
		return $this->belongsTo(Vendor::class, 'vendor_id');
	}

	public function status()
	{
		return $this->belongsTo(TempProductStatus::class, 'status_id');
	}

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
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
