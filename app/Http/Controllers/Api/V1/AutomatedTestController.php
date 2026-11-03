<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAutomatedTestRequest;
use App\Http\Requests\Api\V1\UpdateAutomatedTestRequest;
use App\Http\Resources\V1\AutomatedTestResource;
use App\Models\AutomatedTest;
use App\Models\TestScenario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AutomatedTestController extends Controller
{
    public function index(TestScenario $testScenario): AnonymousResourceCollection
    {
        return AutomatedTestResource::collection($testScenario->automatedTests()->get());
    }

    public function show(AutomatedTest $automatedTest): AutomatedTestResource
    {
        return new AutomatedTestResource($automatedTest);
    }

    public function store(StoreAutomatedTestRequest $request): AutomatedTestResource
    {
        return new AutomatedTestResource(AutomatedTest::create($request->validated()));
    }

    public function update(UpdateAutomatedTestRequest $request, AutomatedTest $automatedTest): AutomatedTestResource
    {
        $automatedTest->update($request->validated());

        return new AutomatedTestResource($automatedTest);
    }

    public function destroy(AutomatedTest $automatedTest): JsonResponse
    {
        $automatedTest->delete();

        return response()->json(null, 204);
    }
}
