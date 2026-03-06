<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\ProductSupplier;

class QuoteProduct extends Model
{
	protected $fillable = [
		'quote_id',
		'product_id',
		'vendor_id',
		'quantity',
		'unit_price',
		'amount',
		'shipping_charge',
		'total_amount',
	];

	public function quote()
	{
		return $this->belongsTo(Quote::class);
	}

	public function product()
	{
		return $this->belongsTo(Product::class);
	}

	public function getVendorProductSupplierAttribute()
	{
		return ProductSupplier::where('product_id', $this->product_id)
		->where('vendor_id', $this->vendor_id)
		->first();
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
