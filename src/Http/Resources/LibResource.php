<?php

namespace Ninex\Lib\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

class LibResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        if (empty($this->resource)) {
            return [];
        }

        return $this->resource instanceof Model
            ? $this->resource->toArray()
            : parent::toArray($request);
    }
}
