<?php

namespace App\Models;

use App\Concerns\AppendsOrderIndex;
use Database\Factories\TestScenarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A row of a test matrix: something to test in each context where it is relevant.
 */
#[Fillable(['test_matrix_id', 'title', 'description', 'order_index'])]
class TestScenario extends Model
{
    /** @use HasFactory<TestScenarioFactory> */
    use AppendsOrderIndex, HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
        ];
    }

    protected function orderIndexSiblings(): array
    {
        return ['test_matrix_id' => $this->test_matrix_id];
    }

    /** @return BelongsTo<TestMatrix, $this> */
    public function matrix(): BelongsTo
    {
        return $this->belongsTo(TestMatrix::class, 'test_matrix_id');
    }

    /** @return HasMany<TestResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(TestResult::class);
    }

    /** @return HasMany<AutomatedTest, $this> */
    public function automatedTests(): HasMany
    {
        return $this->hasMany(AutomatedTest::class)->orderBy('order_index');
    }

    /** @return HasMany<TestComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TestComment::class);
    }
}
