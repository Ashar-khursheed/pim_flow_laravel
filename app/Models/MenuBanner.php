<?php
// app/Models/Invoice.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/MenuBanner.php

class MenuBanner extends Model
{
    protected $fillable = [
        'category_id',
        'desktop_image',
        'desktop_image_alt',
        'mobile_image',
        'mobile_image_alt',
        'url',
    ];
}
