<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'parent_id',
        'child_ids',
        'variants',        
        'status',
        'created_by',
        'updated_by'
    ];

   

    public function parentProduct()
    {
        return $this->belongsTo(Product::class, 'parent_id'); // adjust FK if needed
    }
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id'); // adjust FK if needed
    }

 public function childProduct()
    {
        return $this->belongsTo(Product::class, 'child_id');
    }
     public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
