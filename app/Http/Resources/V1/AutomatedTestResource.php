<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomatedTestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_scenario_id' => $this->test_scenario_id,
            'name' => $this->name,
            'file_path' => $this->file_path,
            'kind' => $this->kind,
            'description' => $this->description,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
