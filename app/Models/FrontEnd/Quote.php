<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Quote extends Model
{
	protected $fillable = [
		'quote_number',
		'quote_name',
		'customer_id',
		'customer_address_id',
		'shipping_charge',

		'is_lift_gate',
		'is_residential_address',
		'is_inside_delivery',
		'amount',

		'tax_percentage',
		'tax_amount',
		'coupon_id',
		'discount',

		'additional_amount_name',
		'additional_amount_price',
		'additional_amount_details',

		'additional_discount_reason',
		'additional_discount_type',
		'additional_discount_percentage',
		'additional_discount_amount',

		'total_amount',
		'total_products',
		'payment_terms',
		'customer_notes',
		'internal_notes',
		'status',
		'expired_at',
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

	public function quoteProducts()
	{
		return $this->hasMany(QuoteProduct::class);
	}

	public function quoteEmails()
	{
		return $this->hasMany(QuoteEmail::class);
	}

	public function accessoryCharges()
	{
		return $this->morphMany(AccessoryCharge::class, 'relation');
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
