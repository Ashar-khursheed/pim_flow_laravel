<?php
// app/Http/Resources/OrderHistoryResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderHistoryResource extends JsonResource
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
            'action' => $this->action,
            'description' => $this->description,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'extras' => $this->extras,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
