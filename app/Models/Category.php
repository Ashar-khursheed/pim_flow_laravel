<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OpenApi\Annotations as OA;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\SeoManagement;

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
	protected $table = 'categories';

	protected $fillable = [
		'name',
		'parent_id',
		'description',
		'status',
		'order',
		'image',
		'is_featured',
		'icon',
		'icon_image',
		'slug'
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
			->from('categories')
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

	public function slug()
	{
		return $this->hasOne(Slug::class, 'reference_id')->where('prefix', 'category');
	}

	public function categoryAttributeGroups()
	{
		// return $this->belongsToMany(
		// 	AttributeGroup::class,
		// 	'category_attribute_groups',
		// 	'category_id',
		// 	'attribute_group_id'
		// );
		return $this->belongsToMany(AttributeGroup::class, 'category_attribute_groups')->using(CategoryAttributeGroup::class);
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
		/* Fetch all attributes from groups */
		$categoryAttributes = $this->categoryAttributeGroups->flatMap->groupsAttributes;
		return $categoryAttributes->unique('id')->values();
	}

	public function categorySeoDetails()
	{
		return $this->morphOne(SeoManagement::class, 'relational');
	}

	public function seoUrl()
	{
		// return $this->hasOne(SeoManagement::class, 'relational_id', 'id')
		// ->where('relational_type', 'Category');
		return $this->hasOne(SeoManagement::class, 'relational_id', 'id')
		->where(function ($query) {
			$query->where('relational_type', 'Category')
			->orWhere('relational_type', static::class);
		});
	}

	public function products()
	{
		return $this->belongsToMany(
			Product::class,
			'product_categories',
			'category_id',
			'product_id'
		);
	}

	public function productIds() {
		return $this->hasMany(ProductCategory::class, 'category_id')->pluck('product_id');
	}

	public function getAllParentsAttribute()
	{
		$parents = collect();
		$category = $this;

		while ($category->parent) {
			$category = $category->parent;
			$parents->prepend($category); // insert at beginning for top-down hierarchy
		}

		return $parents;
	}

	public function titleFormulaAttributes()
	{
		return $this->belongsToMany(
			Attribute::class,
			'product_title_formula',
			'category_id',
			'attribute_id'
		);
	}

	public function getMostParentAttribute()
	{
		$category = $this;
		while ($category->parent) {
			$category = $category->parent;
		}
		return $category;
	}

	public function categoryBrands()
	{
		return $this->belongsToMany(
			Product::class,
			'product_categories',
			'category_id',
			'product_id'
		)
		->join('ec_brands', 'ec_products.brand_id', '=', 'ec_brands.id')
		->select('ec_brands.id', 'ec_brands.name')
		->distinct();
	}

	public function allBrandsFromLeaves()
	{
		$leafCategories = self::getLeafCategories($this);

		$leafIds = $leafCategories->pluck('id')->toArray();

		return Product::query()
		->join('product_categories', 'ec_products.id', '=', 'product_categories.product_id')
		->join('ec_brands', 'ec_products.brand_id', '=', 'ec_brands.id')
		->whereIn('product_categories.category_id', $leafIds)
		->select('ec_brands.id', 'ec_brands.name')
		->distinct()
		->get();
	}


	public function category_url()
	{
		return $this->hasOne(SeoManagement::class, 'relational_id', 'id')

		->where('relational_type', 'Category');
	}

	public function getSuperParent()
	{
		$category = $this;

		while ($category->parent) {
			$category = $category->parent;
		}
		return (string) $category->id;
	}

	public function subCategory()
	{
		return $this->hasOne(SubCategory::class);
	}
}