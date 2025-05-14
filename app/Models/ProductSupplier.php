<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="ProductSupplier",
 *     type="object",
 *     title="Product Supplier",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="sku", type="string"),
 *     @OA\Property(property="vendor_id", type="integer"),
 *     @OA\Property(property="product_id", type="integer"),
 *     @OA\Property(property="warranty_information", type="string"),
 *     @OA\Property(property="refund", type="string"),
 *     @OA\Property(property="delivery_days", type="string"),
 *     @OA\Property(property="cost_per_item", type="number", format="float"),
 *     @OA\Property(property="sale_price", type="number", format="float"),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="margin", type="number", format="float"),
 *     @OA\Property(property="inventory", type="integer"),
 *     @OA\Property(property="additional_cost", type="number", format="float"),
 *     @OA\Property(property="final_cost_price", type="number", format="float"),
 * )
 */
class ProductSupplier extends Model
{
    protected $fillable = [
        'sku',
        'vendor_id',
        'product_id',
        'warranty_information',
        'refund',
        'delivery_days',
        'cost_per_item',
        'sale_price',
        'price',
        'margin',
        'inventory',
        'additional_cost',
        'final_cost_price',
    ];

    public function vendor()
{
    return $this->belongsTo(Vendor::class, 'vendor_id');
}

}
