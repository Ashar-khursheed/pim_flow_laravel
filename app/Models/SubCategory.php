<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    /**
 * @OA\Schema(
 *     schema="SubCategory",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Electronics Accessories"),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="category",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Electronics")
 *     ),
 *     @OA\Property(
 *         property="products",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Smartphone")
 *         )
 *     ),
 *     @OA\Property(
 *         property="web_banners",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="image_name", type="string", example="web_banner_1.jpg"),
 *             @OA\Property(property="alt_text", type="string", example="Web Banner 1")
 *         )
 *     ),
 *     @OA\Property(
 *         property="mobile_banners",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="image_name", type="string", example="mobile_banner_1.jpg"),
 *             @OA\Property(property="alt_text", type="string", example="Mobile Banner 1")
 *         )
 *     )
 * )
 */
    protected $fillable = ['name', 'category_id', 'products_ids', 'attributes_ids', 'web_banners', 'mobile_banners'];

    // Mutator to store products_ids as a JSON array
    public function setProductsIdsAttribute($value)
    {
        $this->attributes['products_ids'] = json_encode($value);
    }

    // Accessor to retrieve products_ids as an array
    public function getProductsIdsAttribute($value)
    {
        return json_decode($value, true);
    }

    // Mutator for attributes_ids
    public function setAttributesIdsAttribute($value)
    {
        $this->attributes['attributes_ids'] = json_encode($value);
    }

    // Accessor for attributes_ids
    public function getAttributesIdsAttribute($value)
    {
        return json_decode($value, true);
    }

    // Mutator for web_banners
    public function setWebBannersAttribute($value)
    {
        $this->attributes['web_banners'] = json_encode($value);
    }

    // Accessor for web_banners
    public function getWebBannersAttribute($value)
    {
        return json_decode($value, true);
    }

    // Mutator for mobile_banners
    public function setMobileBannersAttribute($value)
    {
        $this->attributes['mobile_banners'] = json_encode($value);
    }

    // Accessor for mobile_banners
    public function getMobileBannersAttribute($value)
    {
        return json_decode($value, true);
    }

    // Define the relationship with category (One subcategory belongs to one category)
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

  
}
