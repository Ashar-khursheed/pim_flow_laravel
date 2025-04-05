<?php
// app/Models/SeoSecondaryKeyword.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSecondaryKeyword extends Model
{
    protected $table = 'seo_secondary_keywords';

    protected $fillable = [
        'primary_keyword_id',
        'secondary_keyword',
        'monthly_search_volume',
    ];
    public $timestamps = false; // 👈 Add this to avoid the 422 error

    public function seoManagement()
    {
        return $this->belongsTo(SeoManagement::class, 'primary_keyword_id');
    }
}
