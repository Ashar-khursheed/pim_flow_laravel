<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
	protected $table = 'ec_product_categories';

	protected $fillable = [
		'name', 'parent_id', 'description', 'status', 'order',
		'image', 'is_featured', 'icon', 'icon_image', 'slug'
	];

	public function parent()
	{
		return $this->belongsTo(Category::class, 'parent_id');
	}

	public function scopeLastChildCategories($query, $parentId)
	{
		return $query->where('parent_id', '!=', 0)
		->whereNotIn('id', function ($subQuery) {
			$subQuery->select('parent_id')
			->from('ec_product_categories')
			->whereNotNull('parent_id');
		})
		->whereHas('parent', function ($parentQuery) use ($parentId) {
			$parentQuery->where('parent_id', $parentId);
		});
	}

	public function children()
	{
		return $this->hasMany(Category::class, 'parent_id');
	}

	public function attributes()
	{
		return $this->morphedByMany(Attribute::class, 'relational', 'attribute_group_categories', 'category_id', 'relational_id');
	}

	public function attributeGroups()
	{
		return $this->morphedByMany(AttributeGroup::class, 'relational', 'attribute_group_categories', 'category_id', 'relational_id');
	}

	public static function getLeafCategories($category)
	{
		if ($category->children->isEmpty()) {
			return collect([$category]);
		}

		return $category->children->flatMap(function ($child) {
			return self::getLeafCategories($child);
		});
	}
}
