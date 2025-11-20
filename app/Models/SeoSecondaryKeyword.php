<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSecondaryKeyword extends Model
{
	protected $fillable = [
		'primary_keyword_id',
		'secondary_keyword',
		'monthly_search_volume',
	];

	public $timestamps = false;

	public function seoManagement()
	{
		return $this->belongsTo(SeoManagement::class, 'primary_keyword_id');
	}
	
}
