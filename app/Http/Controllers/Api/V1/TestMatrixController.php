<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTestMatrixRequest;
use App\Http\Requests\Api\V1\UpdateTestMatrixRequest;
use App\Http\Resources\V1\TestMatrixResource;
use App\Models\Epic;
use App\Models\TestMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestMatrixController extends Controller
{
    public function index(Epic $epic): AnonymousResourceCollection
    {
        return TestMatrixResource::collection($epic->testMatrices()->get());
    }

    /** The whole grid: contexts, scenarios (with their automated tests) and every cell. */
    public function show(TestMatrix $testMatrix): TestMatrixResource
    {
        $testMatrix->load(['contexts', 'scenarios.automatedTests', 'results']);

        return new TestMatrixResource($testMatrix);
    }

    public function store(StoreTestMatrixRequest $request): TestMatrixResource
    {
        return new TestMatrixResource(TestMatrix::create($request->validated()));
    }

    public function update(UpdateTestMatrixRequest $request, TestMatrix $testMatrix): TestMatrixResource
    {
        $testMatrix->update($request->validated());

        return new TestMatrixResource($testMatrix);
    }

    public function destroy(TestMatrix $testMatrix): JsonResponse
    {
        $testMatrix->delete();

        return response()->json(null, 204);
    }
}
