<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
	use HasFactory;

	protected $fillable = [
		'order_id',
		'shipment_number',
		'tracking_number',
		'carrier',
		'status',
		'shipping_cost',
		'estimated_delivery_date',
		'actual_delivery_date',
		'notes',
	];

	/* Relationship: Belongs to one order */
	public function order()
	{
		return $this->belongsTo(Order::class);
	}

	/* Relationship: Has many shipment products */
	public function shipmentProducts()
	{
		return $this->hasMany(ShipmentProduct::class);
	}

	/* Optional: Related tracking entries */
	public function tracking()
	{
		return $this->hasMany(OrderTracking::class);
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
