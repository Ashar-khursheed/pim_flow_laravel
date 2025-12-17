<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class Category extends Model implements TranslatableContract
{
	use Translatable;

	// public $translatedAttributes = ['name_tr'];
	public $translatedAttributes = [];

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
		'slug',
		'last_child'
	];

	public function parent()
	{
		return $this->belongsTo(Category::class, 'parent_id');
	}

	public function parentRecursive()
	{
		return $this->belongsTo(Category::class, 'parent_id')
		->select(['id', 'name', 'parent_id'])
		->with(['translations','seoUrl:id,relational_id,relational_type,url', 'parentRecursive']);
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
		return $this->hasMany(Category::class, 'parent_id')->with('childrenRecursive')->select(['id', 'name', 'parent_id']);
	}

	public function publishedChildren()
	{
		return $this->hasMany(Category::class, 'parent_id')
		->select(['id', 'name', 'parent_id', 'image', 'order', 'last_child'])
		->with(['translations', 'seoUrl:id,relational_id,relational_type,url', 'publishedChildren'])
		->withCount('products')
		->where('status', 'published')
		->orderBy('order');
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

	public function featuredProducts()
	{
		return $this->belongsToMany(
			Product::class,
			'product_categories',
			'category_id',
			'product_id'
		)->where('is_featured', 1)->where('status', 'published');
	}

	public function productIdsFromLeafCategories()
	{
		$leafIds = self::getLeafCategories($this)->where('status', 'published')->pluck('id')->toArray();

		return ProductCategory::whereIn('category_id', $leafIds)->pluck('product_id')->unique()->values();
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

		$leafIds = $leafCategories->where('status', 'published')->pluck('id')->toArray();

		// return Product::query()
		// ->join('product_categories', 'ec_products.id', '=', 'product_categories.product_id')
		// ->join('ec_brands', 'ec_products.brand_id', '=', 'ec_brands.id')
		// ->whereIn('product_categories.category_id', $leafIds)
		// ->where('ec_products.status', 'published')
		// ->select('ec_brands.id', 'ec_brands.name')
		// ->distinct()
		// ->get();

		return Brand::query()
		->join('ec_products', 'ec_brands.id', '=', 'ec_products.brand_id')
		->join('product_categories', 'ec_products.id', '=', 'product_categories.product_id')
		->whereIn('product_categories.category_id', $leafIds)
		->where('ec_products.status', 'published')
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