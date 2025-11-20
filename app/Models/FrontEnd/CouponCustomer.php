<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class CouponCustomer extends Model
{
     protected $fillable = [
        'coupon_id',
        'customer_id',
        'usage_count',
    ];

    /**
     * Relationship: A CouponCustomer belongs to a coupon
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Relationship: A CouponCustomer belongs to a customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
