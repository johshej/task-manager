<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTestCommentRequest;
use App\Http\Requests\Api\V1\UpdateTestCommentRequest;
use App\Http\Resources\V1\TestCommentResource;
use App\Models\TestComment;
use App\Models\TestMatrix;
use App\Models\TestScenario;
use App\Services\TestMatrixGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestCommentController extends Controller
{
    public function index(TestMatrix $testMatrix): AnonymousResourceCollection
    {
        return TestCommentResource::collection($testMatrix->comments()->with(['task', 'contexts'])->get());
    }

    public function show(TestComment $testComment): TestCommentResource
    {
        $testComment->load(['task', 'contexts']);

        return new TestCommentResource($testComment);
    }

    /** A comment is a task, given to one or more contexts of the scenario. */
    public function store(StoreTestCommentRequest $request, TestMatrixGrid $grid): TestCommentResource
    {
        $data = $request->validated();
        $scenario = TestScenario::findOrFail($data['test_scenario_id']);

        $comment = $grid->addComment($scenario, $data['test_context_ids'], collect($data)->only(['title', 'description', 'status', 'priority'])->all());
        $comment->load(['task', 'contexts']);

        return new TestCommentResource($comment);
    }

    public function update(UpdateTestCommentRequest $request, TestComment $testComment, TestMatrixGrid $grid): TestCommentResource
    {
        $data = $request->validated();

        $grid->updateComment($testComment, collect($data)->only(['title', 'description', 'status', 'priority'])->all(), $data['test_context_ids'] ?? null);
        $testComment->load(['task', 'contexts']);

        return new TestCommentResource($testComment);
    }

    /** Removes the comment and its task. */
    public function destroy(TestComment $testComment): JsonResponse
    {
        $testComment->task->delete();

        return response()->json(null, 204);
    }
}
