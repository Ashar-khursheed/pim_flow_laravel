<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class AttributeGroup extends Model implements TranslatableContract
{
	use Translatable;

	public $translatedAttributes = ['name_tr'];

	protected $fillable = ['name'];

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
		// return $this->belongsToMany(
		// 	Category::class,
		// 	'category_attribute_groups',
		// 	'attribute_group_id',
		// 	'category_id'
		// );
		return $this->belongsToMany(Category::class, 'category_attribute_groups')->using(CategoryAttributeGroup::class);
	}
}
