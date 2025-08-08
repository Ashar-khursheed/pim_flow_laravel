<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoFraudResponse extends Model
{
    protected $table = 'nofraud_responses'; // explicitly defining the table name

    protected $fillable = [
        'order_id',
        'response',
    ];

    protected $casts = [
        'response' => 'array', // automatically cast JSON response to array
    ];
}
