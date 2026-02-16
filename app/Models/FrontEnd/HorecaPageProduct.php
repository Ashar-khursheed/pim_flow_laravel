<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Relations\Pivot;

class HorecaPageProduct extends Pivot
{
	/**
	 * Indicates if the IDs are auto-incrementing.
	 */
	public $incrementing = true; /* ADD THIS */

	/**
	 * The attributes that are mass assignable.
	 */
	protected $fillable = [
		'horeca_page_id',
		'horeca_page_product_type_id',
		'product_id',
		'order',
	];
}
