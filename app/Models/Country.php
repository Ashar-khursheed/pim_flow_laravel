<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
	protected $table = 'countries';

	protected $fillable = [
		'name',
		'phone_code',
		'icon',
		'currency_id',
		'margin',
		'created_by',
		'updated_by',
	];

	/**
	 * Get the currency (no foreign key, manual relationship)
	 */
	public function currency()
	{
		return $this->belongsTo(Currency::class, 'currency_id');
	}

	/**
	 * Get the user who created this country
	 */
	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	/**
	 * Get the user who last updated this country
	 */
	public function updater()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}
}