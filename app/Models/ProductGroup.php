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
}
