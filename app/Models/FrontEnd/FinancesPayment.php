<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\FrontEnd\Finance;
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
    ];

    public function finance()
    {
        return $this->belongsTo(Finance::class, 'finances_id');
    }
}
