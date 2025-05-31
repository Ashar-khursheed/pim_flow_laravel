<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'shipment_number', 'shipment_date', 'tracking_number', 
        'carrier', 'status', 'shipping_cost', 'estimated_delivery', 
        'actual_delivery', 'notes'
    ];

    protected $casts = [
        'shipment_date' => 'datetime',
        'shipping_cost' => 'decimal:2',
        'estimated_delivery' => 'datetime',
        'actual_delivery' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function tracking()
    {
        return $this->hasMany(OrderTracking::class);
    }
}