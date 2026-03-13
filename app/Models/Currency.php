<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
	protected $fillable = [
		'title',
		'symbol',
		'major_unit_name',
		'minor_unit_name',
		'is_default',
		'created_by',
		'updated_by',
	];

	/**
	 * Get the user who created this currency
	 */
	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	/**
	 * Get the user who last updated this currency
	 */
	public function updater()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	/**
	 * Get countries using this currency
	 */
	public function countries()
	{
		return $this->hasMany(Country::class, 'currency_id');
	}

	/**
	 * Boot method to auto-set created_by and updated_by
	 */
	protected static function boot()
	{
		parent::boot();

		static::creating(function ($model) {
			if (auth()->check()) {
				$model->created_by = auth()->id();
				$model->updated_by = auth()->id();
			}
		});

		static::updating(function ($model) {
			if (auth()->check()) {
				$model->updated_by = auth()->id();
			}
		});
	}

	/**
	 * Scope to get only default currency
	 */
	public function scopeDefault($query)
	{
		return $query->where('is_default', 1);
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
