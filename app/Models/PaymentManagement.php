<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\Invoice;

class PaymentManagement extends Model
{
    protected $table = 'payments_management';

    protected $fillable = [
        'order_id',
        'transaction_id',
        'payment_mode',
        'amount',
        'status',
        'payment_date',
        'notes',
        'payment_details',
        'payment_method',
        'payment_img',
        'rider_name',
        'created_by',
        'updated_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function user()
    {
    return $this->belongsTo(User::class, 'created_by'); // adjust foreign key if needed
    }

    public function invoice()
	{
		return $this->hasOne(Invoice::class, 'order_id', 'id');
	}
     
}
