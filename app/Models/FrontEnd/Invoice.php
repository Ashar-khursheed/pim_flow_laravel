<?php
// app/Models/Invoice.php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'order_id',
        'po_number',
        'due_date',
        'amount',
        'payment_method',
        'status',
         'customer_id',
    ];

    protected $dates = [
        'invoice_date',
        'due_date',
    ];
}
