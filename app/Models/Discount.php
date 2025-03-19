<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Discount",
 *     title="Discount",
 *     description="Discount model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Black Friday Sale"),
 *     @OA\Property(property="code", type="string", nullable=true, example="BLACKFRIDAY2024"),
 *     @OA\Property(property="start_date", type="string", format="date", nullable=true, example="2024-11-25"),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2024-11-30"),
 *     @OA\Property(property="quantity", type="integer", nullable=true, example=100),
 *     @OA\Property(property="total_used", type="integer", nullable=true, example=25),
 *     @OA\Property(property="value", type="number", format="float", example=15.5),
 *     @OA\Property(property="type", type="string", example="percentage"),
 *     @OA\Property(property="can_use_with_promotion", type="boolean", example=true),
 *     @OA\Property(property="discount_on", type="string", example="product"),
 *     @OA\Property(property="product_quantity", type="integer", nullable=true, example=2),
 *     @OA\Property(property="type_option", type="string", example="fixed"),
 *     @OA\Property(property="target", type="string", example="order"),
 *     @OA\Property(property="min_order_price", type="number", format="float", nullable=true, example=50),
 *     @OA\Property(property="apply_via_url", type="boolean", example=false),
 *     @OA\Property(property="display_at_checkout", type="boolean", example=true),
 *     @OA\Property(property="description", type="string", nullable=true, example="Get 15% off for Black Friday!"),
 *     @OA\Property(property="store_id", type="integer", nullable=true, example=3)
 * )
 */
class Discount extends Model
{
    use HasFactory;

    protected $table = 'ec_discounts';

    protected $fillable = [
        'title', 'code', 'start_date', 'end_date', 'quantity', 'total_used',
        'value', 'type', 'can_use_with_promotion', 'discount_on', 'product_quantity',
        'type_option', 'target', 'min_order_price', 'apply_via_url',
        'display_at_checkout', 'description', 'store_id'
    ];

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'ec_discount_customers', 'discount_id', 'customer_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'ec_discount_products', 'discount_id', 'product_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'ec_discount_product_categories', 'discount_id', 'product_category_id');
    }
}
