<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSupplier extends Model
{
	protected $fillable = [
		'product_id',
		'vendor_id',
		'vendor_sku',
		'list_price',
		'multiple',
		'cost_per_item',
		'surcharge',
		'additional_cost',
		'total_cost_per_item',
		'map',
		'sale_price',
		'price',
		'inventory',
		'in_stock',
		'delivery_days',
		'return_policy',
		'free_shipping',
		'shipping_charge',
		'margin',
		'restocking_fees',
		'warranty_information',
		'created_by',
		'updated_by',
	];

	public function product()
	{
		return $this->belongsTo(Product::class, 'product_id');
	}

	public function vendor()
	{
		return $this->belongsTo(Vendor::class, 'vendor_id');
	}
}
