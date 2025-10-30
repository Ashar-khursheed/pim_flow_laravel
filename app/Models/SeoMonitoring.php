<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMonitoring extends Model
{
	protected $fillable = [
		'date',
		'url',
		'keyword',
		'country',
		'device',
		'total_clicks',
		'impressions',
		'click_rate',
		'position',		 
		'relational_type',		 
	];
}
