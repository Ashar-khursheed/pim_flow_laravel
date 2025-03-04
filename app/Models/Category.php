<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{

	use HasFactory;

    protected $table = 'ec_product_categories';

    protected $fillable = [
        'name', 'parent_id', 'description', 'status', 'order', 
        'image', 'is_featured', 'icon', 'icon_image', 'slug'
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }


    public function attributeFamilies()
    {
        return $this->hasMany(AttributeFamily::class, 'category_id');
    }

    public function scopeLastChildCategories($query, $parentId)
    {
        return $query->where('parent_id', '!=', 0)
            ->whereNotIn('id', function ($subQuery) {
                $subQuery->select('parent_id')
                    ->from('ec_product_categories')
                    ->whereNotNull('parent_id');
            })
            ->whereHas('parent', function ($parentQuery) use ($parentId) {
                $parentQuery->where('parent_id', $parentId);
            });
    }

	public function children()
	{
		return $this->hasMany(Category::class, 'parent_id');
	}

}
