<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEpicRequest;
use App\Http\Requests\Api\V1\UpdateEpicRequest;
use App\Http\Resources\V1\EpicResource;
use App\Models\Epic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EpicController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return EpicResource::collection(Epic::all());
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
}
