<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeGroupCategory extends Model
{
	protected $fillable = ['category_id', 'relational_id', 'relational_type'];

	/* Define the polymorphic relationship */
	public function relational()
	{
		return $this->morphTo();
	}

	/* Define the category relationship */
	public function category()
	{
		return $this->belongsTo(Category::class, 'category_id');
	}
}
