<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MadeToOrder extends Model
{ 
    protected $fillable = [
        'product_id',
        'quantity',
        'name',
        'email',
        'address',
        'city',
        'state',
        'country',
        'zipcode',
        'phone_number',
        'notes',
    ];

     

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
