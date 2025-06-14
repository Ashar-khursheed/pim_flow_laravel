<?php
namespace App\Models\FrontEnd;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * @OA\Schema(
 *     schema="SimpleSlider",
 *     type="object",
 *     title="SimpleSlider",
 *     required={"id", "name", "key", "status"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Homepage Banner"),
 *     @OA\Property(property="key", type="string", example="home_slider"),
 *     @OA\Property(property="description", type="string", example="Main homepage slider"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(
 *         property="sliderItems",
 *         type="array",
 *         @OA\Items(type="object") // Replace with a ref if you define a separate SliderItem schema
 *     )
 * )
 */

class Slider extends Model
{
    protected $table = 'simple_sliders';

    protected $fillable = [
        'name',
        'key',
        'description',
        'status',
    ];


    protected static function booted(): void
    {
        static::deleted(function (SimpleSlider $slider) {
            $slider->sliderItems()->each(fn (SliderItem $item) => $item->delete());
        });
    }

    public function sliderItems(): HasMany
    {
        return $this->hasMany(SliderItem::class)->orderBy('simple_slider_items.order');
    }
}
