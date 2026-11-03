<?php

use App\Enums\TaskStatus;
use App\Enums\TestResultStatus;
use App\Models\AutomatedTest;
use App\Models\Epic;
use App\Models\TestComment;
use App\Models\TestContext;
use App\Models\TestMatrix;
use App\Models\TestResult;
use App\Models\TestScenario;
use App\Services\TestMatrixGrid;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Test matrix')] class extends Component {
    public Epic $epic;
    public TestMatrix $testMatrix;

    // Matrix
    public string $matrixName = '';
    public string $matrixDescription = '';

    // Scenario (a row)
    public ?string $editingScenarioId = null;
    public string $scenarioTitle = '';
    public string $scenarioDescription = '';
    /** @var list<string> The contexts where the scenario is relevant. */
    public array $scenarioContextIds = [];
    public string $automatedTestName = '';
    public string $automatedTestFilePath = '';
    public string $automatedTestKind = '';
    public ?string $deletingScenarioId = null;

    // Context (a column)
    public ?string $editingContextId = null;
    public string $contextCode = '';
    public string $contextName = '';
    public string $contextDescription = '';
    /** @var list<string> The scenarios that are relevant in the context. */
    public array $contextScenarioIds = [];
    public ?string $deletingContextId = null;

    // Cell
    public ?string $editingResultId = null;
    public string $resultStatus = 'untested';
    public bool $resultRelevant = true;
    public string $resultExpectation = '';

    // Comment (a task given to one or more contexts)
    public ?string $editingCommentId = null;
    public ?string $commentScenarioId = null;
    public string $commentTitle = '';
    public string $commentDescription = '';
    public string $commentStatus = 'todo';
    /** @var list<string> */
    public array $commentContextIds = [];
    public ?string $deletingCommentId = null;

    public function mount(Epic $epic, TestMatrix $testMatrix): void
    {
        abort_unless($testMatrix->epic_id === $epic->id, 404);

        $this->epic = $epic;
        $this->testMatrix = $testMatrix;
    }

    // ── Matrix ────────────────────────────────────────────────────────────────

    public function openMatrix(): void
    {
        $this->matrixName = $this->testMatrix->name;
        $this->matrixDescription = $this->testMatrix->description ?? '';
        $this->modal('edit-test-matrix')->show();
    }

    public function saveMatrix(): void
    {
        $this->validate([
            'matrixName' => ['required', 'string', 'max:255'],
            'matrixDescription' => ['nullable', 'string'],
        ]);

        $this->testMatrix->update(['name' => $this->matrixName, 'description' => $this->matrixDescription ?: null]);
        $this->modal('edit-test-matrix')->close();
        Flux::toast(variant: 'success', text: 'Test matrix saved.');
    }

    // ── Scenarios ─────────────────────────────────────────────────────────────

    public function openScenario(?string $scenarioId = null): void
    {
        $this->resetErrorBag();
        $this->reset('automatedTestName', 'automatedTestFilePath', 'automatedTestKind');
        $this->editingScenarioId = $scenarioId;

        if ($scenarioId === null) {
            $this->scenarioTitle = '';
            $this->scenarioDescription = '';
            $this->scenarioContextIds = $this->contexts->pluck('id')->all();
        } else {
            $scenario = $this->testMatrix->scenarios()->findOrFail($scenarioId);
            $this->scenarioTitle = $scenario->title;
            $this->scenarioDescription = $scenario->description ?? '';
            $this->scenarioContextIds = $scenario->results()->where('relevant', true)->pluck('test_context_id')->all();
        }

        $this->modal('scenario')->show();
    }

    public function saveScenario(TestMatrixGrid $grid): void
    {
        $this->validate([
            'scenarioTitle' => ['required', 'string', 'max:255'],
            'scenarioDescription' => ['nullable', 'string'],
        ]);

        $attributes = ['title' => $this->scenarioTitle, 'description' => $this->scenarioDescription ?: null];

        if ($this->editingScenarioId === null) {
            $grid->addScenario($this->testMatrix, $attributes, $this->scenarioContextIds);
        } else {
            $scenario = $this->testMatrix->scenarios()->findOrFail($this->editingScenarioId);
            $scenario->update($attributes);
            $grid->setScenarioRelevance($scenario, $this->scenarioContextIds);
        }

        $this->modal('scenario')->close();
        $this->refreshGrid();
        Flux::toast(variant: 'success', text: 'Scenario saved.');
    }

    public function confirmDeleteScenario(string $scenarioId): void
    {
        $this->deletingScenarioId = $scenarioId;
        $this->modal('scenario')->close();
        $this->modal('delete-scenario')->show();
    }

    public function deleteScenario(): void
    {
        $this->testMatrix->scenarios()->findOrFail($this->deletingScenarioId)->delete();
        $this->deletingScenarioId = null;
        $this->modal('delete-scenario')->close();
        $this->refreshGrid();
        Flux::toast(variant: 'success', text: 'Scenario deleted.');
    }

    public function moveScenario(string $scenarioId, string $direction): void
    {
        $this->move($this->testMatrix->scenarios()->get(), $scenarioId, $direction);
        $this->refreshGrid();
    }

    public function addAutomatedTest(): void
    {
        $this->validate([
            'automatedTestName' => ['required', 'string', 'max:255'],
            'automatedTestFilePath' => ['nullable', 'string', 'max:255'],
            'automatedTestKind' => ['nullable', 'string', 'max:50'],
        ]);

        $scenario = $this->testMatrix->scenarios()->findOrFail($this->editingScenarioId);
        $scenario->automatedTests()->create([
            'name' => $this->automatedTestName,
            'file_path' => $this->automatedTestFilePath ?: null,
            'kind' => $this->automatedTestKind ?: null,
        ]);

        $this->reset('automatedTestName', 'automatedTestFilePath', 'automatedTestKind');
        $this->refreshGrid();
    }

    public function removeAutomatedTest(string $automatedTestId): void
    {
        AutomatedTest::where('test_scenario_id', $this->editingScenarioId)->findOrFail($automatedTestId)->delete();
        $this->refreshGrid();
    }

    // ── Contexts ──────────────────────────────────────────────────────────────

    public function openContext(?string $contextId = null): void
    {
        $this->resetErrorBag();
        $this->editingContextId = $contextId;

        if ($contextId === null) {
            $this->contextCode = '';
            $this->contextName = '';
            $this->contextDescription = '';
            $this->contextScenarioIds = $this->scenarios->pluck('id')->all();
        } else {
            $context = $this->testMatrix->contexts()->findOrFail($contextId);
            $this->contextCode = $context->code;
            $this->contextName = $context->name;
            $this->contextDescription = $context->description ?? '';
            $this->contextScenarioIds = $context->results()->where('relevant', true)->pluck('test_scenario_id')->all();
        }

        $this->modal('context')->show();
    }

    public function saveContext(TestMatrixGrid $grid): void
    {
        $this->validate([
            'contextCode' => ['required', 'string', 'max:20', Rule::unique('test_contexts', 'code')->where('test_matrix_id', $this->testMatrix->id)->ignore($this->editingContextId)],
            'contextName' => ['required', 'string', 'max:255'],
            'contextDescription' => ['nullable', 'string'],
        ]);

        $attributes = ['code' => $this->contextCode, 'name' => $this->contextName, 'description' => $this->contextDescription ?: null];

        if ($this->editingContextId === null) {
            $grid->addContext($this->testMatrix, $attributes, $this->contextScenarioIds);
        } else {
            $context = $this->testMatrix->contexts()->findOrFail($this->editingContextId);
            $context->update($attributes);

            foreach ($this->scenarios as $scenario) {
                TestResult::updateOrCreate(
                    ['test_scenario_id' => $scenario->id, 'test_context_id' => $context->id],
                    ['relevant' => in_array($scenario->id, $this->contextScenarioIds, true)],
                );
            }
        }

        $this->modal('context')->close();
        $this->refreshGrid();
        Flux::toast(variant: 'success', text: 'Context saved.');
    }

    public function confirmDeleteContext(string $contextId): void
    {
        $this->deletingContextId = $contextId;
        $this->modal('context')->close();
        $this->modal('delete-context')->show();
    }

    public function deleteContext(): void
    {
        $this->testMatrix->contexts()->findOrFail($this->deletingContextId)->delete();
        $this->deletingContextId = null;
        $this->modal('delete-context')->close();
        $this->refreshGrid();
        Flux::toast(variant: 'success', text: 'Context deleted.');
    }

    public function moveContext(string $contextId, string $direction): void
    {
        $this->move($this->testMatrix->contexts()->get(), $contextId, $direction);
        $this->refreshGrid();
    }

    // ── Cells ─────────────────────────────────────────────────────────────────

    /** Marks a cell with the next status: untested, passed, failed, and around again. */
    public function cycleStatus(string $scenarioId, string $contextId): void
    {
        $result = $this->resultFor($scenarioId, $contextId);

        $result->update(['status' => match ($result->status) {
            TestResultStatus::Untested => TestResultStatus::Passed,
            TestResultStatus::Passed => TestResultStatus::Failed,
            TestResultStatus::Failed => TestResultStatus::Untested,
        }]);

        $this->refreshGrid();
    }

    public function openResult(string $scenarioId, string $contextId): void
    {
        $result = $this->resultFor($scenarioId, $contextId);

        $this->editingResultId = $result->id;
        $this->resultStatus = $result->status->value;
        $this->resultRelevant = $result->relevant;
        $this->resultExpectation = $result->expectation ?? '';

        $this->modal('result')->show();
    }

    public function saveResult(): void
    {
        $this->validate([
            'resultStatus' => ['required', Rule::enum(TestResultStatus::class)],
            'resultExpectation' => ['nullable', 'string', 'max:255'],
        ]);

        $result = TestResult::whereHas('scenario', fn ($query) => $query->where('test_matrix_id', $this->testMatrix->id))->findOrFail($this->editingResultId);
        $result->update([
            'status' => $this->resultStatus,
            'relevant' => $this->resultRelevant,
            'expectation' => $this->resultExpectation ?: null,
        ]);

        $this->modal('result')->close();
        $this->refreshGrid();
    }

    // ── Comments ──────────────────────────────────────────────────────────────

    /** Starts a comment on a scenario, given to the context it is made in (more can be chosen). */
    public function newComment(string $scenarioId, ?string $contextId = null): void
    {
        $this->resetErrorBag();
        $this->editingCommentId = null;
        $this->commentScenarioId = $this->testMatrix->scenarios()->findOrFail($scenarioId)->id;
        $this->commentTitle = '';
        $this->commentDescription = '';
        $this->commentStatus = TaskStatus::Todo->value;
        $this->commentContextIds = $contextId ? [$this->testMatrix->contexts()->findOrFail($contextId)->id] : [];

        $this->modal('result')->close();
        $this->modal('comment')->show();
    }

    public function openComment(string $commentId): void
    {
        $this->resetErrorBag();
        $comment = $this->testMatrix->comments()->with(['task', 'contexts'])->findOrFail($commentId);

        $this->editingCommentId = $comment->id;
        $this->commentScenarioId = $comment->test_scenario_id;
        $this->commentTitle = $comment->task->title;
        $this->commentDescription = $comment->task->description ?? '';
        $this->commentStatus = $comment->task->status->value;
        $this->commentContextIds = $comment->contexts->pluck('id')->all();

        $this->modal('comment')->show();
    }

    public function saveComment(TestMatrixGrid $grid): void
    {
        $this->validate([
            'commentTitle' => ['required', 'string', 'max:255'],
            'commentDescription' => ['nullable', 'string'],
            'commentStatus' => ['required', Rule::enum(TaskStatus::class)],
            'commentContextIds' => ['required', 'array', 'min:1'],
            'commentContextIds.*' => [Rule::exists('test_contexts', 'id')->where('test_matrix_id', $this->testMatrix->id)],
        ], ['commentContextIds.required' => 'Choose at least one context.', 'commentContextIds.min' => 'Choose at least one context.']);

        $task = ['title' => $this->commentTitle, 'description' => $this->commentDescription ?: null, 'status' => $this->commentStatus];

        if ($this->editingCommentId === null) {
            $grid->addComment($this->testMatrix->scenarios()->findOrFail($this->commentScenarioId), $this->commentContextIds, $task);
        } else {
            $grid->updateComment($this->testMatrix->comments()->findOrFail($this->editingCommentId), $task, $this->commentContextIds);
        }

        $this->modal('comment')->close();
        $this->refreshGrid();
        Flux::toast(variant: 'success', text: 'Comment saved.');
    }

    public function confirmDeleteComment(string $commentId): void
    {
        $this->deletingCommentId = $commentId;
        $this->modal('comment')->close();
        $this->modal('delete-comment')->show();
    }

    public function deleteComment(): void
    {
        $this->testMatrix->comments()->with('task')->findOrFail($this->deletingCommentId)->task->delete();
        $this->deletingCommentId = null;
        $this->modal('delete-comment')->close();
        $this->refreshGrid();
        Flux::toast(variant: 'success', text: 'Comment and its task deleted.');
    }

    // ── Keyboard shortcuts ────────────────────────────────────────────────────

    /**
     * What public/js/shortcuts.js asks for when a key is pressed on the grid: the cell, scenario, context or comment
     * that is selected, and the action. Each action does what its button does.
     */
    public function runShortcut(string $action, ?string $scenarioId = null, ?string $contextId = null, ?string $commentId = null): void
    {
        $cell = $scenarioId !== null && $contextId !== null;

        match (true) {
            $action === 'new-scenario' => $this->openScenario(),
            $action === 'new-context' => $this->openContext(),
            $action === 'edit-matrix' => $this->openMatrix(),
            $action === 'open-comment' && $commentId !== null => $this->openComment($commentId),
            $action === 'delete-comment' && $commentId !== null => $this->confirmDeleteComment($commentId),
            $action === 'edit-scenario' && $scenarioId !== null => $this->openScenario($scenarioId),
            $action === 'edit-context' && $contextId !== null => $this->openContext($contextId),
            $action === 'delete-scenario' && $scenarioId !== null => $this->confirmDeleteScenario($scenarioId),
            $action === 'delete-context' && $contextId !== null => $this->confirmDeleteContext($contextId),
            $action === 'move-scenario-up' && $scenarioId !== null => $this->moveScenario($scenarioId, 'up'),
            $action === 'move-scenario-down' && $scenarioId !== null => $this->moveScenario($scenarioId, 'down'),
            $action === 'move-context-left' && $contextId !== null => $this->moveContext($contextId, 'up'),
            $action === 'move-context-right' && $contextId !== null => $this->moveContext($contextId, 'down'),
            $action === 'cycle-status' && $cell => $this->cycleStatus($scenarioId, $contextId),
            $action === 'status-passed' && $cell => $this->setStatus($scenarioId, $contextId, TestResultStatus::Passed),
            $action === 'status-failed' && $cell => $this->setStatus($scenarioId, $contextId, TestResultStatus::Failed),
            $action === 'status-untested' && $cell => $this->setStatus($scenarioId, $contextId, TestResultStatus::Untested),
            $action === 'toggle-relevant' && $cell => $this->toggleRelevance($scenarioId, $contextId),
            $action === 'open-cell' && $cell => $this->openResult($scenarioId, $contextId),
            $action === 'new-comment' && $cell => $this->newComment($scenarioId, $contextId),
            default => null,
        };
    }

    private function setStatus(string $scenarioId, string $contextId, TestResultStatus $status): void
    {
        $this->resultFor($scenarioId, $contextId)->update(['status' => $status]);
        $this->refreshGrid();
    }

    private function toggleRelevance(string $scenarioId, string $contextId): void
    {
        $result = $this->resultFor($scenarioId, $contextId);
        $result->update(['relevant' => ! $result->relevant]);
        $this->refreshGrid();
    }

    // ── Data for the grid ─────────────────────────────────────────────────────

    /** @return Collection<int, TestContext> */
    #[Computed]
    public function contexts(): Collection
    {
        return $this->testMatrix->contexts()->get();
    }

    /** @return Collection<int, TestScenario> */
    #[Computed]
    public function scenarios(): Collection
    {
        return $this->testMatrix->scenarios()->with('automatedTests')->get();
    }

    /** @return Collection<string, TestResult> The cells, keyed "scenario id/context id". */
    #[Computed]
    public function results(): Collection
    {
        return $this->testMatrix->results()->get()->keyBy(fn (TestResult $result) => $result->test_scenario_id.'/'.$result->test_context_id);
    }

    /** @return Collection<int, TestComment> */
    #[Computed]
    public function comments(): Collection
    {
        return $this->testMatrix->comments()->with(['task', 'contexts', 'scenario'])->get();
    }

    /** @return array<string, int> How many comments that are not finished each cell has, keyed "scenario id/context id". */
    #[Computed]
    public function openCommentCounts(): array
    {
        $counts = [];

        foreach ($this->comments as $comment) {
            if (in_array($comment->task->status, [TaskStatus::Done, TaskStatus::Archived, TaskStatus::DeployedToMaster], true)) {
                continue;
            }

            foreach ($comment->contexts as $context) {
                $key = $comment->test_scenario_id.'/'.$context->id;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /** @return array{relevant: int, passed: int, failed: int, untested: int} */
    #[Computed]
    public function summary(): array
    {
        return $this->testMatrix->summary();
    }

    private function resultFor(string $scenarioId, string $contextId): TestResult
    {
        $scenario = $this->testMatrix->scenarios()->findOrFail($scenarioId);
        $context = $this->testMatrix->contexts()->findOrFail($contextId);

        return TestResult::firstOrCreate(['test_scenario_id' => $scenario->id, 'test_context_id' => $context->id]);
    }

    /** Swaps the item with its neighbour above ("up") or below ("down"). */
    private function move(Collection $items, string $id, string $direction): void
    {
        $ids = $items->pluck('id')->all();
        $at = array_search($id, $ids, true);
        $to = $direction === 'up' ? $at - 1 : $at + 1;

        if ($at === false || $to < 0 || $to >= count($ids)) {
            return;
        }

        [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];

        foreach ($ids as $index => $itemId) {
            $items->firstWhere('id', $itemId)->update(['order_index' => $index]);
        }
    }

    private function refreshGrid(): void
    {
        unset($this->contexts, $this->scenarios, $this->results, $this->comments, $this->openCommentCounts, $this->summary);

        // The keyboard selection goes back on the cell or comment it was on once the grid has been redrawn.
        $this->dispatch('test-matrix-refreshed');
    }
}; ?>

<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    data-view="test-matrix"
    data-test-matrices-url="{{ route('test-matrices', $epic) }}"
    x-on:test-matrix-action.window="$wire.runShortcut($event.detail.action, $event.detail.scenarioId, $event.detail.contextId, $event.detail.commentId)"
>
    {{-- Header --}}
    <div class="flex flex-col gap-1">
        <div class="flex items-center justify-between gap-2">
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('test-matrices', $epic)" wire:navigate />
            <div class="flex items-center gap-2">
                <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openMatrix">{{ __('Edit matrix') }}</flux:button>
                <flux:button variant="filled" size="sm" icon="plus" wire:click="openContext" data-action="add-context">{{ __('Add context') }}</flux:button>
                <flux:button variant="primary" size="sm" icon="plus" wire:click="openScenario" data-action="add-scenario">{{ __('Add scenario') }}</flux:button>
            </div>
        </div>
        <flux:heading size="xl">{{ $testMatrix->name }}</flux:heading>
        @if ($testMatrix->description)
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 break-words">{{ $testMatrix->description }}</flux:text>
        @endif
        <div class="flex flex-wrap items-center gap-2 text-xs" data-summary>
            <flux:badge size="sm" color="green">{{ __('Passed') }} · {{ $this->summary['passed'] }}</flux:badge>
            <flux:badge size="sm" color="red">{{ __('Failed') }} · {{ $this->summary['failed'] }}</flux:badge>
            <flux:badge size="sm" color="zinc">{{ __('Untested') }} · {{ $this->summary['untested'] }}</flux:badge>
            <span class="text-zinc-400">{{ __('Click a cell to mark it; the dots open details and comments.') }}</span>
        </div>
    </div>

    {{-- The grid --}}
    @if ($this->scenarios->isEmpty() || $this->contexts->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-16 dark:border-zinc-700 dark:bg-zinc-900/50" data-empty-grid>
            <flux:text class="text-base font-medium text-zinc-500 dark:text-zinc-400">{{ __('Add contexts (columns) and scenarios (rows) to start testing.') }}</flux:text>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full border-collapse text-sm" data-grid>
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-800/60">
                        <th class="sticky left-0 z-10 min-w-64 bg-zinc-50 px-3 py-2 text-left font-medium dark:bg-zinc-800">{{ __('Scenario') }}</th>
                        @foreach ($this->contexts as $context)
                            <th wire:key="context-{{ $context->id }}" class="px-1 py-2 text-center font-medium" data-context="{{ $context->id }}">
                                <flux:tooltip :content="$context->name.($context->description ? ' — '.$context->description : '')">
                                    <button
                                        type="button"
                                        wire:click="openContext('{{ $context->id }}')"
                                        title="{{ $context->name }}{{ $context->description ? ' — '.$context->description : '' }}"
                                        aria-label="{{ $context->name }}"
                                        class="rounded px-2 py-1 font-mono text-xs hover:bg-zinc-200 dark:hover:bg-zinc-700"
                                    >{{ $context->code }}</button>
                                </flux:tooltip>
                                <div class="flex justify-center gap-0.5 text-zinc-300 dark:text-zinc-600">
                                    <button type="button" wire:click="moveContext('{{ $context->id }}', 'up')" class="px-1 hover:text-zinc-600 dark:hover:text-zinc-300" aria-label="{{ __('Move context left') }}" @disabled($loop->first)>‹</button>
                                    <button type="button" wire:click="moveContext('{{ $context->id }}', 'down')" class="px-1 hover:text-zinc-600 dark:hover:text-zinc-300" aria-label="{{ __('Move context right') }}" @disabled($loop->last)>›</button>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->scenarios as $scenario)
                        <tr wire:key="scenario-{{ $scenario->id }}" class="border-t border-zinc-100 dark:border-zinc-800" data-scenario="{{ $scenario->id }}">
                            <th class="sticky left-0 z-10 bg-white px-3 py-2 text-left font-normal dark:bg-zinc-900">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <button type="button" wire:click="openScenario('{{ $scenario->id }}')" class="text-left font-medium hover:underline">{{ $scenario->title }}</button>
                                        @if ($scenario->automatedTests->isNotEmpty())
                                            <flux:tooltip :content="$scenario->automatedTests->map(fn ($test) => $test->name.($test->file_path ? ' ('.$test->file_path.')' : ''))->implode(', ')">
                                                <flux:badge size="sm" color="sky" class="ml-1" data-automated-tests>{{ trans_choice(':count automated test|:count automated tests', $scenario->automatedTests->count()) }}</flux:badge>
                                            </flux:tooltip>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 text-zinc-300 dark:text-zinc-600">
                                        <button type="button" wire:click="moveScenario('{{ $scenario->id }}', 'up')" class="px-1 hover:text-zinc-600 dark:hover:text-zinc-300" aria-label="{{ __('Move scenario up') }}" @disabled($loop->first)>▲</button>
                                        <button type="button" wire:click="moveScenario('{{ $scenario->id }}', 'down')" class="px-1 hover:text-zinc-600 dark:hover:text-zinc-300" aria-label="{{ __('Move scenario down') }}" @disabled($loop->last)>▼</button>
                                    </div>
                                </div>
                            </th>
                            @foreach ($this->contexts as $context)
                                @php
                                    $result = $this->results->get($scenario->id.'/'.$context->id);
                                    $openComments = $this->openCommentCounts[$scenario->id.'/'.$context->id] ?? 0;
                                @endphp
                                <td wire:key="cell-{{ $scenario->id }}-{{ $context->id }}" class="px-1 py-1 text-center" data-selectable data-cell="{{ $scenario->id }}/{{ $context->id }}">
                                    @if (! $result || ! $result->relevant)
                                        <button type="button" wire:click="openResult('{{ $scenario->id }}', '{{ $context->id }}')" title="{{ __('Not relevant here — click to change') }}" class="w-full rounded px-2 py-1 text-zinc-300 hover:bg-zinc-100 dark:text-zinc-600 dark:hover:bg-zinc-800" data-state="not-relevant">–</button>
                                    @else
                                        <div class="flex items-center justify-center gap-1">
                                            <button
                                                type="button"
                                                wire:click="cycleStatus('{{ $scenario->id }}', '{{ $context->id }}')"
                                                title="{{ $result->status->label() }}{{ $result->expectation ? ' — '.__('expected').': '.$result->expectation : '' }}"
                                                class="size-7 rounded text-sm font-semibold {{ match ($result->status) {
                                                    TestResultStatus::Passed => 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/40 dark:text-green-300',
                                                    TestResultStatus::Failed => 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300',
                                                    TestResultStatus::Untested => 'bg-zinc-100 text-zinc-400 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-500',
                                                } }}"
                                                data-state="{{ $result->status->value }}"
                                            >{{ match ($result->status) { TestResultStatus::Passed => '✓', TestResultStatus::Failed => '✗', TestResultStatus::Untested => '·' } }}</button>
                                            <button type="button" wire:click="openResult('{{ $scenario->id }}', '{{ $context->id }}')" class="rounded px-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800" aria-label="{{ __('Details and comments') }}" title="{{ __('Details and comments') }}">⋯</button>
                                            @if ($openComments > 0)
                                                <flux:badge size="sm" color="amber" data-comment-count>{{ $openComments }}</flux:badge>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Comments --}}
    <div class="space-y-2" data-comments>
        <flux:heading size="lg">{{ __('Comments') }}</flux:heading>
        @forelse ($this->comments as $comment)
            <div wire:key="comment-{{ $comment->id }}" class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900" data-selectable data-comment="{{ $comment->id }}">
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="openComment('{{ $comment->id }}')" class="font-medium hover:underline">{{ $comment->task->title }}</button>
                        <flux:badge size="sm" color="{{ $comment->task->status->color() }}">{{ $comment->task->status->label() }}</flux:badge>
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $comment->scenario->title }}</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($comment->contexts as $context)
                            <flux:tooltip :content="$context->name">
                                <flux:badge size="sm" color="zinc" class="font-mono">{{ $context->code }}</flux:badge>
                            </flux:tooltip>
                        @endforeach
                    </div>
                </div>
                <flux:button variant="ghost" size="sm" icon="arrow-top-right-on-square" :href="route('epics.board.task', [$epic, $comment->task_id])" wire:navigate :aria-label="__('Open the task')" />
            </div>
        @empty
            <flux:text class="text-sm text-zinc-400">{{ __('No comments yet. Open a cell to comment on a scenario for the contexts it affects.') }}</flux:text>
        @endforelse
    </div>

    {{-- Edit matrix --}}
    <flux:modal name="edit-test-matrix" :show="false" focusable class="md:w-[480px]">
        <form wire:submit="saveMatrix" @keydown.ctrl.enter.prevent="$wire.saveMatrix()" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit matrix') }}</flux:heading>
            <flux:input wire:model="matrixName" :label="__('Name')" required />
            <flux:textarea wire:model="matrixDescription" :label="__('Description (optional)')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Scenario --}}
    <flux:modal name="scenario" focusable class="md:w-[560px]">
        <form wire:submit="saveScenario" @keydown.ctrl.enter.prevent="$wire.saveScenario()" class="space-y-5">
            <flux:heading size="lg">{{ $editingScenarioId ? __('Edit scenario') : __('Add scenario') }}</flux:heading>
            <flux:input wire:model="scenarioTitle" :label="__('Title')" required autofocus />
            <flux:textarea wire:model="scenarioDescription" :label="__('Description (optional)')" rows="3" />

            <div>
                <flux:label>{{ __('Relevant in') }}</flux:label>
                <div class="mt-2 grid max-h-48 grid-cols-1 gap-1 overflow-y-auto sm:grid-cols-2" data-relevance>
                    @foreach ($this->contexts as $context)
                        <label class="flex cursor-pointer items-center gap-2 text-sm" title="{{ $context->name }}">
                            <input type="checkbox" wire:model="scenarioContextIds" value="{{ $context->id }}" class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600">
                            <span class="font-mono text-xs">{{ $context->code }}</span>
                            <span class="truncate text-zinc-500">{{ $context->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            @if ($editingScenarioId)
                @php
                    $editingScenario = $this->scenarios->firstWhere('id', $editingScenarioId);
                @endphp
                <div class="space-y-2" data-automated-tests-editor>
                    <flux:label>{{ __('Automated tests') }}</flux:label>
                    @forelse ($editingScenario?->automatedTests ?? [] as $test)
                        <div wire:key="automated-test-{{ $test->id }}" class="flex items-center justify-between gap-2 rounded border border-zinc-200 px-2 py-1 text-sm dark:border-zinc-700">
                            <div class="min-w-0">
                                <div class="truncate">{{ $test->name }} @if ($test->kind)<flux:badge size="sm" color="sky">{{ $test->kind }}</flux:badge>@endif</div>
                                @if ($test->file_path)<div class="truncate font-mono text-xs text-zinc-500">{{ $test->file_path }}</div>@endif
                            </div>
                            <flux:button type="button" variant="ghost" size="sm" icon="x-mark" wire:click="removeAutomatedTest('{{ $test->id }}')" :aria-label="__('Remove automated test')" />
                        </div>
                    @empty
                        <flux:text class="text-sm text-zinc-400">{{ __('None listed.') }}</flux:text>
                    @endforelse
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-6">
                        <flux:input class="sm:col-span-3" wire:model="automatedTestName" :placeholder="__('Test name')" />
                        <flux:input class="sm:col-span-2" wire:model="automatedTestFilePath" :placeholder="__('File path')" />
                        <flux:input class="sm:col-span-1" wire:model="automatedTestKind" :placeholder="__('Kind')" />
                    </div>
                    <flux:error name="automatedTestName" />
                    <flux:button type="button" size="sm" icon="plus" wire:click="addAutomatedTest">{{ __('Add automated test') }}</flux:button>
                </div>
            @else
                <flux:text class="text-sm text-zinc-400">{{ __('Automated tests can be listed once the scenario is saved.') }}</flux:text>
            @endif

            <div class="flex items-center justify-between gap-2">
                <div>
                    @if ($editingScenarioId)
                        <flux:button type="button" variant="danger" size="sm" wire:click="confirmDeleteScenario('{{ $editingScenarioId }}')">{{ __('Delete') }}</flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    {{-- Context --}}
    <flux:modal name="context" focusable class="md:w-[560px]">
        <form wire:submit="saveContext" @keydown.ctrl.enter.prevent="$wire.saveContext()" class="space-y-5">
            <flux:heading size="lg">{{ $editingContextId ? __('Edit context') : __('Add context') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('A context is one named combination, such as "Log in / Desktop / Offline". The code is the short column header.') }}</flux:text>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:input wire:model="contextCode" :label="__('Code')" required autofocus />
                <flux:input class="sm:col-span-2" wire:model="contextName" :label="__('Name')" required />
            </div>
            <flux:textarea wire:model="contextDescription" :label="__('Description (optional)')" rows="3" />

            <div>
                <flux:label>{{ __('Scenarios that are relevant here') }}</flux:label>
                <div class="mt-2 max-h-48 space-y-1 overflow-y-auto" data-relevance>
                    @foreach ($this->scenarios as $scenario)
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="contextScenarioIds" value="{{ $scenario->id }}" class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600">
                            <span class="truncate">{{ $scenario->title }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-between gap-2">
                <div>
                    @if ($editingContextId)
                        <flux:button type="button" variant="danger" size="sm" wire:click="confirmDeleteContext('{{ $editingContextId }}')">{{ __('Delete') }}</flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    {{-- Cell --}}
    <flux:modal name="result" focusable class="md:w-[560px]">
        @php
            $resultRecord = $editingResultId ? \App\Models\TestResult::with(['scenario', 'context'])->find($editingResultId) : null;
            $cellComments = $resultRecord ? $this->comments->filter(fn ($comment) => $comment->test_scenario_id === $resultRecord->test_scenario_id && $comment->contexts->contains('id', $resultRecord->test_context_id)) : collect();
        @endphp
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $resultRecord?->scenario->title }}</flux:heading>
                <flux:subheading>
                    <span class="font-mono">{{ $resultRecord?->context->code }}</span> — {{ $resultRecord?->context->name }}
                    @if ($resultRecord?->context->description)
                        <span class="block text-xs text-zinc-400">{{ $resultRecord->context->description }}</span>
                    @endif
                </flux:subheading>
            </div>

            <form wire:submit="saveResult" @keydown.ctrl.enter.prevent="$wire.saveResult()" class="space-y-4">
                <div class="flex flex-wrap items-center gap-4">
                    @foreach (TestResultStatus::cases() as $status)
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="resultStatus" value="{{ $status->value }}" class="border-zinc-300 text-blue-600 focus:ring-blue-500">
                            <flux:badge size="sm" color="{{ $status->color() }}">{{ $status->label() }}</flux:badge>
                        </label>
                    @endforeach
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="resultRelevant" class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600">
                    {{ __('This scenario is relevant in this context') }}
                </label>
                <flux:input wire:model="resultExpectation" :label="__('Expected here (optional)')" :placeholder="__('For example: Refused, or Read only')" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>

            <div class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700" data-cell-comments>
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">{{ __('Comments here') }}</flux:heading>
                    @if ($resultRecord)
                        <flux:button size="sm" icon="plus" wire:click="newComment('{{ $resultRecord->test_scenario_id }}', '{{ $resultRecord->test_context_id }}')">{{ __('Add comment') }}</flux:button>
                    @endif
                </div>
                @forelse ($cellComments as $comment)
                    <button type="button" wire:click="openComment('{{ $comment->id }}')" class="flex w-full items-center justify-between gap-2 rounded border border-zinc-200 px-2 py-1 text-left text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <span class="truncate">{{ $comment->task->title }}</span>
                        <flux:badge size="sm" color="{{ $comment->task->status->color() }}">{{ $comment->task->status->label() }}</flux:badge>
                    </button>
                @empty
                    <flux:text class="text-sm text-zinc-400">{{ __('No comments here.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </flux:modal>

    {{-- Comment --}}
    <flux:modal name="comment" focusable class="md:w-[560px]">
        <form wire:submit="saveComment" @keydown.ctrl.enter.prevent="$wire.saveComment()" class="space-y-5">
            <flux:heading size="lg">{{ $editingCommentId ? __('Edit comment') : __('Add comment') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('A comment is a task. Give it to every context it affects.') }}</flux:text>
            <flux:input wire:model="commentTitle" :label="__('Title')" required autofocus />
            <flux:textarea wire:model="commentDescription" :label="__('Description (optional)')" rows="4" />
            <flux:select wire:model="commentStatus" :label="__('Status')">
                @foreach (TaskStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div>
                <flux:label>{{ __('Affects') }}</flux:label>
                <div class="mt-2 grid max-h-48 grid-cols-1 gap-1 overflow-y-auto sm:grid-cols-2" data-comment-contexts>
                    @foreach ($this->contexts as $context)
                        <label class="flex cursor-pointer items-center gap-2 text-sm" title="{{ $context->name }}">
                            <input type="checkbox" wire:model="commentContextIds" value="{{ $context->id }}" class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600">
                            <span class="font-mono text-xs">{{ $context->code }}</span>
                            <span class="truncate text-zinc-500">{{ $context->name }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="commentContextIds" />
            </div>

            <div class="flex items-center justify-between gap-2">
                <div>
                    @if ($editingCommentId)
                        <flux:button type="button" variant="danger" size="sm" wire:click="confirmDeleteComment('{{ $editingCommentId }}')">{{ __('Delete') }}</flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    {{-- Deletions --}}
    @foreach ([
        'delete-scenario' => ['Delete scenario', 'This deletes the scenario with its cells and its list of automated tests.', 'deleteScenario'],
        'delete-context' => ['Delete context', 'This deletes the context with its cells. Comments given to it stay, without it.', 'deleteContext'],
        'delete-comment' => ['Delete comment', 'This deletes the comment and its task.', 'deleteComment'],
    ] as $modalName => [$heading, $text, $action])
        <flux:modal name="{{ $modalName }}" class="w-full sm:min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __($heading) }}</flux:heading>
                    <flux:text class="mt-2">{{ __($text) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="danger" wire:click="{{ $action }}">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endforeach
</div>
