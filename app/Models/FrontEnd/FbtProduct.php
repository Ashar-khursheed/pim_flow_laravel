<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbtProduct extends Model
{
    protected $table = 'fbt';

    protected $fillable = [
        'product_id',
        'fbt_id',
        'priority',
        'similarity',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function fbt(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'fbt_id');
    }
}
