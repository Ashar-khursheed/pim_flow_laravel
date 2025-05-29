<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderTracking extends Model
{
    use HasFactory;
    protected $table = 'order_tracking';

    protected $fillable = [
        'order_id', 'shipment_id', 'status', 'description', 
        'location', 'tracked_at', 'metadata'
    ];

    protected $casts = [
        'tracked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
