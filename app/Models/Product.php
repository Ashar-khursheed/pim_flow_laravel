<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'ec_products';

    protected $fillable = [
        'name',
        'website_id',
        'description',
        'content',
        'image', // Featured image
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
        'width_unit_id',
        'height',
        'height_unit_id',
        'depth',
        'depth_unit_id',
        'shipping_height',
        'shipping_height_id',
        'shipping_length',
        'shipping_length_id',
        'shipping_depth',
        'shipping_depth_id',
        'shipping_width',
        'shipping_width_id',
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

    public function specifications()
    {
        return $this->hasMany(Specification::class);
    }
}
