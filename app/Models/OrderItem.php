<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'item_id', 'name', 'quantity', 'shipped_quantity', 
        'remaining_quantity', 'supplier', 'unit_price', 'total_amount', 
        'image_url', 'status'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'shipped_quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shipmentItems()
    {
        return $this->hasMany(ShipmentItem::class);
    }

    // Check if item is fully shipped
    public function getIsFullyShippedAttribute()
    {
        return $this->shipped_quantity >= $this->quantity;
    }

    // Get pending quantity
    public function getPendingQuantityAttribute()
    {
        return $this->quantity - $this->shipped_quantity;
    }
}
