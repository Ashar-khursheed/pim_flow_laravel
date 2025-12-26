<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class CouponCategory extends Model
{
     protected $fillable = [
        'coupon_id',
        'category_id'
        
    ];
}
