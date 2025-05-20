<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSupplier extends Model
{
	protected $fillable = [
		'product_id',
		'vendor_id',
		'vendor_sku',
		'cost_per_item',
		'additional_cost',
		'price',
		'sale_price',
		'inventory',
		'in_stock',
		'delivery_days',
		'warranty_information',
		'refund',
		'final_cost_price',
		'margin',
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
