<?php
// app/Http/Resources/OrderResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'code' => $this->code,
            'user_id' => $this->user_id,
            'shipping_option' => $this->shipping_option,
            'shipping_method' => $this->shipping_method,
            'status' => $this->status,
            'amount' => $this->amount,
            'tax_amount' => $this->tax_amount,
            'shipping_amount' => $this->shipping_amount,
            'description' => $this->description,
            'coupon_code' => $this->coupon_code,
            'discount_amount' => $this->discount_amount,
            'sub_total' => $this->sub_total,
            'is_confirmed' => $this->is_confirmed,
            'discount_description' => $this->discount_description,
            'is_finished' => $this->is_finished,
            'cancellation_reason' => $this->cancellation_reason,
            'cancellation_reason_description' => $this->cancellation_reason_description,
            'completed_at' => $this->completed_at,
            'token' => $this->token,
            'payment_id' => $this->payment_id,
            'proof_file' => $this->proof_file,
            'store_id' => $this->store_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Include relationships if they are loaded
            'address' => new OrderAddressResource($this->whenLoaded('address')),
            'histories' => OrderHistoryResource::collection($this->whenLoaded('histories')),
            'returns' => OrderReturnResource::collection($this->whenLoaded('returns')),
            'referral' => new OrderReferralResource($this->whenLoaded('referral')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}