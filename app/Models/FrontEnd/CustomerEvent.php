<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class CustomerEvent extends Model
{
	protected $fillable = [
		'event_type',
		'page_url',
		'element',
		'customer_id',
		'session_id',
		'ip_address',
		'user_agent',
		'extra_data',
	];

	protected $casts = [
		'extra_data' => 'array',
	];

	public function customer()
	{
		return $this->belongsTo(Customer::class);
	}
}
