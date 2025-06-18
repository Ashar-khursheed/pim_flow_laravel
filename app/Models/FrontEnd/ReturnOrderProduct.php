<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ReturnOrderProduct extends Model
{
    protected $fillable = [
        'refund_number', 'order_product_id', 'quantity', 'reason', 'product_images',
        'product_videos', 'description', 'status', 'inspected_by', 'comment',
        'refund_status', 'refund_amount', 'refund_method', 'refund_date', 'updated_by'
    ];

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class);
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
