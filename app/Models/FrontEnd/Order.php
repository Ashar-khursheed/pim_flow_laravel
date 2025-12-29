<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FrontEnd\Customer;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\OrderTracking;
use App\Models\PaymentManagement;
use App\Models\User;

class Order extends Model
{
	use SoftDeletes;

	protected $fillable = [
		'utm_id',
		'order_number',
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
		'additional_discount',
		'pay_with_cheque',
		'cheque_discount_percentage',
		'cheque_discount',
		'cheque_img',
		'cheque_img_back',
		'total_amount',
		'total_products',
		'ship_all_at_once',
		'separate_deliveries',
		'is_paid',
		'paid_amount',
		'pending_amount',
		'status',
		'payment_link',
		'is_reserved',
		'is_payment',
		'is_squarePayment',
		'is_paymob',
		'is_ccavenue',
		'is_customer_pickup',
		'is_cod',
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
		return $this->hasMany(PaymentManagement::class);
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
	// In app/Models/Order.php

	public function nofraudResponse()
	{
		return $this->hasOne(\App\Models\NoFraudResponse::class, 'order_id', 'order_number');
	}

	public function utm()
	{
		return $this->hasOne(\App\Models\Utm::class, 'id', 'utm_id');
	}

	 public function invoice()
	{
		return $this->hasOne(Invoice::class, 'order_id', 'id');
	}

}
