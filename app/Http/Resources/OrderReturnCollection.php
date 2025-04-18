<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderReturnCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Use OrderReturnResource to transform each item in the collection
        return $this->collection->map(function ($orderReturn) {
            return new OrderReturnResource($orderReturn);
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
        // Check if the collection is paginated
        if ($this->collection instanceof LengthAwarePaginator) {
            return [
                'meta' => [
                    'total' => $this->collection->total(),
                    'pagination' => [
                        'total' => $this->collection->total(),
                        'count' => $this->collection->count(),
                        'per_page' => $this->collection->perPage(),
                        'current_page' => $this->collection->currentPage(),
                        'total_pages' => $this->collection->lastPage(),
                    ],
                ],
            ];
        }

        return [];
    }
}
