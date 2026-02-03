<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessoryItem extends Model
{
     protected $fillable = ['name', 'price', 'cost_price', 'product_accessory_id'];

    protected $casts = [
        'accessories' => 'array',  
    ];

     public function accessory()
    {
        return $this->belongsTo(ProductAccessory::class, 'product_accessory_id');
    }
}
