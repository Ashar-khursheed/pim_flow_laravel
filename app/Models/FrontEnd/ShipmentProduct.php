<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentProduct extends Model
{
	use HasFactory;

	protected $fillable = [
		'shipment_id',
		'order_product_id',
		'quantity',
	];

	protected $casts = [
		'quantity' => 'integer',
	];

	/* Relationship: Belongs to a shipment */
	public function shipment()
	{
		return $this->belongsTo(Shipment::class);
	}

	/* Relationship: Belongs to an order product */
	public function orderProduct()
	{
		return $this->belongsTo(OrderProduct::class, 'order_product_id');
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
