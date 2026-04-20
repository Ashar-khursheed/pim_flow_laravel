<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
	protected $guarded = [];

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function updator()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function country()
	{
		return $this->belongsTo(Country::class);
	}

	public function city()
	{
		return $this->belongsTo(City::class);
	}

	public function vendorContacts()
	{
		return $this->hasMany(VendorContact::class);
	}

	public function vendorProducts()
	{
		return $this->hasMany(ProductSupplier::class);
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
