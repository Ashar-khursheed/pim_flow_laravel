<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderHistoryCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Use OrderHistoryResource to transform each item in the collection
        return $this->collection->map(function ($history) {
            return new OrderHistoryResource($history);
        });
    }

    /**
     * Optionally, you can include additional metadata to the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [
            'meta' => [
                'total' => $this->collection->count(),
            ],
        ];
    }
}
