<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestMatrixResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'epic_id' => $this->epic_id,
            'feature_id' => $this->feature_id,
            'name' => $this->name,
            'description' => $this->description,
            'order_index' => $this->order_index,
            'summary' => $this->summary(),
            'contexts' => TestContextResource::collection($this->whenLoaded('contexts')),
            'scenarios' => TestScenarioResource::collection($this->whenLoaded('scenarios')),
            'results' => TestResultResource::collection($this->whenLoaded('results')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
