<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\FrontEnd\Order;

class Utm extends Model
{
	protected $fillable = [
		'utm_source', 'utm_medium', 'utm_campaign',
		'utm_term', 'utm_content', 'gclid', 'session_id',
	];

	public function orders()
	{
		return $this->hasMany(Order::class, 'utm_id');
	}
}
