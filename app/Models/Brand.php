<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class Brand extends Model implements TranslatableContract
{
    use Translatable;

    public $translatedAttributes = ['name_tr', 'description_tr'];

    protected $table = 'ec_brands';

    protected $fillable = [
        'name', 'description', 'website', 'logo', 'status', 'order', 'is_featured' , 'thumbnail' , 'ar_thumbnail'
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id'); // Ensure 'brand_id' is the foreign key in products table
    }

    public function slug()
	{
		return $this->hasOne(Slug::class, 'reference_id')->where('prefix', 'brands');
	}

    public function seoUrl()
    {
        return $this->hasOne(SeoManagement::class, 'relational_id', 'id')
        ->where(function ($query) {
            $query->where('relational_type', 'Brand')
            ->orWhere('relational_type', static::class);
        });
    }
}
