<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoFraudResponse extends Model
{
    protected $fillable = [
        'order_id',
        'response',
    ];
}
