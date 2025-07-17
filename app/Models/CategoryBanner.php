<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CategoryBanner extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'image_url',
        'image_alt_text',
        'position',
    ];

    public function category()
{
    return $this->belongsTo(Category::class, 'category_id');
}
}
