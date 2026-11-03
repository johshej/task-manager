<?php

namespace App\Models;

use App\Concerns\AppendsOrderIndex;
use App\Enums\FeatureStatus;
use App\Enums\TestResultStatus;
use Database\Factories\TestMatrixFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A grid for testing an epic: scenarios as rows, contexts as columns, a result in every cell.
 */
#[Fillable(['epic_id', 'feature_id', 'name', 'description', 'order_index'])]
class TestMatrix extends Model
{
    /** @use HasFactory<TestMatrixFactory> */
    use AppendsOrderIndex, HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
        ];
    }

    protected function orderIndexSiblings(): array
    {
        return ['epic_id' => $this->epic_id];
    }

    /** @return BelongsTo<Epic, $this> */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    /**
     * The feature that holds the tasks made from comments.
     *
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /** @return HasMany<TestContext, $this> */
    public function contexts(): HasMany
    {
        return $this->hasMany(TestContext::class)->orderBy('order_index');
    }

    /** @return HasMany<TestScenario, $this> */
    public function scenarios(): HasMany
    {
        return $this->hasMany(TestScenario::class)->orderBy('order_index');
    }

    /** @return HasManyThrough<TestResult, TestScenario, $this> */
    public function results(): HasManyThrough
    {
        return $this->hasManyThrough(TestResult::class, TestScenario::class);
    }

    /** @return HasMany<TestComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TestComment::class);
    }

    /**
     * The feature that collects the tasks made from comments, made the first time one is needed.
     */
    public function commentFeature(): Feature
    {
        if ($this->feature) {
            return $this->feature;
        }

        $feature = Feature::create([
            'epic_id' => $this->epic_id,
            'name' => 'Test findings: '.$this->name,
            'description' => 'Comments made in the test matrix "'.$this->name.'".',
            'status' => FeatureStatus::Todo,
            'order_index' => (Feature::where('epic_id', $this->epic_id)->max('order_index') ?? -1) + 1,
        ]);

        $this->update(['feature_id' => $feature->id]);

        return $feature;
    }

    /**
     * How many of the cells that are relevant are passed, failed and untested.
     *
     * @return array{relevant: int, passed: int, failed: int, untested: int}
     */
    public function summary(): array
    {
        $counts = $this->results()->where('relevant', true)->selectRaw('test_results.status as status, count(*) as total')->groupBy('test_results.status')->pluck('total', 'status');

        $passed = (int) ($counts[TestResultStatus::Passed->value] ?? 0);
        $failed = (int) ($counts[TestResultStatus::Failed->value] ?? 0);
        $untested = (int) ($counts[TestResultStatus::Untested->value] ?? 0);

        return ['relevant' => $passed + $failed + $untested, 'passed' => $passed, 'failed' => $failed, 'untested' => $untested];
    }
}
