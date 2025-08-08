<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempProductStatus extends Model
{
	protected $fillable = [
		'name',
		'code',
		'step_number',
	];
}
