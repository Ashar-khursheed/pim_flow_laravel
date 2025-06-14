<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FrontEnd\Customer;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\OrderTracking;
use App\Models\FrontEnd\Payment;
use App\Models\User;

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

	public function orderProducts()
	{
		return $this->hasMany(OrderProduct::class);
	}

	public function tracking()
	{
		return $this->hasMany(OrderTracking::class);
	}

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

	public function shipments()
	{
		return $this->hasMany(Shipment::class);
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
