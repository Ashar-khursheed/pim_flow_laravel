<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\FrontEnd\Customer;
use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\product;

class RecentlyViewedProduct extends Model
{
    use HasFactory;

    protected $table = 'ec_customer_recently_viewed_products';

    protected $fillable = [
        'customer_id',
        'product_id',
    ];

    // You can add relationships here, for example, to get product info
   public function product()
{
    return $this->belongsTo(Product::class);
}
}
