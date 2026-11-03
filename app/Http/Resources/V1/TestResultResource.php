<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_scenario_id' => $this->test_scenario_id,
            'test_context_id' => $this->test_context_id,
            'relevant' => $this->relevant,
            'status' => $this->status,
            'expectation' => $this->expectation,
            'tested_at' => $this->tested_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
