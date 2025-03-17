<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="SimpleSliderItem",
 *     title="Simple Slider Item",
 *     type="object",
 *     required={"simple_slider_id", "image"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="simple_slider_id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", nullable=true, example="Welcome Banner"),
 *     @OA\Property(property="image", type="string", example="https://example.com/slider1.jpg"),
 *     @OA\Property(property="link", type="string", nullable=true, example="https://example.com"),
 *     @OA\Property(property="description", type="string", nullable=true, example="This is a banner"),
 *     @OA\Property(property="order", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class SimpleSliderItem extends Model
{
    use HasFactory;

    protected $table = 'simple_slider_items';

    protected $fillable = [
        'simple_slider_id',
        'title',
        'image',
        'link',
        'description',
        'order',
    ];

    public function slider()
    {
        return $this->belongsTo(SimpleSlider::class, 'simple_slider_id');
    }
}
