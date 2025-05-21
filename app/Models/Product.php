<?php

namespace App\Models;
use App\Models\Slug;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @OA\Schema(
 *     schema="Product",
 *     title="Product",
 *     description="Product model",
 *     type="object",
 *     required={"id", "name", "price"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="iPhone 14"),
 *     @OA\Property(property="description", type="string", example="Latest iPhone with A16 chip"),
 *     @OA\Property(property="price", type="number", format="float", example=999.99),
 *     @OA\Property(property="sku", type="string", example="IPH14-256GB-BLK"),
 *     @OA\Property(property="quantity", type="integer", example=100),
 *     @OA\Property(property="image", type="string", example="https://example.com/product.jpg"),
 *     @OA\Property(property="categories", type="array", @OA\Items(ref="#/components/schemas/Category")),
 *     @OA\Property(property="brand", ref="#/components/schemas/Brand")
 * )
 */
class Product extends Model
{
	protected $table = 'ec_products';

	protected $fillable = [
		'name',
		'website_id',
		'description',
		'content',
		'image',
		'images',
		'sku',
		'order',
		'quantity',
		'allow_checkout_when_out_of_stock',
		'with_storehouse_management',
		'is_featured',
		'brand_id',
		'is_variation',
		'sale_type',
		'price',
		'sale_price',
		'start_date',
		'end_date',
		'length',
		'length_unit_id',
		'width',
		'height',
		'depth',
		'shipping_height',
		'shipping_length',
		'shipping_length_id',
		'shipping_depth',
		'shipping_width',
		'weight',
		'weight_unit_id',
		'tax_id',
		'views',
		'stock_status',
		'barcode',
		'cost_per_item',
		//'generate_license_code',
		'minimum_order_quantity',
		'maximum_order_quantity',
		'specs_sheet_heading',
		'specs_sheet',
		'documents',
		'video_url',
		'video_path',
		'warranty_information',
		'unit_of_measurement_id',
		'shipping_weight_option' => 'nullable|string',
		'shipping_weight' => 'nullable|numeric',
		'shipping_dimension_option' => 'nullable|string',
		'shipping_width' => 'nullable|numeric',
		'shipping_width_id' => 'nullable|exists:units,id',
		'shipping_depth' => 'nullable|numeric',
		'shipping_depth_id' => 'nullable|exists:units,id',
		'shipping_height' => 'nullable|numeric',
		'shipping_height_id' => 'nullable|exists:units,id',
		'shipping_length' => 'nullable|numeric',
		'shipping_length_id' => 'nullable|exists:units,id',
		'store_id',
		'refund_policy',
		'delivery_days',
		'box_quantity',
		'frequently_bought_together' => 'array', // Adjust as needed
		'compare_type' => 'array', // Cast JSON to array
		'compare_products' => 'array', // Cast JSON to array
		'variant_1_title' => 'nullable|string|max:255',
		'variant_1_value' => 'nullable|string|max:255',
		'variant_1_products' => 'nullable|string', // Can be comma-separated IDs

		' variant_color_title' => 'nullable|string|max:255',
		'variant_color_value' => 'nullable|string|max:255',
		'variant_color_products' => 'nullable|string',

		'variant_2_title' => 'nullable|string|max:255',
		'variant_2_value' => 'nullable|string|max:255',
		'variant_2_products' => 'nullable|string',

		'variant_3_title' => 'nullable|string|max:255',
		'variant_3_value' => 'nullable|string|max:255',
		'variant_3_products' => 'nullable|string',
		'google_shopping_category',
		'benefits_features' => 'array',

	];

	public function categories()
	{
		return $this->belongsToMany(
			Category::class,
			'ec_product_category_product',
			'product_id',
			'category_id'
		);
	}

	public function currency()
	{
		return $this->belongsTo(Currency::class, 'currency_id');
	}

	public function lengthUnit()
	{
		return $this->belongsTo(Unit::class, 'length_unit_id');
	}

	public function weightUnit()
	{
		return $this->belongsTo(Unit::class, 'weight_unit_id');
	}

	public function shippingLengthUnit()
	{
		return $this->belongsTo(Unit::class, 'shipping_length_id');
	}

	public function store()
	{
		return $this->belongsTo(Store::class, 'store_id');
	}

	public function brand()
	{
		return $this->belongsTo(Brand::class, 'brand_id');
	}

	public function creator()
	{
		return $this->morphTo();
	}

	public function tags()
	{
		return $this->belongsToMany(Tag::class, 'ec_product_tag_product', 'product_id', 'tag_id');
	}

	public function seoMetaData()
	{
		return $this->morphOne(Metabox::class, 'reference')->where('meta_key', 'seo_meta');
	}

	public function seoManagement()
	{
		return $this->morphOne(SeoManagement::class, 'relational');
	}

	public function specifications()
	{
		return $this->hasMany(Specification::class);
	}

	public function slug()
	{
		return $this->hasOne(Slug::class, 'reference_id')->where('prefix', 'products');
	}

	public function arTranslations()
	{
		return $this->hasOne(ProductTranslation::class, 'ec_products_id')->where('lang_code', 'ar');
	}

	// public function attributes()
	// {
	// 	return $this->belongsToMany(
	// 		Attribute::class,
	// 		'product_attributes',
	// 		'product_id',
	// 		'attribute_id'
	// 	);
	// }

	public function productAttributes()
	{
		return $this->hasMany(ProductAttribute::class);
	}

	public function discounts(): BelongsToMany
	{
		return $this->belongsToMany(Discount::class, 'ec_discount_products', 'product_id', 'discount_id');
	}

	/* Get the latest category associated with the product */
	public function latestChildCategory()
	{
		return $this->categories()->whereDoesntHave('children')->orderByDesc('ec_product_category_product.created_at')->orderByDesc('ec_product_category_product.category_id')->first();
	}

	public function latestChildCategoryRelation()
	{
		return $this->belongsToMany(Category::class, 'ec_product_category_product', 'product_id', 'category_id')
		->whereDoesntHave('children')
		->orderByDesc('ec_product_category_product.created_at')
		->orderByDesc('ec_product_category_product.category_id')
		->limit(1); // This returns a relation, usable in `with()`
	}

	/* Get unique attributes associated with the product's latest category */
	public function productCategoryAttributes()
	{
		$category = $this->latestChildCategory();
		if (!$category) {
			return collect();
		}

		/* Fetch attributes from groups */
		// $groupAttributes = $category->attributeGroups->flatMap->groupAttributes;

		// /* Fetch direct attributes */
		// $directAttributes = $category->categoryAttributes;

		// /* Merge and return unique attributes */
		// return $groupAttributes->merge($directAttributes)->unique('id')->values();
		$groupAttributes = $category->attributeGroups->flatMap->groupAttributes ?? [];

		return $groupAttributes->unique('id')->values();
	}

	// public function seo()
	// {
	// 	return $this->hasOne(SeoSchema::class, 'type_id')->where('type', 'product');
	// }

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

	public function unitOfMeasurement()
	{
		return $this->belongsTo(UnitOfMeasurement::class, 'unit_of_measurement_id');
	}

	public function productSuppliers()
	{
		return $this->hasMany(ProductSupplier::class, 'product_id');
	}


}