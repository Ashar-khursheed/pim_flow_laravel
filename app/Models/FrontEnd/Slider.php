<?php
namespace App\Models\FrontEnd;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


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
