<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeGroup extends Model
{
	// protected $fillable = ['name'];
	protected $guarded = [];

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function groupsAttributes()
	{
		return $this->hasMany(Attribute::class, 'attribute_group_id', 'id');
		// return $this->hasMany(Attribute::class);
	}

	public function categories()
	{
		return $this->belongsToMany(
			Category::class,
			'category_attribute_groups',
			'attribute_group_id',
			'category_id'
		);
	}
}
