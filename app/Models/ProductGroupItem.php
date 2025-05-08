<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroupItem extends Model
{
    protected $fillable = ['group_id', 'product_id'];

    public function group()
    {
        return $this->belongsTo(ProductGroup::class, 'group_id');
    }
}
