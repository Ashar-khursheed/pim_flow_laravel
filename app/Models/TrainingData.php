<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingData extends Model
{
    protected $fillable = [
        'name',
        'business_name',
        'phone_number',
        'quotation',
        'call_summary',
        'transcript',
        'type',
        'successful',
        'zipcode'
    ];
}
