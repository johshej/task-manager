<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestCommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_matrix_id' => $this->test_matrix_id,
            'test_scenario_id' => $this->test_scenario_id,
            'test_context_ids' => $this->contexts->pluck('id')->values(),
            'task_id' => $this->task_id,
            'title' => $this->task->title,
            'description' => $this->task->description,
            'status' => $this->task->status,
            'priority' => $this->task->priority,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
