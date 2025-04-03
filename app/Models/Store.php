<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $table = 'mp_stores';

    public function products()
    {
        return $this->hasMany(Product::class, 'store_id');
    }
    
}