<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'business_name',
        'trade_name',
        'company_reg_no',
        'vat_number',
        'country',
        'legal_status',
    ];
}
