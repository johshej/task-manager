<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeatureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'epic_id' => $this->epic_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
