<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\ProductSupplier;

class CustomerCartProduct extends Model
{
    protected $fillable = [
        'customer_cart_id',
        'product_id',
        'vendor_id',
        'quantity',
        'unit_price',
        'amount',
        'shipping_charge',
        'total_amount',
        'accessories_options', // just the column name
    ];

    // THIS IS IMPORTANT
    protected $casts = [
        'accessories_options' => 'array', // cast to array for JSON storage
    ];

    public function customerCart()
    {
        return $this->belongsTo(CustomerCart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getVendorProductSupplierAttribute()
    {
        return ProductSupplier::with([
                'vendor.country:id,name',
                'vendor.city:id,name',
                'vendor.city.state:id,name,abbreviation'
            ])
            ->where('product_id', $this->product_id)
            ->where('vendor_id', $this->vendor_id)
            ->first();
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
