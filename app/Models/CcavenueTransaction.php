<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcavenueTransaction extends Model
{
    protected $fillable = [
        'order_id', 'tracking_id', 'bank_ref_no', 'order_status',
        'payment_mode', 'amount', 'currency', 'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];
}
