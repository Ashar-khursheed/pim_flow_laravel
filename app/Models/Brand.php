<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Brand",
 *     title="Brand",
 *     description="Brand model",
 *     type="object",
 *     required={"id", "name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Apple"),
 *     @OA\Property(property="description", type="string", example="Premium electronics brand"),
 *     @OA\Property(property="website", type="string", format="url", example="https://www.apple.com"),
 *     @OA\Property(property="logo", type="string", format="url", example="https://example.com/logo.jpg"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="order", type="integer", example=1),
 *     @OA\Property(property="is_featured", type="boolean", example=true)
 * )
 */

class Brand extends Model
{
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
        ->select(['id', 'url', 'relational_id']) 
        ->whereNotNull('url')
        ->where('relational_type', 'Brand');
    }
}
