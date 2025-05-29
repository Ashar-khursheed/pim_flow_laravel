<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'order_date', 'order_time', 'customer_name', 
        'customer_email', 'customer_phone', 'company', 'address', 
        'city', 'country', 'status', 'total_amount', 'total_products',
        'ship_all_at_once', 'separate_deliveries', 'is_paid', 
        'paid_amount', 'pending_amount'
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'order_time' => 'datetime:H:i:s',
        'ship_all_at_once' => 'boolean',
        'separate_deliveries' => 'boolean',
        'is_paid' => 'boolean',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function tracking()
    {
        return $this->hasMany(OrderTracking::class);
    }

    // Calculate payment status
    public function getPaymentStatusAttribute()
    {
        if ($this->paid_amount >= $this->total_amount) {
            return 'Fully Paid';
        } elseif ($this->paid_amount > 0) {
            return 'Partially Paid';
        }
        return 'Unpaid';
    }

    // Check if all items are delivered
    public function getIsFullyDeliveredAttribute()
    {
        return $this->items->every(function ($item) {
            return $item->shipped_quantity >= $item->quantity;
        });
    }
}