<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class ProductQuestion extends Model
{
    protected $fillable = ['email', 'product_id', 'question'];


    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
