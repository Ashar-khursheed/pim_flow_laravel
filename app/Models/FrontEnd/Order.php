<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
	use SoftDeletes;

	protected $fillable = [
		'order_number',
		'customer_id',
		'customer_address',
		'status',
		'shipping_charge',
		'total_amount',
		'total_products',
		'ship_all_at_once',
		'separate_deliveries',
		'is_paid',
		'paid_amount',
		'pending_amount',
		'created_by',
		'updated_by',
	];

	public function products()
	{
		return $this->hasMany(OrderProduct::class);
	}

	public function tracking()
	{
		return $this->hasMany(OrderTracking::class);
	}

	/* Accessor: Payment Status */
	public function getPaymentStatusAttribute(): string
	{
		if ($this->paid_amount >= $this->total_amount) {
			return 'Fully Paid';
		} elseif ($this->paid_amount > 0) {
			return 'Partially Paid';
		}
		return 'Unpaid';
	}

	/* Accessor: Fully Delivered */
	public function getIsFullyDeliveredAttribute(): bool
	{
		return $this->products->every(function ($item) {
			return $item->shipped_quantity >= $item->quantity;
		});
	}
}
