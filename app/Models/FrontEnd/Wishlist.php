<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Product; // Add this at the top of your Product model
use App\Models\SeoManagement;
class Wishlist extends Model
{
    protected $table = 'ec_wish_lists';

    protected $fillable = [
        'customer_id',
        'product_id',
        'quantity',
    ];

    public function product(): HasOne
    {
        return $this->hasOne(Product::class, 'id', 'product_id')->withDefault();
    }
    public function seoUrl()
	{
		return $this->hasOne(SeoManagement::class, 'relational_id', 'id');
	}
}
