<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class HorecaPageProductType extends Model
{
	protected $fillable = [
		'horeca_page_id',
		'type',
		'description',
		'order',
	];

	/**
	 * Get the horeca page that owns this product type.
	 */
	public function horecaPage()
	{
		return $this->belongsTo(HorecaPage::class, 'horeca_page_id');
	}

	/**
	 * Get the products for this type.
	 */
	public function products()
	{
		return $this->belongsToMany(
			Product::class,
			'horeca_page_products',
			'horeca_page_product_type_id',
			'product_id'
		)->withPivot('order')->orderBy('horeca_page_products.order');
	}
}