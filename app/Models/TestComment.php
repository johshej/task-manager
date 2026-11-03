<?php

namespace App\Models;

use Database\Factories\TestCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A comment on a test matrix is a task. It belongs to a scenario and to one or more contexts, so the same
 * comment can be given to every context it affects. The title, description and status are those of the task.
 */
#[Fillable(['test_matrix_id', 'test_scenario_id', 'task_id'])]
class TestComment extends Model
{
    /** @use HasFactory<TestCommentFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<TestMatrix, $this> */
    public function matrix(): BelongsTo
    {
        return $this->belongsTo(TestMatrix::class, 'test_matrix_id');
    }

    /** @return BelongsTo<TestScenario, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(TestScenario::class, 'test_scenario_id');
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsToMany<TestContext, $this> */
    public function contexts(): BelongsToMany
    {
        return $this->belongsToMany(TestContext::class, 'test_comment_test_context')->orderBy('order_index');
    }
}
