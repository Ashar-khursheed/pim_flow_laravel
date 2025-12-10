<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\FrontEnd\Finance;
use App\Models\User;
class FinancesPayment extends Model
{
    protected $fillable = [
        'finances_id',
        'customer_id',
        'due_amount',
        'due_date',
        'paid_amount',
        'paid_on_date',
        'balance',
        'creditTerms',
        'payment_mode',
        'paid_by',
        'updated_by',
        'created_at',
        'updated_at',
        'order_number',
    ];

    public function finance()
    {
        return $this->belongsTo(Finance::class, 'finances_id');
    }
    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
 
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_number', 'id');
    }

    public function invoice()
    {
    return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }

        
}
