<?php

namespace App\Models;
use App\Models\Slug;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use OpenApi\Annotations as OA;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Frontend\Wishlist; // Add this at the top of your Product model
use App\Models\Frontend\ProductQuestion;
use App\Models\FrontEnd\AlternateProduct;
use App\Models\SeoManagement;
/**
 * @OA\Schema(
 *     schema="Product",
 *     title="Product",
 *     description="Product model",
 *     type="object",
 *     required={"id", "name", "price"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="iPhone 14"),
 *     @OA\Property(property="description", type="string", example="Latest iPhone with A16 chip"),
 *     @OA\Property(property="price", type="number", format="float", example=999.99),
 *     @OA\Property(property="original_price", type="number", format="float", example=100.00),
 *     @OA\Property(property="sale_price", type="number", format="float", example=80.00),
 *     @OA\Property(property="front_sale_price", type="number", format="float", example=80.00),
 *     @OA\Property(property="sku", type="string", example="IPH14-256GB-BLK"),
 *     @OA\Property(property="quantity", type="integer", example=100),
 *     @OA\Property(property="leftStock", type="integer", example=50),
 *     @OA\Property(property="image", type="string", example="https://example.com/product.jpg"),
 *     @OA\Property(property="images", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="video_url", type="string", example="https://example.com/video.mp4"),
 *     @OA\Property(property="video_path", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="start_date", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="end_date", type="string", format="date-time", example="2024-12-31T23:59:59Z"),
 *     @OA\Property(property="warranty_information", type="string", example="1 Year Warranty"),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="currency_title", type="string", example="$"),
 *     @OA\Property(property="total_reviews", type="integer", example=10),
 *     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
 *     @OA\Property(property="best_price", type="number", format="float", example=80.00),
 *     @OA\Property(property="delivery_days", type="string", nullable=true),
 *     @OA\Property(property="in_wishlist", type="boolean", example=true),
 * )
 */

class Product extends Model implements TranslatableContract
{
	use Translatable;

	// public $translatedAttributes = [
	// 	'name_tr',
	// 	'description_tr',
	// 	'benefits_features_tr',
	// 	'images_tr',
	// ];

	public $translatedAttributes = [];

	public static $observerUserId = null;
	protected $table = 'ec_products';

	protected $fillable = [
		'name',
		'website_ids',
		'description',
		'images',
		'sku',
		'order',
		'quantity',
		'is_featured',
		'brand_id',
		'quote_available',
		'tax_id',
		'views',
		'stock_status',
		'barcode',
		'specs_sheet_heading',
		'specs_sheet',
		'documents',
		'video_path',
		'frequently_bought_together' => 'array',
		'benefits_features' => 'array',
		'gen_type' => 'nullable|integer',
		'approved' => 'nullable|integer',
		'ar_approved' => 'nullable|integer'

	];

	public function categories()
	{
		return $this->belongsToMany(
			Category::class,
			'product_categories',
			'product_id',
			'category_id'
		);
	}

	public function currency()
	{
		return $this->belongsTo(Currency::class, 'currency_id');
	}

	public function vendors()
	{
		return $this->belongsToMany(Vendor::class, 'product_suppliers', 'product_id', 'vendor_id')->withPivot(['price', 'sale_price']);
	}

	public function brand()
	{
		return $this->belongsTo(Brand::class, 'brand_id');
	}

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function tags()
	{
		return $this->belongsToMany(Tag::class, 'product_tags', 'product_id', 'tag_id');
	}

	public function seoMetaData()
	{
		return $this->morphOne(Metabox::class, 'reference')->where('meta_key', 'seo_meta');
	}

	// public function seoManagement()
	// {
	// 	return $this->morphOne(SeoManagement::class, 'relational');
	// }
public function seoManagement()
{
    return $this->hasOne(SeoManagement::class, 'relational_id')
                ->where('relational_type', 'Product');
}
	public function slug()
	{
		return $this->hasOne(Slug::class, 'reference_id')->where('prefix', 'products');
	}
	public function seoUrl()
	{
		return $this->hasOne(SeoManagement::class, 'relational_id', 'id');
	}

