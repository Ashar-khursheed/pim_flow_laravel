<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Finance extends Model
{
    protected $fillable = [
        'payment_selection',
        'payment_options',
        'term_selection',
        'amount',
        'documents',
        'payment_due',
        'type_of_business',
        'business_name',
        'business_address',
        'country',
        'address',
        'city',
        'state',
        'zip',
        'annual_revenue',
        'years_in_business',
        'accounts_payable_email',
        'accounts_payable_phone',
        'duns_number',
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
}
