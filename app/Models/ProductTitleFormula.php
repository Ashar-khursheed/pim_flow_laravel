<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTitleFormula extends Model
{
    protected $table = 'product_title_formula';

    protected $fillable = [
        'attribute_id',
        'category_id',
        'locked',
        'created_by',
    ];

    protected $casts = [
        'attribute_id' => 'array',
        'locked' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
