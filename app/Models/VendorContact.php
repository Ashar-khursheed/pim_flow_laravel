<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorContact extends Model
{
	protected $fillable = [
		'type',
		'name',
		'mobile_number',
		'email',
	];
}
