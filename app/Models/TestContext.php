<?php

namespace App\Models;

use App\Concerns\AppendsOrderIndex;
use Database\Factories\TestContextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A column of a test matrix: one named combination such as "Log ind / Desktop / Offline".
 * The code is the short form shown in the column header; the name and description say what it is in full.
 */
#[Fillable(['test_matrix_id', 'code', 'name', 'description', 'order_index'])]
class TestContext extends Model
{
    /** @use HasFactory<TestContextFactory> */
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

    /** @return BelongsToMany<TestComment, $this> */
    public function comments(): BelongsToMany
    {
        return $this->belongsToMany(TestComment::class, 'test_comment_test_context');
    }
}
