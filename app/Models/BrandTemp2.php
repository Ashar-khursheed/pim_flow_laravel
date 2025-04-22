<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="BrandTemp2",
 *     type="object",
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="brand_id", type="integer"),
 *
 *     @OA\Property(
 *         property="page_top_banners_desktop",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="file", type="string", format="uri"),
 *             @OA\Property(property="alt_text", type="string"),
 *             @OA\Property(property="file_name", type="string")
 *         )
 *     ),
 *     @OA\Property(
 *         property="page_top_banners_mobile",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="file", type="string", format="uri"),
 *             @OA\Property(property="alt_text", type="string"),
 *             @OA\Property(property="file_name", type="string")
 *         )
 *     ),
 *     @OA\Property(
 *         property="category_banners",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="file", type="string", format="uri"),
 *             @OA\Property(property="alt_text", type="string"),
 *             @OA\Property(property="file_name", type="string")
 *         )
 *     ),
 *     @OA\Property(
 *         property="page_middle_banners_desktop",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="file", type="string", format="uri"),
 *             @OA\Property(property="alt_text", type="string"),
 *             @OA\Property(property="file_name", type="string")
 *         )
 *     ),
 *     @OA\Property(
 *         property="page_middle_banners_mobile",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="file", type="string", format="uri"),
 *             @OA\Property(property="alt_text", type="string"),
 *             @OA\Property(property="file_name", type="string")
 *         )
 *     ),
 *     @OA\Property(
 *         property="website_banners_videos",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="file", type="string", format="uri"),
 *             @OA\Property(property="type", type="string", enum={"image", "video"}),
 *             @OA\Property(property="alt_text", type="string"),
 *             @OA\Property(property="file_name", type="string")
 *         )
 *     ),
 *     @OA\Property(
 *         property="website_banners_videos_mobile",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="file", type="string", format="uri"),
 *             @OA\Property(property="type", type="string", enum={"image", "video"}),
 *             @OA\Property(property="alt_text", type="string"),
 *             @OA\Property(property="file_name", type="string")
 *         )
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class BrandTemp2 extends Model
{
    protected $table = 'brand_temp_2';

    protected $guarded = [];

    protected $casts = [
        'page_top_banners_desktop' => 'array',
        'page_top_banners_desktop_alt_text' => 'array',
        'page_top_banners_desktop_file_name' => 'array',
        'page_top_banners_mobile' => 'array',
        'page_top_banners_mobile_alt_text' => 'array',
        'page_top_banners_mobile_file_name' => 'array',
        'category_banners' => 'array',
        'category_banners_alt_text' => 'array',
        'category_banners_file_name' => 'array',
        'page_middle_banners_desktop' => 'array',
        'page_middle_banners_desktop_alt_text' => 'array',
        'page_middle_banners_desktop_file_name' => 'array',
        'page_middle_banners_mobile' => 'array',
        'page_middle_banners_mobile_alt_text' => 'array',
        'page_middle_banners_mobile_file_name' => 'array',
        'website_banners_videos' => 'array',
        'website_banners_videos_alt_text' => 'array',
        'website_banners_videos_file_name' => 'array',
        'website_banners_videos_mobile' => 'array',
        'website_banners_videos_mobile_alt_text' => 'array',
        'website_banners_videos_mobile_file_name' => 'array',
    ];

    public function getCategoryIdAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setCategoryIdAttribute($value)
    {
        $this->attributes['category_id'] = is_array($value) ? json_encode($value) : $value;
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}