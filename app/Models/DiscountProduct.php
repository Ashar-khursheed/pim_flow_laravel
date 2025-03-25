<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountProduct extends Model
{
	protected $table = 'ec_discount_products';

	protected $fillable = [
		'discount_id',
		'product_id',
	];
	public $timestamps = false;

	public function products(): BelongsTo
	{
		return $this->belongsTo(Product::class, 'product_id')->withDefault();
	}
}