<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessoryItem extends Model
{
     protected $fillable = ['name', 'price', 'product_accessory_id'];

    protected $casts = [
        'accessories' => 'array',  
    ];
}
