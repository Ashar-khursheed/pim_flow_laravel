<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Country;
use App\Models\State;
use App\Models\City;

class CustomerAddress extends Model
{
	protected $table = 'customer_addresses';

	protected $fillable = [
		'customer_id',
		'type',
		'country',
		'state',
		'city',
		'address',
		'zip_code',
		'is_default',
		'created_by',
	];

	public function customer()
	{
		return $this->belongsTo(Customer::class);
	}

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function country()
	{
		return $this->belongsTo(Country::class, 'country', 'name');
	}

	// public function state()
	// {
	// 	return $this->belongsTo(State::class, 'state_id');
	// }

	// public function city()
	// {
	// 	return $this->belongsTo(City::class, 'city_id');
	// }

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