<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class PrePurchaseClaim extends Model
{
	protected $fillable = [
		'customer_id',
		'customer_address_id',
		'product_id',
		'product_quantity',
		'competitor_product_url',
		'competitor_product_price',
		'competitor_product_shipping_charge',
		'competitor_screenshot_url',
	];

	public function product()
	{
		return $this->belongsTo(Product::class);
	}

	public function customer()
	{
		return $this->belongsTo(Customer::class);
	}

	public function customerAddress()
	{
		return $this->belongsTo(CustomerAddress::class);
	}
}