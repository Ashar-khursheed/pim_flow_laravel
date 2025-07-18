<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class QuoteEmail extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'quote_id',
		'email',
	];

	public function quote()
	{
		return $this->belongsTo(Quote::class);
	}
}
