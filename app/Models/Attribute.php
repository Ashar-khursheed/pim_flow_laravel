<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
	protected $fillable = ['name', 'code', 'type', 'is_required', 'validations'];

	/* Relation with AttributeGroup */
	public function attributeGroups()
	{
		return $this->belongsToMany(
			AttributeGroup::class,
			'attribute_group_attributes',
			'attribute_id',
			'attribute_group_id'
		);
	}

	public function categories()
	{
		return $this->morphToMany(Category::class, 'relational', 'attribute_group_categories', 'relational_id', 'category_id');
	}

	/* Relation with AttributeValue */
	public function attributeValues()
	{
		return $this->hasMany(AttributeValue::class, 'attribute_id');
	}
}
