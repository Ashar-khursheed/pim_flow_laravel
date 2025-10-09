<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppKeywordTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'locale',
		'app_keyword_id',
		'title',
	];
}
