<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChequeUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'cheque_img',
        'cheque_img_back',
        'session_id',
    ];
}