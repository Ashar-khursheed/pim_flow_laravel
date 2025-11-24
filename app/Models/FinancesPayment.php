<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\FrontEnd\Finance;
class FinancesPayment extends Model
{
    protected $fillable = [
        'finances_id',
        'limitAmount',
        'usedAmount',
        'availableAmount',
        'purchaseAmount',
        'dueAmount',
        'creditTerms',
        'nextPaymentDue',
        'payment_mode'
    ];

    public function finance()
    {
        return $this->belongsTo(Finance::class, 'finances_id');
    }
}
