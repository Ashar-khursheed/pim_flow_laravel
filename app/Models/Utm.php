<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Utm extends Model
{
    protected $fillable = [
        'utm_source', 'utm_medium', 'utm_campaign',
        'utm_term', 'utm_content', 'gclid', 'session_id',
    ];
}
