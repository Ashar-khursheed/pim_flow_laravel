<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;

class Slug extends Model
{
    use HasTimestamps;

    protected $table = 'slugs';

    protected $fillable = [
        'key', 
        'prefix', 
        'reference_id', 
        'reference_type'
    ];

    public function sluggable()
    {
        return $this->morphTo('sluggable', 'reference_type', 'reference_id');
    }

    public function getFullSlugAttribute()
    {
        return $this->prefix ? $this->prefix . '/' . $this->key : $this->key;
    }
}
