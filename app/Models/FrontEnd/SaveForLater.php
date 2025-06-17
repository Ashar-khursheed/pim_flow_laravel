<?php

namespace App\Models\FrontEnd;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Frontend\Customer; // Add this at the top of your Product model
use App\Models\Product; 

class SaveForLater extends Model
{

    protected $table = 'save_for_later';

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
    ];
    
        public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
