<?php

use App\Enums\EpicStatus;
use App\Enums\TaskStatus;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\Task;
use App\Models\TestMatrix;
use App\Models\TestResult;
use App\Models\User;
use App\Services\TestMatrixGrid;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('g then e navigates to epics index from the epics index itself, not the edit route', function () {
    Epic::factory()->create(['name' => 'Selectable Epic', 'status' => EpicStatus::Active]);

    $page = visit(route('epics'));

    $page->assertNoJavaScriptErrors()
        ->keys('body', 'ArrowDown')
        ->keys('body', 'g')
        ->keys('body', 'e')
        ->assertUrlIs(route('epics'));
});

test('g then e navigates to epics index from the epic board, not the edit epic shortcut', function () {
    $epic = Epic::factory()->create(['name' => 'Board Epic', 'status' => EpicStatus::Active]);

    $page = visit(route('epics.board', $epic));

    $page->assertNoJavaScriptErrors()
        ->keys('body', 'g')
        ->keys('body', 'e')
        ->assertUrlIs(route('epics'));
});

test('kanban feature headers are selectable and reorderable with the keyboard', function () {
    $epic = Epic::factory()->create(['status' => EpicStatus::Active]);
    $featureOne = Feature::factory()->for($epic)->create(['name' => 'Feature One', 'order_index' => 0]);
    Task::factory()->for($featureOne)->create(['status' => TaskStatus::Todo, 'order_index' => 0]);
    $featureTwo = Feature::factory()->for($epic)->create(['name' => 'Feature Two', 'order_index' => 1]);
    Task::factory()->for($featureTwo)->create(['status' => TaskStatus::Todo, 'order_index' => 0]);

    $page = visit(route('epics.board.kanban', $epic));

    $page->assertNoJavaScriptErrors()
        ->click('Feature Two')
        ->keys('body', ['{Shift}', 'ArrowUp'])
        ->wait(0.5)
        ->assertSeeInOrder(['Feature Two', 'Feature One']);
});

test('kanban tasks within a feature are reorderable with the keyboard', function () {
    $epic = Epic::factory()->create(['status' => EpicStatus::Active]);
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['title' => 'First Task', 'status' => TaskStatus::Todo, 'order_index' => 0]);
    Task::factory()->for($feature)->create(['title' => 'Second Task', 'status' => TaskStatus::Todo, 'order_index' => 1]);

    $page = visit(route('epics.board.kanban', $epic));

    $page->assertNoJavaScriptErrors()
        ->click('Second Task')
        ->keys('body', ['{Shift}', 'ArrowUp'])
        ->wait(0.5)
        ->assertSeeInOrder(['Second Task', 'First Task']);
});

// ── Test matrices ─────────────────────────────────────────────────────────────

/** A matrix with two contexts and two scenarios, opened from the epic board so that Escape has a page to go back to. */
function openTestMatrixFromTheBoard(): array
{
    $epic = Epic::factory()->create(['status' => EpicStatus::Active]);
    $matrix = TestMatrix::factory()->for($epic)->create(['name' => 'Roles and devices']);
    $grid = app(TestMatrixGrid::class);
    $grid->addContext($matrix, ['code' => 'LD', 'name' => 'Log in / Desktop']);
    $grid->addContext($matrix, ['code' => 'LT', 'name' => 'Log in / Tablet']);
    $login = $grid->addScenario($matrix, ['title' => 'Log in']);
    $grid->addScenario($matrix, ['title' => 'Log out']);

    $page = visit(route('epics.board', $epic))
        ->keys('body', 'm')
        ->wait(1)
        ->keys('body', 'ArrowDown')
        ->keys('body', 'Enter')
        ->wait(1);

    return [$page, $epic, $matrix, $login];
}

test('m on the epic board opens its test matrices and Enter opens the selected matrix', function () {
    [$page, , $matrix] = openTestMatrixFromTheBoard();

    $page->assertNoJavaScriptErrors()->assertUrlIs(route('test-matrices.show', [$matrix->epic_id, $matrix]));
});

test('escape closes a dialog on the test matrix and does not leave the page', function () {
    [$page, , $matrix] = openTestMatrixFromTheBoard();

    $page->keys('body', 'e')
        ->wait(0.5)
        ->assertSee('Edit scenario')
        ->keys('body', 'Escape')
        ->wait(0.5)
        ->assertDontSee('Edit scenario')
        ->assertUrlIs(route('test-matrices.show', [$matrix->epic_id, $matrix]));
});

test('arrow keys select cells and p, x and u mark them', function () {
    [$page, , $matrix, $login] = openTestMatrixFromTheBoard();

    $page->keys('body', 'ArrowRight')->keys('body', 'p')->wait(0.7);
    expect(TestResult::where('test_scenario_id', $login->id)->orderBy('created_at')->get()->pluck('status.value')->sort()->values()->all())->toContain('passed');

    $page->keys('body', 'x')->wait(0.7);
    expect(TestResult::where('status', 'failed')->count())->toBe(1);

    $page->keys('body', 'u')->wait(0.7);
    expect(TestResult::where('status', 'failed')->count())->toBe(0);
});

test('n on the test matrix opens the add scenario dialog and the other shortcuts open theirs', function () {
    [$page] = openTestMatrixFromTheBoard();

    $page->keys('body', 'n')->wait(0.5)->assertSee('Add scenario')->keys('body', 'Escape')->wait(0.5)
        ->keys('body', ['{Shift}', 'N'])->wait(0.5)->assertSee('Add context')->keys('body', 'Escape')->wait(0.5)
        ->keys('body', 'm')->wait(0.5)->assertSee('Edit matrix');
});
