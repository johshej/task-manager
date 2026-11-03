<?php

namespace App\Concerns;

/**
 * A new record without an order_index goes to the bottom of its siblings.
 */
trait AppendsOrderIndex
{
    /** @return array<string, mixed> The columns that identify the siblings, such as ['test_matrix_id' => '…']. */
    abstract protected function orderIndexSiblings(): array;

    protected static function bootAppendsOrderIndex(): void
    {
        static::creating(function ($model): void {
            if ($model->order_index === null) {
                $highest = static::query()->where($model->orderIndexSiblings())->max('order_index');
                $model->order_index = $highest === null ? 0 : $highest + 1;
            }
        });
    }
}
