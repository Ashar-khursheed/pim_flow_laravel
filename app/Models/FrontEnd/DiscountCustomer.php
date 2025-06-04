<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCustomer extends Model
{
    protected $table = 'ec_discount_customers';

    protected $fillable = [
        'discount_id',
        'customer_id',
    ];

    public function customers(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }
}
