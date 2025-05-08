<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    protected $fillable = ['name'];

    public function items()
    {
        return $this->hasMany(ProductGroupItem::class, 'group_id');
    }
    // In App\Models\ProductGroupItem.php

    public function product()
    {
        return $this->belongsTo(Product::class); // Adjust the relationship type if needed
    }

}
