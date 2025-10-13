<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
	protected $fillable = [
		'full_name',
		'phone',
		'email',
		'company_name',
		'restaurant_type',
		'files',
		'notes',
		'lead_type',
		'lead_source',
		'landing_page',
	];

	protected $casts = [
		'files' => 'array',
	];

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
