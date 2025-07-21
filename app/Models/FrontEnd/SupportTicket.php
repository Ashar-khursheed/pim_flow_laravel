<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'customer_id',
        'full_name',
        'email',
        'company_name',
        'phone_number',
        'category_id',
        'priority_id',
        'subject',
        'description',
        'reference_id',
        'file_path',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
