<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTestResultRequest;
use App\Http\Resources\V1\TestResultResource;
use App\Models\TestMatrix;
use App\Models\TestResult;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestResultController extends Controller
{
    public function index(TestMatrix $testMatrix): AnonymousResourceCollection
    {
        return TestResultResource::collection($testMatrix->results()->get());
    }

    public function show(TestResult $testResult): TestResultResource
    {
        return new TestResultResource($testResult);
    }

    public function update(UpdateTestResultRequest $request, TestResult $testResult): TestResultResource
    {
        $testResult->update($request->validated());

        return new TestResultResource($testResult);
    }
}
