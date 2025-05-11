<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeRecommendation extends Model
{
    protected $fillable = ['parent_id', 'family_name', 'common_attributes', 'variants'];

    protected $casts = [
        'common_attributes' => 'array',
        'variants' => 'array',
    ];
}
