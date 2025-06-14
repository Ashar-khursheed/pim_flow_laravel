<?php
namespace App\Models\FrontEnd;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SliderItem extends Model
{
    protected $table = 'simple_slider_items';

    protected $fillable = [
        'title',
        'description',
        'link',
        'image',
        'order',
        'simple_slider_id',
    ];

    // protected $casts = [
    //     'title' => SafeContent::class,
    //     'description' => SafeContent::class,
    //     'link' => SafeContent::class,
    // ];

    protected static function booted(): void
    {
        static::deleted(function (SliderItem $item) {
            $item->metadata()->delete();
        });
    }
}
