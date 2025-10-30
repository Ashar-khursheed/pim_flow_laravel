<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'locale',
		'faq_id',
		'question_tr',
		'answer_tr',
	];
}
