<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiffProduct extends Model
{
    protected $table = 'diff_brands';

    protected $fillable = [
        'product_id',
        'dif_id',
        'priority',
        'similarity',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function fbt(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'dif_id');
    }
}
