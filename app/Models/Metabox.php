<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Metabox extends Model
{
	protected $table = 'meta_boxes';

	protected $fillable = [
		'meta_key',
		'meta_value',
		'reference_id',
		'reference_type',
	];

	protected $casts = [
		'meta_value' => 'json',
	];

	public function reference()
	{
		return $this->morphTo();
	}
}