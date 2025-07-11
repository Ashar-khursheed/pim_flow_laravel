<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlternateProduct extends Model
{
    protected $table = 'alternate_products';

    protected $fillable = [
        'product_id',
        'product_alternate_id',
        'priority',
        'similarity',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function alternate(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_alternate_id');
    }
}
