<?php
// app/Http/Resources/OrderReturnItemResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_return_id' => $this->order_return_id,
            'order_product_id' => $this->order_product_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_image' => $this->product_image,
            'qty' => $this->qty,
            'price' => $this->price,
            'reason' => $this->reason,
            'refund_amount' => $this->refund_amount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}