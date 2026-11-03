<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestContextResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_matrix_id' => $this->test_matrix_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
