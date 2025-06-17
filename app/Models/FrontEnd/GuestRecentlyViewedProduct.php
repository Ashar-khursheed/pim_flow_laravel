<?php
// app/Models/GuestRecentlyViewedProduct.php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="GuestRecentlyViewedProduct",
 *     description="GuestRecentlyViewedProduct model",
 *     type="object"
 * )
 */
class GuestRecentlyViewedProduct extends Model
{
    protected $fillable = ['guest_token', 'product_id'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
