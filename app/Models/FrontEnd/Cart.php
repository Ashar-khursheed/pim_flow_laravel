<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\FrontEnd\Customer;
class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
        'quantity',
    ];

    // Define relationships
    public function user()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id'); // Ensure proper relationship
    }

 public function customer()
{
    return $this->belongsTo(Customer::class, 'user_id', 'id');
}



}
