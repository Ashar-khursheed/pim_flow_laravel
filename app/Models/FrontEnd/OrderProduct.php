<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
	protected $fillable = [
		'order_id',
		'product_id',
		'vendor_id',
		'quantity',
		'shipped_quantity',
		'remaining_quantity',
		'unit_price',
		'total_amount',
		'status',
	];

	protected $casts = [
		'quantity' => 'integer',
		'shipped_quantity' => 'integer',
		'remaining_quantity' => 'integer',
		'unit_price' => 'decimal:2',
		'total_amount' => 'decimal:2',
	];

	public function order()
	{
		return $this->belongsTo(Order::class);
	}

	/* Accessor: Fully Shipped */
	public function getIsFullyShippedAttribute(): bool
	{
		return $this->shipped_quantity >= $this->quantity;
	}

	/* Accessor: Pending Quantity */
	public function getPendingQuantityAttribute(): int
	{
		return $this->quantity - $this->shipped_quantity;
	}
}
