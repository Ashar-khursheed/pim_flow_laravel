<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoManagement extends Model
{
	protected $guarded = [];

	public function relational()
	{
		return $this->morphTo();
	}

	public function secondaryKeywordDetails()
	{
		return $this->hasMany(SeoSecondaryKeyword::class, 'primary_keyword_id');
	}
}
