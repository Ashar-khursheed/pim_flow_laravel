<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryTranslation extends Model
{
	public $timestamps = false;
	protected $fillable = [
		'locale',
		'category_id',
		'name_tr',
	];

	public function category()
	{
		return $this->belongsTo(Category::class);
	}
}
