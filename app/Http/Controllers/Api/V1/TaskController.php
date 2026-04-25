<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PatchTaskAssignRequest;
use App\Http\Requests\Api\V1\PatchTaskExecutionOrderRequest;
use App\Http\Requests\Api\V1\PatchTaskOrderRequest;
use App\Http\Requests\Api\V1\PatchTaskPriorityRequest;
use App\Http\Requests\Api\V1\PatchTaskStatusRequest;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Feature;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function index(Feature $feature): AnonymousResourceCollection
    {
        $tasks = $feature->tasks()
            ->with('latestHistory')
            ->orderBy('order_index')
            ->get();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): TaskResource
    {
        $task = Task::create($request->validated());
        $task->load('latestHistory');

        return new TaskResource($task);
    }

    public function show(Task $task): TaskResource
    {
        $task->load('latestHistory');

        return new TaskResource($task);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());
        $task->load('latestHistory');

        return new TaskResource($task);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(PatchTaskStatusRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());
        $task->load('latestHistory');

        return new TaskResource($task);
    }

    public function updateAssign(PatchTaskAssignRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());
        $task->load('latestHistory');

        return new TaskResource($task);
    }

    public function updatePriority(PatchTaskPriorityRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());
        $task->load('latestHistory');

        return new TaskResource($task);
    }

    public function updateOrder(PatchTaskOrderRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());
        $task->load('latestHistory');

        return new TaskResource($task);
    }

    public function updateExecutionOrder(PatchTaskExecutionOrderRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());
        $task->load('latestHistory');

        return new TaskResource($task);
    }
}
