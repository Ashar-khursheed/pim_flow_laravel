<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class GetInTouch extends Model
{
	protected $fillable = [
		'name',
		'email',
		'phone',
		'topic',
		'order_number',
		'image_url',
		'message',
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
