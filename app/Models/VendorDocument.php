<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorDocument extends Model
{
	protected $guarded = [];

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function vendor()
	{
		return $this->belongsTo(Vendor::class, 'vendor_id');
	}
}
