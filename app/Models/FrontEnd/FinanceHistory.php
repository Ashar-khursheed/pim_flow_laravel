<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\FrontEnd\Finances;
class FinanceHistory extends Model
{
    protected $table = 'finances_histories';
    protected $fillable = [
        'payment_id',
        'finances_id',
        'order_id',
        'order_number',
        'customer_id',
        'due_date',
        'due_amount',
        'paid_on_date',
        'paid_amount',
        'balance',
        'creditTerms',
        'status',
        'payment_mode',
        'paid_by',
        'updated_by',
    ];

 

    // Payment reference
    public function payment()
    {
        return $this->belongsTo(FinancesPayment::class, 'payment_id');
    }

    // Finance main record
    public function finance()
    {
        return $this->belongsTo(Finances::class, 'finances_id');
    }

    // Order relation
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // Customer relation
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    // Paid by user
    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // Updated by user
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }


}
