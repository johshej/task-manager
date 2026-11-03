<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTestScenarioRequest;
use App\Http\Requests\Api\V1\UpdateTestScenarioRelevanceRequest;
use App\Http\Requests\Api\V1\UpdateTestScenarioRequest;
use App\Http\Resources\V1\TestScenarioResource;
use App\Models\TestMatrix;
use App\Models\TestScenario;
use App\Services\TestMatrixGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestScenarioController extends Controller
{
    public function index(TestMatrix $testMatrix): AnonymousResourceCollection
    {
        return TestScenarioResource::collection($testMatrix->scenarios()->with('automatedTests')->get());
    }

    public function show(TestScenario $testScenario): TestScenarioResource
    {
        $testScenario->load('automatedTests');

        return new TestScenarioResource($testScenario);
    }

    /** The scenario gets a cell in every context; with test_context_ids only those contexts are relevant for it. */
    public function store(StoreTestScenarioRequest $request, TestMatrixGrid $grid): TestScenarioResource
    {
        $data = $request->validated();
        $relevantContextIds = $data['test_context_ids'] ?? null;
        unset($data['test_context_ids']);

        $matrix = TestMatrix::findOrFail($data['test_matrix_id']);

        return new TestScenarioResource($grid->addScenario($matrix, $data, $relevantContextIds));
    }

    public function update(UpdateTestScenarioRequest $request, TestScenario $testScenario): TestScenarioResource
    {
        $testScenario->update($request->validated());
        $testScenario->load('automatedTests');

        return new TestScenarioResource($testScenario);
    }

    /** Makes the scenario relevant in the listed contexts and not relevant in the others. */
    public function updateRelevance(UpdateTestScenarioRelevanceRequest $request, TestScenario $testScenario, TestMatrixGrid $grid): TestScenarioResource
    {
        $grid->setScenarioRelevance($testScenario, $request->validated('test_context_ids'));
        $testScenario->load('automatedTests');

        return new TestScenarioResource($testScenario);
    }

    public function destroy(TestScenario $testScenario): JsonResponse
    {
        $testScenario->delete();

        return response()->json(null, 204);
    }
}
