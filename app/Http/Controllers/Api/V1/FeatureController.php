<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFeatureRequest;
use App\Http\Requests\Api\V1\UpdateFeatureRequest;
use App\Http\Resources\V1\FeatureResource;
use App\Models\Epic;
use App\Models\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeatureController extends Controller
{
    public function index(Epic $epic): AnonymousResourceCollection
    {
        return FeatureResource::collection($epic->features()->orderBy('order_index')->get());
    }

    public function store(StoreFeatureRequest $request): FeatureResource
    {
        $feature = Feature::create($request->validated());

        return new FeatureResource($feature);
    }

    public function update(UpdateFeatureRequest $request, Feature $feature): FeatureResource
    {
        $feature->update($request->validated());

        return new FeatureResource($feature);
    }

    public function destroy(Feature $feature): JsonResponse
    {
        $feature->delete();

        return response()->json(null, 204);
    }
}
