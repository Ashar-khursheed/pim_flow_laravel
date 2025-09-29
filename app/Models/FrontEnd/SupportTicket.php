<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
	protected $fillable = [
		'ticket_number',
		'customer_id',
		'category_id',
		'priority_id',
		'subject',
		'description',
		'reference',
		'file_path',
		'status',
		'response_days',
		'created_by',
		'updated_by',
	];

	public function customer()
	{
		return $this->belongsTo(Customer::class, 'customer_id');
	}

	public function category()
	{
		return $this->belongsTo(SupportCategory::class, 'category_id');
	}

	public function priority()
	{
		return $this->belongsTo(SupportPriority::class, 'priority_id');
	}

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
