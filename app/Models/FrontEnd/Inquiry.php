<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'company_name',
        'restaurant_type',
        'files',
        'notes',
    ];

    protected $casts = [
        'files' => 'array',
    ];
}
