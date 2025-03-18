<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'ec_brands';

    protected $fillable = [
        'name', 'description', 'website', 'logo', 'status', 'order', 'is_featured'
    ];
}
