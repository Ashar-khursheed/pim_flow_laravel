<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSupplier extends Model
{
	protected $fillable = [
		'product_id',
		'vendor_id',
		'vendor_sku',
		'warranty_information',
		'refund',
		'delivery_days',
		'cost_per_item',
		'sale_price',
		'price',
		'margin',
		'additional_cost',
		'final_cost_price',
		'in_stock',
		'inventory',
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
