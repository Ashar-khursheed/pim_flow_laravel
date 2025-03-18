<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="SimpleSlider",
 *     title="Simple Slider",
 *     type="object",
 *     required={"name", "key", "status"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Homepage Slider"),
 *     @OA\Property(property="key", type="string", example="homepage_slider"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Main banner slider"),
 *     @OA\Property(property="status", type="string", example="published"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/SimpleSliderItem"))
 * )
 */
class SimpleSlider extends Model
{
    use HasFactory;

    protected $table = 'simple_sliders';

    protected $fillable = [
        'name',
        'key',
        'description',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(SimpleSliderItem::class, 'simple_slider_id');
    }
}
