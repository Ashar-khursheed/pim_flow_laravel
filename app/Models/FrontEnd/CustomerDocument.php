<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class CustomerDocument extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'document_path',
        'status',
    ];
}
