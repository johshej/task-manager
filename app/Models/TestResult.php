<?php

namespace App\Models;

use App\Enums\TestResultStatus;
use Database\Factories\TestResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cell of a test matrix: whether a scenario is relevant in a context, and how its test went.
 * The expectation says what is expected there when it is not the plain "works", such as "refused" or "read only".
 */
#[Fillable(['test_scenario_id', 'test_context_id', 'relevant', 'status', 'expectation', 'tested_at'])]
class TestResult extends Model
{
    /** @use HasFactory<TestResultFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        // The time of the test is when the status is set to passed or failed.
        static::saving(function (TestResult $result): void {
            if ($result->isDirty('status')) {
                $result->tested_at = $result->status === TestResultStatus::Untested ? null : now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'relevant' => 'boolean',
            'status' => TestResultStatus::class,
            'tested_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TestScenario, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(TestScenario::class, 'test_scenario_id');
    }

    /** @return BelongsTo<TestContext, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(TestContext::class, 'test_context_id');
    }
}
