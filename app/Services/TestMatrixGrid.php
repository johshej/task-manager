<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TestComment;
use App\Models\TestContext;
use App\Models\TestMatrix;
use App\Models\TestResult;
use App\Models\TestScenario;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a test matrix consistent: every scenario has a cell (a result) in every context, and a comment is a task
 * that belongs to one scenario and one or more contexts.
 */
class TestMatrixGrid
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $relevantContextIds  The contexts where the scenario is relevant; null means all of them.
     */
    public function addScenario(TestMatrix $matrix, array $attributes, ?array $relevantContextIds = null): TestScenario
    {
        return DB::transaction(function () use ($matrix, $attributes, $relevantContextIds): TestScenario {
            $scenario = $matrix->scenarios()->create($attributes);

            foreach ($matrix->contexts()->get() as $context) {
                $scenario->results()->create([
                    'test_context_id' => $context->id,
                    'relevant' => $relevantContextIds === null || in_array($context->id, $relevantContextIds, true),
                ]);
            }

            return $scenario;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $relevantScenarioIds  The scenarios that are relevant in the context; null means all of them.
     */
    public function addContext(TestMatrix $matrix, array $attributes, ?array $relevantScenarioIds = null): TestContext
    {
        return DB::transaction(function () use ($matrix, $attributes, $relevantScenarioIds): TestContext {
            $context = $matrix->contexts()->create($attributes);

            foreach ($matrix->scenarios()->get() as $scenario) {
                $scenario->results()->create([
                    'test_context_id' => $context->id,
                    'relevant' => $relevantScenarioIds === null || in_array($scenario->id, $relevantScenarioIds, true),
                ]);
            }

            return $context;
        });
    }

    /**
     * Makes the scenario relevant in the listed contexts and not relevant in the others.
     *
     * @param  list<string>  $relevantContextIds
     */
    public function setScenarioRelevance(TestScenario $scenario, array $relevantContextIds): void
    {
        DB::transaction(function () use ($scenario, $relevantContextIds): void {
            foreach ($scenario->matrix->contexts()->get() as $context) {
                TestResult::updateOrCreate(
                    ['test_scenario_id' => $scenario->id, 'test_context_id' => $context->id],
                    ['relevant' => in_array($context->id, $relevantContextIds, true)],
                );
            }
        });
    }

    /**
     * Makes a comment: a task in the matrix's feature, given to the listed contexts of the scenario.
     *
     * @param  list<string>  $contextIds
     * @param  array{title: string, description?: string|null, status?: string|null, priority?: int|null}  $taskAttributes
     */
    public function addComment(TestScenario $scenario, array $contextIds, array $taskAttributes): TestComment
    {
        return DB::transaction(function () use ($scenario, $contextIds, $taskAttributes): TestComment {
            $matrix = $scenario->matrix;
            $feature = $matrix->commentFeature();

            $task = Task::create([
                'feature_id' => $feature->id,
                'title' => $taskAttributes['title'],
                'description' => $taskAttributes['description'] ?? null,
                'status' => $taskAttributes['status'] ?? TaskStatus::Todo->value,
                'priority' => $taskAttributes['priority'] ?? 0,
                'order_index' => (Task::where('feature_id', $feature->id)->max('order_index') ?? -1) + 1,
            ]);

            $comment = TestComment::create([
                'test_matrix_id' => $matrix->id,
                'test_scenario_id' => $scenario->id,
                'task_id' => $task->id,
            ]);
            $comment->contexts()->sync($contextIds);

            return $comment;
        });
    }

    /**
     * @param  array<string, mixed>  $taskAttributes  Any of title, description, status and priority.
     * @param  list<string>|null  $contextIds  The contexts the comment is given to; null leaves them as they are.
     */
    public function updateComment(TestComment $comment, array $taskAttributes, ?array $contextIds = null): TestComment
    {
        return DB::transaction(function () use ($comment, $taskAttributes, $contextIds): TestComment {
            if ($taskAttributes !== []) {
                $comment->task->update($taskAttributes);
            }

            if ($contextIds !== null) {
                $comment->contexts()->sync($contextIds);
            }

            return $comment;
        });
    }
}
