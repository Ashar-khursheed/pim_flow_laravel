<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeGroup extends Model
{
	protected $guarded = [];

	public function attributes()
	{
		return $this->belongsToMany(
			Attribute::class,
			'attribute_group_attributes',
			'attribute_group_id',
			'attribute_id'
		);
	}

	public function categories()
	{
		return $this->morphToMany(Category::class, 'relational', 'attribute_group_categories', 'relational_id', 'category_id');
	}
}
