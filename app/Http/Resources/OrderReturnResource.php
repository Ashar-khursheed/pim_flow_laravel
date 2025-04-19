<?php
// app/Http/Resources/OrderReturnResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


    /**
     * @OA\Schema(
     *     schema="UpdateOrderReturnRequest",
     *     type="object",
     *     title="Update Order Return Request",
     *     required={"return_status"}, 
     *     @OA\Property(property="return_status", type="string", example="approved"),
     *     @OA\Property(property="reason", type="string", example="Customer changed mind"),
     *     @OA\Property(property="notes", type="string", example="Updated return reason based on new info")
     * )
     */
class OrderReturnResource extends JsonResource
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
            'order_id' => $this->order_id,
            'store_id' => $this->store_id,
            'user_id' => $this->user_id,
            'reason' => $this->reason,
            'order_status' => $this->order_status,
            'return_status' => $this->return_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items' => OrderReturnItemResource::collection($this->whenLoaded('items')),
            'order' => new OrderResource($this->whenLoaded('order')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
