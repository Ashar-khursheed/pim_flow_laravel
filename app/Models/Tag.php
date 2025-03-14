<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $table = 'ec_product_tags'; // Define the correct table name

    protected $fillable = ['name'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'ec_product_tag_product', 'tag_id', 'product_id');
    }
}
