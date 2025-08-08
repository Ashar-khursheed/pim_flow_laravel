<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempProductAssignment extends Model
{
	protected $fillable = [
		'temp_product_id',
		'assigned_to',
		'assigned_by',
		'due_date',
		'completed_at',
		'status',
		'created_at',
	];

	public function tempProduct()
	{
		return $this->belongsTo(TempProduct::class, 'temp_product_id');
	}

	public function assignedToUser()
	{
		return $this->belongsTo(User::class, 'assigned_to');
	}

	public function assignedByUser()
	{
		return $this->belongsTo(User::class, 'assigned_by');
	}

	public function getFormattedTimeTakenAttribute()
	{
		// if (!$this->time_taken_minutes) return '-';

		// $days = floor($this->time_taken_minutes / 1440);
		// $hours = floor(($this->time_taken_minutes % 1440) / 60);
		// $minutes = $this->time_taken_minutes % 60;

		// $parts = [];

		// if ($days > 0) {
		// 	$parts[] = "{$days} days";
		// }

		// if ($days > 0 || $hours > 0) {
		// 	$parts[] = str_pad($hours, 2, '0', STR_PAD_LEFT);
		// }

		// $parts[] = str_pad($minutes, 2, '0', STR_PAD_LEFT);

		// return $days > 0 || $hours > 0
		// 	? implode(' ', $parts)
		// 	: $parts[0];
	}
}
