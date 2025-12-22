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
        'requested_amount',
        'documents',
        'payment_due',
        'type_of_business',
        'annual_revenue',
        'years_in_business',
        'business_email',
        'accounts_payable_email',
        'accounts_payable_phone',
        'accounts_status',
        'customer_address_id',
        'duns_number',
        'credit_limit_amount',
        'approved_amount',
        'approval_date',
        'approvalBy',
        'used_credit_amount',
        'available_credit_amount',
        'paid_amount',
        'legal_business_name',
        'doing_business',
        'business_address',
        'rejection_reason',
        'rejectedBy',
        'rejected_date',
        'role_at_business',
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
    public function approvalUser()
    {
        return $this->belongsTo(User::class, 'approvalBy');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function customerAddress()
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }
}
