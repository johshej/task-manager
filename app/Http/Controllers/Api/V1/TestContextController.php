<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTestContextRequest;
use App\Http\Requests\Api\V1\UpdateTestContextRequest;
use App\Http\Resources\V1\TestContextResource;
use App\Models\TestContext;
use App\Models\TestMatrix;
use App\Services\TestMatrixGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestContextController extends Controller
{
    public function index(TestMatrix $testMatrix): AnonymousResourceCollection
    {
        return TestContextResource::collection($testMatrix->contexts()->get());
    }

    public function show(TestContext $testContext): TestContextResource
    {
        return new TestContextResource($testContext);
    }

    /** The context gets a cell in every scenario; with test_scenario_ids only those scenarios are relevant in it. */
    public function store(StoreTestContextRequest $request, TestMatrixGrid $grid): TestContextResource
    {
        $data = $request->validated();
        $relevantScenarioIds = $data['test_scenario_ids'] ?? null;
        unset($data['test_scenario_ids']);

        $matrix = TestMatrix::findOrFail($data['test_matrix_id']);

        return new TestContextResource($grid->addContext($matrix, $data, $relevantScenarioIds));
    }

    public function update(UpdateTestContextRequest $request, TestContext $testContext): TestContextResource
    {
        $testContext->update($request->validated());

        return new TestContextResource($testContext);
    }

    public function destroy(TestContext $testContext): JsonResponse
    {
        $testContext->delete();

        return response()->json(null, 204);
    }
}
