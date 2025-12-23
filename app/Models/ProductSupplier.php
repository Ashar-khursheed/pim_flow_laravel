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
		'min_quantity',
		'is_fixed',
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

	/**
	 * Scope to filter by price range
	 * Uses sale_price if > 0, otherwise uses price
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $query
	 * @param float|null $priceMin
	 * @param float|null $priceMax
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public function scopePriceRange($query, $priceMin = null, $priceMax = null)
	{
		if ($priceMin === null && $priceMax === null) {
			return $query;
		}

		$priceExpression = 'CASE WHEN sale_price > 0 THEN sale_price ELSE price END';

		if ($priceMin !== null && $priceMax !== null) {
			return $query->whereRaw("{$priceExpression} BETWEEN ? AND ?", [$priceMin, $priceMax]);
		} elseif ($priceMin !== null) {
			return $query->whereRaw("{$priceExpression} >= ?", [$priceMin]);
		} elseif ($priceMax !== null) {
			return $query->whereRaw("{$priceExpression} <= ?", [$priceMax]);
		}

		return $query;
	}

	/**
	 * Scope to get cheapest supplier
	 */
	public function scopeCheapest($query)
	{
		return $query->orderByRaw('CASE WHEN sale_price > 0 THEN sale_price ELSE price END ASC');
	}
}
