<?php

namespace ChandraHemant\HtkcUtils;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PaginatedResource extends ResourceCollection
{
    protected $resourceClass;

    public function __construct($resource, $resourceClass)
    {
        parent::__construct($resource);
        $this->resourceClass = $resourceClass;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'data' => $this->resourceClass::collection($this->collection),
            'pagination' => [
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_pages' => $this->lastPage(),
            ],
        ];
    }
}
