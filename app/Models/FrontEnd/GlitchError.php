<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class GlitchError extends Model
{
    protected $fillable = [
        'email',
        'mobile_number',
        'description',
        'device',
        'images',
    ];
}
