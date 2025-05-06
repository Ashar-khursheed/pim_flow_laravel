<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Category",
 *     title="Category",
 *     description="Product Category model",
 *     type="object",
 *     required={"id", "name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Electronics"),
 *     @OA\Property(property="description", type="string", example="All electronic devices"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="image", type="string", example="https://example.com/category.jpg"),
 *     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="slug", type="string", example="electronics"),
 *     @OA\Property(property="children", type="array", @OA\Items(ref="#/components/schemas/Category"))
 * )
 */
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

	public function childrenRecursive()
	{
		return $this->hasMany(Category::class, 'parent_id')->with('childrenRecursive')->select(['id', 'name', 'slug', 'parent_id']);
	}

	// public function categoryAttributes()
	// {
	// 	return $this->morphedByMany(Attribute::class, 'relational', 'attribute_group_categories', 'category_id', 'relational_id');
	// }

	public function attributeGroups()
	{
		return $this->morphedByMany(AttributeGroup::class, 'relational', 'attribute_group_categories', 'category_id', 'relational_id');
	}

	public function categoryAttributeGroups()
	{
		return $this->belongsToMany(
			AttributeGroup::class,
			'category_attribute_groups',
			'category_id',
			'attribute_group_id'
		);
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

	/* Get unique attributes associated with the product's latest category */
	public function categoryAllAttributes()
	{
		/* Fetch attributes from groups */
		$groupAttributes = $this->attributeGroups->flatMap->groupAttributes;

		/* Fetch direct attributes */
		// $directAttributes = $this->categoryAttributes;

		/* Merge and return unique attributes */
		// return $groupAttributes->merge($directAttributes)->unique('id')->values();
		return $groupAttributes->unique('id')->values();
	}

	public function categorySeoDetails()
	{
		return $this->morphOne(SeoManagement::class, 'relational');
	}

	public function subCategories()
	{
		return $this->hasMany(SubCategory::class);
	}


}