	public function seoProductUrl()
	{
		return $this->hasOne(SeoManagement::class, 'relational_id', 'id')
		->where(function ($query) {
			$query->where('relational_type', 'Product')
			->orWhere('relational_type', static::class);
		});
	}

	public function productAttributes()
	{
		return $this->hasMany(ProductAttribute::class);
	}

	public function sellingUnitAttribute()
	{
		return $this->hasOne(ProductAttribute::class)
		->whereHas('attributeDetails', function ($query) {
			$query->where('name', 'Selling Unit');
		});
	}

	public function warrantyAttribute()
	{
		return $this->hasOne(ProductAttribute::class)
		->whereHas('attributeDetails', function ($query) {
			$query->where('name', 'Warranty');
		});
	}

	public function ingredientsAttribute()
	{
		return $this->hasOne(ProductAttribute::class)
		->whereHas('attributeDetails', function ($query) {
			$query->where('name', 'Ingredients');
		});
	}

	// public function discounts(): BelongsToMany
	// {
	// 	return $this->belongsToMany(Discount::class, 'ec_discount_products', 'product_id', 'discount_id');
	// }

	/* Get the latest category associated with the product */
	public function latestChildCategory()
	{
		return $this->categories()->whereDoesntHave('children')->orderByDesc('product_categories.created_at')->orderByDesc('product_categories.category_id')->first();
	}

	public function latestChildCategoryRelation()
	{
		return $this->belongsToMany(Category::class, 'product_categories', 'product_id', 'category_id')
		->whereDoesntHave('children')
		->orderByDesc('product_categories.created_at')
		->orderByDesc('product_categories.category_id')
		->limit(1);
	}

	/* Get unique attributes associated with the product's latest category */
	public function productCategoryAttributes()
	{
		$category = $this->latestChildCategory();
		if (!$category) {
			return collect();
		}

		$categoryAttributes = $category->categoryAttributeGroups->flatMap->groupsAttributes ?? [];

		return $categoryAttributes->unique('id')->values();
	}

	public function seo()
	{
		return $this->hasOne(SeoSchema::class, 'product_id', 'id');
	}

	public function seoSchema()
	{
		return $this->hasOne(SeoSchema::class, 'product_id', 'id');
	}
	public function tax()
	{
		return $this->belongsTo(Tax::class, 'tax_id');
	}

	public function reviews()
	{
		return $this->hasMany(Review::class, 'product_id');
	}

	public function faqs()
	{
		return $this->hasMany(Faq::class, 'product_id');
	}

	public function productSuppliers()
	{
		return $this->hasMany(ProductSupplier::class, 'product_id');
	}
	public function productVariants()
	{
		return $this->hasMany(ProductVariant::class, 'parent_id');
	}

	public function wishlist()
	{
		return $this->hasMany(Wishlist::class, 'product_id');
	}

	public function isInWishlist($customerId)
	{
		if ($customerId) {
			return $this->wishlist()->where('user_id', $customerId)->exists();
		}
		return false;
	}

	public function slugData()
	{
		return $this->morphOne(Slug::class, 'reference')->where('prefix', 'products');
	}
	public function questions()
	{
		return $this->hasMany(ProductQuestion::class);
	}
	public function alternateProducts()
	{
		return $this->hasMany(AlternateProduct::class, 'product_id', 'id');
	}

	public function category_url()
	{

		$category = $this->latestChildCategory();
		if ($category && $category->seoUrl) {
			return $category->seoUrl->url;
		}

		return null;
	}

	public function parent_category_url()
	{
		$category = $this->latestChildCategory();
		$mostParent = $category?->most_parent;

		return $mostParent && $mostParent->seoUrl
		? $mostParent->seoUrl->url
		: null;
	}

	// In Product.php
	// In Product.phps
	public function accessories()
	{
		return $this->hasMany(ProductAccessory::class, 'product_id')->approved();
	}

	public function getIsRequiredAttribute()
	{
		return $this->accessories()->where('isRequired', 1)->exists();
	}



}