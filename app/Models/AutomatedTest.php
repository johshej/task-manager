<?php

namespace App\Models;

use App\Concerns\AppendsOrderIndex;
use Database\Factories\AutomatedTestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An automated test that covers a scenario, listed by its name and where it is (such as a file path).
 */
#[Fillable(['test_scenario_id', 'name', 'file_path', 'kind', 'description', 'order_index'])]
class AutomatedTest extends Model
{
    /** @use HasFactory<AutomatedTestFactory> */
    use AppendsOrderIndex, HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
        ];
    }

    protected function orderIndexSiblings(): array
    {
        return ['test_scenario_id' => $this->test_scenario_id];
    }

    /** @return BelongsTo<TestScenario, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(TestScenario::class, 'test_scenario_id');
    }
}
