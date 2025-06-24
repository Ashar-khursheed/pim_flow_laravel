<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentManagement extends Model
{
    protected $table = 'payments_management';

    protected $fillable = [
        'order_id',
        'transaction_id',
        'payment_mode',
        'amount',
        'status',
        'payment_date',
        'notes',
        'payment_details',
    ];
}
