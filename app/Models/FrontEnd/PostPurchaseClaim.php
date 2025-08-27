<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class PostPurchaseClaim extends Model
{
	protected $fillable = [
		'customer_id',
		'order_id',
		'order_product_id',
		'competitor_product_url',
		'competitor_product_price',
		'competitor_product_shipping_charge',
		'competitor_screenshot_url',
	];

	public function customer()
	{
		return $this->belongsTo(Customer::class);
	}

	public function order()
	{
		return $this->belongsTo(Order::class);
	}

	public function orderProduct()
	{
		return $this->belongsTo(OrderProduct::class);
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