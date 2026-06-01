<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEpicRequest;
use App\Http\Requests\Api\V1\UpdateEpicRequest;
use App\Http\Resources\V1\EpicResource;
use App\Http\Resources\V1\FeatureResource;
use App\Http\Resources\V1\TaskResource;
use App\Models\Epic;
use App\Models\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EpicController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Epic::query();

        if ($request->filled('repository_url')) {
            $query->where('repository_url', $request->input('repository_url'));
        }

        return EpicResource::collection($query->get());
    }

    public function store(StoreEpicRequest $request): EpicResource
    {
        $epic = Epic::create($request->validated());

        return new EpicResource($epic);
    }

    public function show(Epic $epic): EpicResource
    {
        return new EpicResource($epic);
    }

    public function update(UpdateEpicRequest $request, Epic $epic): EpicResource
    {
        $epic->update($request->validated());

        return new EpicResource($epic);
    }

    public function destroy(Epic $epic): JsonResponse
    {
        $epic->delete();

        return response()->json(null, 204);
    }

    public function queue(Epic $epic): JsonResponse
    {
        $tasks = $epic->features()
            ->with('tasks')
            ->get()
            ->flatMap(fn ($feature) => $feature->tasks)
            ->map(fn ($task) => ['type' => 'task', 'sort' => [$task->execution_order ?? PHP_INT_MAX, $task->created_at->timestamp], 'data' => new TaskResource($task)]);

        $features = Feature::where('epic_id', $epic->id)
            ->doesntHave('tasks')
            ->get()
            ->map(fn ($feature) => ['type' => 'feature', 'sort' => [$feature->execution_order ?? PHP_INT_MAX, $feature->created_at->timestamp], 'data' => new FeatureResource($feature)]);

        $items = $tasks->merge($features)
            ->sortBy('sort')
            ->values()
            ->map(fn ($item) => array_merge(['type' => $item['type']], $item['data']->resolve()));

        return response()->json(['data' => $items]);
    }
}
