<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Country;

class CustomerCart extends Model
{
	protected $fillable = [
		'reference_number',
		'customer_id',
		'customer_address_id',
		'country',
		'shipping_charge',
		'is_lift_gate',
		'is_residential_address',
		'is_inside_delivery',
		'amount',
		'tax_percentage',
		'tax_amount',
		'total_amount',
		'total_products',
		'additional_amount_name',
		'additional_amount_price',
		'created_by',
		'updated_by',
	];

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function updator()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function customer()
	{
		return $this->belongsTo(Customer::class);
	}

	public function customerAddress()
	{
		return $this->belongsTo(CustomerAddress::class);
	}

	public function countryRelation()
	{
		return $this->belongsTo(Country::class, 'country', 'name');
	}

	public function customerCartProducts()
	{
		return $this->hasMany(CustomerCartProduct::class);
	}
}
