<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Relations\Pivot;

class HorecaPageCategory extends Pivot
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
		'category_id',
		'order',
	];
}
