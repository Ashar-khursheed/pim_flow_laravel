<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'transaction_id', 'payment_mode', 'amount', 
        'status', 'payment_date', 'notes', 'payment_details'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'payment_details' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}