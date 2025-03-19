<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="FlashSale",
 *     title="Flash Sale",
 *     description="Flash sale model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Summer Sale"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2024-08-30"),
 *     @OA\Property(property="status", type="string", example="published"),
 *     @OA\Property(
 *         property="products",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Product")
 *     )
 * )
 */
class FlashSale extends Model
{
    use HasFactory;

    protected $table = 'ec_flash_sales';

    protected $fillable = ['name', 'end_date', 'status'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'ec_flash_sale_products', 'flash_sale_id', 'product_id')
                    ->withPivot('price', 'quantity', 'sold');
    }
}
