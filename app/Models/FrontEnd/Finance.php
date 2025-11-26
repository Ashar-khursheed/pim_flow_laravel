<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
class Finance extends Model
{
    protected $fillable = [
        'customer_id',
        'payment_selection',
        'payment_options',
        'term_selection',
        'requestedAmount',
        'documents',
        'payment_due',
        'type_of_business',        
        'annual_revenue',
        'years_in_business',
        'accountsPayableEmail',
        'accountsPayablePhone',
        'customer_address_id',
        'duns_number',
        'creditLimitAmount',
        'approvedAmount',
        'approvalDate',
        'approvalBy',        
        'usedCreditAmount',
        'availableCreditAmount',
        'purchaseAmount',
        'dueCreditAmount',
        'payment_mode',
        'next_due_date',
        'status',
        'created_by',
        'updated_by',
    ];


    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function approvalBy()
    {
        return $this->belongsTo(User::class, 'approvalBy');
    }

    public function customer()
{
    return $this->belongsTo(Customer::class, 'customer_id');
}


}
