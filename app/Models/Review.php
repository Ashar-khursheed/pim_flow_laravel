<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $table = 'ec_reviews';

    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_email',
        'product_id',
        'star',
        'comment',
        'status',
        'images'
    ];

    protected $casts = [
        'images' => 'array',
    ];
}
