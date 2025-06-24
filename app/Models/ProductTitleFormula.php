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
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_title_formula_attribute', 'product_title_formula_id', 'attribute_id');
    }
}
