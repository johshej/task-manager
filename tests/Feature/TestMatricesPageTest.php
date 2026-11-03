<?php

use App\Enums\TaskStatus;
use App\Enums\TestResultStatus;
use App\Models\AutomatedTest;
use App\Models\Epic;
use App\Models\TestContext;
use App\Models\TestMatrix;
use App\Models\TestResult;
use App\Models\TestScenario;
use App\Models\User;
use App\Services\TestMatrixGrid;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->epic = Epic::factory()->create(['name' => 'Sheet']);
    $this->matrix = TestMatrix::factory()->for($this->epic)->create(['name' => 'Roles and devices']);
    $this->grid = app(TestMatrixGrid::class);

    $this->desktop = $this->grid->addContext($this->matrix, ['code' => 'LD', 'name' => 'Log in / Desktop', 'description' => 'A logged in user on a desktop browser']);
    $this->tablet = $this->grid->addContext($this->matrix, ['code' => 'LT', 'name' => 'Log in / Tablet']);
    $this->offline = $this->grid->addContext($this->matrix, ['code' => 'LDO', 'name' => 'Log in / Desktop / Offline']);
    $this->login = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    $this->logout = $this->grid->addScenario($this->matrix, ['title' => 'Log out'], [$this->desktop->id]);
});

function showMatrix(): Testable
{
    return Livewire::test('pages::test-matrices.show', ['epic' => test()->epic, 'testMatrix' => test()->matrix]);
}

function cell(TestScenario $scenario, TestContext $context): TestResult
{
    return TestResult::where('test_scenario_id', $scenario->id)->where('test_context_id', $context->id)->firstOrFail();
}

// ── Index ─────────────────────────────────────────────────────────────────────

test('guests are redirected from the test matrices', function () {
    auth()->logout();

    $this->get(route('test-matrices', $this->epic))->assertRedirect(route('login'));
    $this->get(route('test-matrices.show', [$this->epic, $this->matrix]))->assertRedirect(route('login'));
});

test('the test matrices page lists the matrices of the epic with their results', function () {
    cell($this->login, $this->desktop)->update(['status' => TestResultStatus::Passed]);
    TestMatrix::factory()->create(['name' => 'Another epic']);

    $this->get(route('test-matrices', $this->epic))
        ->assertOk()
        ->assertSee('Roles and devices')
        ->assertSee('Passed · 1')
        ->assertSee('2 scenarios')
        ->assertSee('3 contexts')
        ->assertDontSee('Another epic');
});

test('can create a test matrix from the page', function () {
    Livewire::test('pages::test-matrices.index', ['epic' => $this->epic])
        ->set('name', 'Browsers')
        ->set('description', 'Across browsers')
        ->call('createMatrix')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('test_matrices', ['name' => 'Browsers', 'epic_id' => $this->epic->id, 'description' => 'Across browsers']);
});

test('a test matrix needs a name', function () {
    Livewire::test('pages::test-matrices.index', ['epic' => $this->epic])
        ->set('name', '')
        ->call('createMatrix')
        ->assertHasErrors(['name' => 'required']);
});

test('can delete a test matrix from the page', function () {
    Livewire::test('pages::test-matrices.index', ['epic' => $this->epic])
        ->call('confirmDeleteMatrix', $this->matrix->id)
        ->call('deleteMatrix');

    $this->assertModelMissing($this->matrix);
});

test('the epic board links to the test matrices', function () {
    $this->get(route('epics.board', $this->epic))->assertOk()->assertSee(route('test-matrices', $this->epic), false);
});

// ── The grid ──────────────────────────────────────────────────────────────────

test('the matrix page shows contexts as columns with their full name on hover, and scenarios as rows', function () {
    $this->get(route('test-matrices.show', [$this->epic, $this->matrix]))
        ->assertOk()
        ->assertSee('Roles and devices')
        ->assertSee('LD')
        ->assertSee('LDO')
        ->assertSee('Log in / Desktop — A logged in user on a desktop browser (Shift+E)')
        ->assertSee('Log in / Desktop / Offline (Shift+E)')
        ->assertSee('Log in')
        ->assertSee('Log out');
});

test('a matrix of another epic is not found under this epic', function () {
    $other = Epic::factory()->create();

    $this->get(route('test-matrices.show', [$other, $this->matrix]))->assertNotFound();
});

test('an empty matrix invites to add contexts and scenarios', function () {
    $empty = TestMatrix::factory()->for($this->epic)->create();

    $this->get(route('test-matrices.show', [$this->epic, $empty]))->assertOk()->assertSee('Add contexts (columns) and scenarios (rows)');
});

test('a cell is marked passed, failed and untested again by clicking it', function () {
    $component = showMatrix();
    $result = cell($this->login, $this->desktop);

    $component->call('cycleStatus', $this->login->id, $this->desktop->id);
    expect($result->fresh()->status)->toBe(TestResultStatus::Passed)->and($result->fresh()->tested_at)->not->toBeNull();

    $component->call('cycleStatus', $this->login->id, $this->desktop->id);
    expect($result->fresh()->status)->toBe(TestResultStatus::Failed);

    $component->call('cycleStatus', $this->login->id, $this->desktop->id);
    expect($result->fresh()->status)->toBe(TestResultStatus::Untested);
});

test('cells that are not relevant are shown as such', function () {
    showMatrix()->assertSeeHtml('data-state="not-relevant"');

    expect(cell($this->logout, $this->tablet)->relevant)->toBeFalse();
});

test('a cell can be marked not relevant, with what is expected there', function () {
    showMatrix()
        ->call('openResult', $this->login->id, $this->tablet->id)
        ->assertSet('resultStatus', 'untested')
        ->assertSet('resultRelevant', true)
        ->set('resultRelevant', false)
        ->set('resultExpectation', 'Refused')
        ->call('saveResult')
        ->assertHasNoErrors();

    $result = cell($this->login, $this->tablet);
    expect($result->relevant)->toBeFalse()->and($result->expectation)->toBe('Refused');
});

test('a cell that is not relevant can be made relevant and marked', function () {
    showMatrix()
        ->call('openResult', $this->logout->id, $this->tablet->id)
        ->set('resultRelevant', true)
        ->set('resultStatus', 'failed')
        ->call('saveResult');

    $result = cell($this->logout, $this->tablet);
    expect($result->relevant)->toBeTrue()->and($result->status)->toBe(TestResultStatus::Failed);
});

test('the summary shows how many relevant cells are passed, failed and untested', function () {
    cell($this->login, $this->desktop)->update(['status' => TestResultStatus::Passed]);
    cell($this->login, $this->tablet)->update(['status' => TestResultStatus::Failed]);

    showMatrix()->assertSee('Passed · 1')->assertSee('Failed · 1')->assertSee('Untested · 2');
});

// ── Scenarios ─────────────────────────────────────────────────────────────────

test('can add a scenario that is relevant in only some contexts', function () {
    showMatrix()
        ->call('openScenario')
        ->assertSet('scenarioContextIds', $this->matrix->contexts()->pluck('id')->all())
        ->set('scenarioTitle', 'Delete account')
        ->set('scenarioDescription', 'Removes all data')
        ->set('scenarioContextIds', [$this->tablet->id])
        ->call('saveScenario')
        ->assertHasNoErrors();

    $scenario = TestScenario::where('title', 'Delete account')->firstOrFail();
    expect(cell($scenario, $this->tablet)->relevant)->toBeTrue()
        ->and(cell($scenario, $this->desktop)->relevant)->toBeFalse()
        ->and($scenario->description)->toBe('Removes all data');
});

test('a scenario needs a title', function () {
    showMatrix()->call('openScenario')->set('scenarioTitle', '')->call('saveScenario')->assertHasErrors(['scenarioTitle' => 'required']);
});

test('can edit a scenario and where it is relevant', function () {
    showMatrix()
        ->call('openScenario', $this->login->id)
        ->assertSet('scenarioTitle', 'Log in')
        ->assertSet('scenarioContextIds', $this->matrix->contexts()->pluck('id')->all())
        ->set('scenarioTitle', 'Sign in')
        ->set('scenarioContextIds', [$this->desktop->id])
        ->call('saveScenario');

    expect($this->login->fresh()->title)->toBe('Sign in')
        ->and(cell($this->login, $this->desktop)->relevant)->toBeTrue()
        ->and(cell($this->login, $this->tablet)->relevant)->toBeFalse();
});

test('can delete a scenario', function () {
    showMatrix()
        ->call('confirmDeleteScenario', $this->login->id)
        ->call('deleteScenario');

    $this->assertModelMissing($this->login);
});

test('scenarios can be moved up and down', function () {
    showMatrix()->call('moveScenario', $this->logout->id, 'up');

    expect($this->matrix->scenarios()->pluck('title')->all())->toBe(['Log out', 'Log in']);

    showMatrix()->call('moveScenario', $this->logout->id, 'up');
    expect($this->matrix->scenarios()->pluck('title')->all())->toBe(['Log out', 'Log in']);
});

test('automated tests can be listed for a scenario and removed again', function () {
    $component = showMatrix()
        ->call('openScenario', $this->login->id)
        ->set('automatedTestName', 'logs in with e-mail')
        ->set('automatedTestFilePath', 'tests/Browser/LoginTest.php')
        ->set('automatedTestKind', 'browser')
        ->call('addAutomatedTest')
        ->assertHasNoErrors()
        ->assertSet('automatedTestName', '')
        ->assertSee('logs in with e-mail')
        ->assertSee('tests/Browser/LoginTest.php');

    $test = AutomatedTest::firstOrFail();
    expect($test->test_scenario_id)->toBe($this->login->id)->and($test->kind)->toBe('browser');

    $component->call('removeAutomatedTest', $test->id);
    expect(AutomatedTest::count())->toBe(0);
});

test('an automated test needs a name', function () {
    showMatrix()->call('openScenario', $this->login->id)->set('automatedTestName', '')->call('addAutomatedTest')->assertHasErrors(['automatedTestName' => 'required']);
});

test('the grid shows how many automated tests cover a scenario', function () {
    AutomatedTest::factory()->count(2)->for($this->login, 'scenario')->create();

    showMatrix()->assertSee('2 automated tests');
});

// ── Contexts ──────────────────────────────────────────────────────────────────

test('can add a context that is relevant for only some scenarios', function () {
    showMatrix()
        ->call('openContext')
        ->assertSet('contextScenarioIds', $this->matrix->scenarios()->pluck('id')->all())
        ->set('contextCode', 'GL')
        ->set('contextName', 'Guest / Phone')
        ->set('contextScenarioIds', [$this->login->id])
        ->call('saveContext')
        ->assertHasNoErrors();

    $context = TestContext::where('code', 'GL')->firstOrFail();
    expect(cell($this->login, $context)->relevant)->toBeTrue()->and(cell($this->logout, $context)->relevant)->toBeFalse();
});

test('a context needs a code that no other context of the matrix has', function () {
    showMatrix()->call('openContext')->set('contextCode', '')->set('contextName', '')->call('saveContext')->assertHasErrors(['contextCode', 'contextName']);

    showMatrix()->call('openContext')->set('contextCode', 'LD')->set('contextName', 'Duplicate')->call('saveContext')->assertHasErrors(['contextCode']);
});

test('can edit a context without tripping over its own code', function () {
    showMatrix()
        ->call('openContext', $this->desktop->id)
        ->assertSet('contextCode', 'LD')
        ->assertSet('contextDescription', 'A logged in user on a desktop browser')
        ->set('contextName', 'Log in / Desktop browser')
        ->set('contextScenarioIds', [$this->logout->id])
        ->call('saveContext')
        ->assertHasNoErrors();

    expect($this->desktop->fresh()->name)->toBe('Log in / Desktop browser')
        ->and(cell($this->login, $this->desktop)->relevant)->toBeFalse()
        ->and(cell($this->logout, $this->desktop)->relevant)->toBeTrue();
});

test('can delete a context', function () {
    showMatrix()->call('confirmDeleteContext', $this->offline->id)->call('deleteContext');

    $this->assertModelMissing($this->offline);
});

test('contexts can be moved left and right', function () {
    showMatrix()->call('moveContext', $this->offline->id, 'up');

    expect($this->matrix->contexts()->pluck('code')->all())->toBe(['LD', 'LDO', 'LT']);
});

// ── Comments ──────────────────────────────────────────────────────────────────

test('a comment made in a cell starts with that context and can be given to more', function () {
    showMatrix()
        ->call('newComment', $this->login->id, $this->tablet->id)
        ->assertSet('commentContextIds', [$this->tablet->id])
        ->set('commentTitle', 'Log in fails')
        ->set('commentDescription', 'The button does nothing')
        ->set('commentContextIds', [$this->tablet->id, $this->offline->id])
        ->call('saveComment')
        ->assertHasNoErrors();

    $comment = $this->matrix->comments()->with(['task', 'contexts'])->firstOrFail();
    expect($comment->task->title)->toBe('Log in fails')
        ->and($comment->task->status)->toBe(TaskStatus::Todo)
        ->and($comment->test_scenario_id)->toBe($this->login->id)
        ->and($comment->contexts->pluck('id')->all())->toEqualCanonicalizing([$this->tablet->id, $this->offline->id]);
});

test('a comment needs a title and at least one context', function () {
    showMatrix()
        ->call('newComment', $this->login->id)
        ->set('commentTitle', '')
        ->call('saveComment')
        ->assertHasErrors(['commentTitle', 'commentContextIds']);

    expect($this->matrix->comments()->count())->toBe(0);
});

test('a comment can be edited, including the contexts it is given to', function () {
    $comment = $this->grid->addComment($this->login, [$this->desktop->id], ['title' => 'Fails']);

    showMatrix()
        ->call('openComment', $comment->id)
        ->assertSet('commentTitle', 'Fails')
        ->assertSet('commentContextIds', [$this->desktop->id])
        ->set('commentTitle', 'Fails on two')
        ->set('commentStatus', 'active')
        ->set('commentContextIds', [$this->desktop->id, $this->tablet->id])
        ->call('saveComment');

    expect($comment->task->fresh()->title)->toBe('Fails on two')
        ->and($comment->task->fresh()->status)->toBe(TaskStatus::Active)
        ->and($comment->contexts()->pluck('test_contexts.id')->all())->toEqualCanonicalizing([$this->desktop->id, $this->tablet->id]);
});

test('deleting a comment deletes its task', function () {
    $comment = $this->grid->addComment($this->login, [$this->desktop->id], ['title' => 'Fails']);

    showMatrix()->call('confirmDeleteComment', $comment->id)->call('deleteComment');

    $this->assertModelMissing($comment);
    $this->assertDatabaseMissing('tasks', ['id' => $comment->task_id]);
});

test('the grid marks the cells that have open comments and the comment list names their contexts', function () {
    $this->grid->addComment($this->login, [$this->desktop->id, $this->tablet->id], ['title' => 'Log in fails']);
    $finished = $this->grid->addComment($this->logout, [$this->desktop->id], ['title' => 'Old problem', 'status' => TaskStatus::Done->value]);

    $component = showMatrix()
        ->assertSeeHtml('data-comment-count')
        ->assertSee('Log in fails')
        ->assertSee('Old problem');

    expect($component->get('openCommentCounts'))->toBe([
        $this->login->id.'/'.$this->desktop->id => 1,
        $this->login->id.'/'.$this->tablet->id => 1,
    ]);
});

test('the comments of a cell are listed when it is opened', function () {
    $this->grid->addComment($this->login, [$this->desktop->id], ['title' => 'Log in fails']);
    $this->grid->addComment($this->login, [$this->tablet->id], ['title' => 'Only on the tablet']);

    $html = showMatrix()->call('openResult', $this->login->id, $this->desktop->id)->html();
    $cellComments = substr($html, strpos($html, 'data-cell-comments'));

    expect($cellComments)->toContain('Log in fails')->not->toContain('Only on the tablet');
});

// ── Matrix ────────────────────────────────────────────────────────────────────

test('can rename the matrix', function () {
    showMatrix()->call('openMatrix')->assertSet('matrixName', 'Roles and devices')->set('matrixName', 'Everything')->call('saveMatrix')->assertHasNoErrors();

    expect($this->matrix->fresh()->name)->toBe('Everything');
});

// ── Keyboard shortcuts ────────────────────────────────────────────────────────

test('the grid page is wired to the keyboard script', function () {
    $this->get(route('test-matrices.show', [$this->epic, $this->matrix]))
        ->assertOk()
        ->assertSee('data-view="test-matrix"', false)
        ->assertSee('data-test-matrices-url="'.route('test-matrices', $this->epic).'"', false)
        ->assertSee('x-on:test-matrix-action.window', false)
        ->assertSee('data-selectable data-cell="'.$this->login->id.'/'.$this->desktop->id.'"', false);
});

test('the list page and the epic board are wired to the keyboard script', function () {
    $this->get(route('test-matrices', $this->epic))
        ->assertOk()
        ->assertSee('data-view="test-matrices-index"', false)
        ->assertSee('data-shortcut="new-test-matrix"', false)
        ->assertSee('data-selectable data-href="'.route('test-matrices.show', [$this->epic, $this->matrix]).'"', false)
        ->assertSee('data-delete-btn', false);

    $this->get(route('epics.board', $this->epic))->assertSee('data-test-matrices-url="'.route('test-matrices', $this->epic).'"', false);
});

test('comments are selectable with the keyboard', function () {
    $this->grid->addComment($this->login, [$this->desktop->id], ['title' => 'Log in fails']);

    $this->get(route('test-matrices.show', [$this->epic, $this->matrix]))->assertSee('data-selectable data-comment=', false);
});

test('the forms can be saved with Ctrl+Enter', function () {
    $html = $this->get(route('test-matrices.show', [$this->epic, $this->matrix]))->getContent();

    foreach (['saveMatrix', 'saveScenario', 'saveContext', 'saveResult', 'saveComment'] as $method) {
        expect($html)->toContain('@keydown.ctrl.enter.prevent="$wire.'.$method.'()"');
    }
});

test('the status shortcuts mark the selected cell', function () {
    $result = cell($this->login, $this->desktop);
    $component = showMatrix();

    $component->call('runShortcut', 'status-passed', $this->login->id, $this->desktop->id);
    expect($result->fresh()->status)->toBe(TestResultStatus::Passed);

    $component->call('runShortcut', 'status-failed', $this->login->id, $this->desktop->id);
    expect($result->fresh()->status)->toBe(TestResultStatus::Failed);

    $component->call('runShortcut', 'status-untested', $this->login->id, $this->desktop->id);
    expect($result->fresh()->status)->toBe(TestResultStatus::Untested);

    $component->call('runShortcut', 'cycle-status', $this->login->id, $this->desktop->id);
    expect($result->fresh()->status)->toBe(TestResultStatus::Passed);
});

test('the keyboard script is told to select the cell again after the grid is redrawn', function () {
    showMatrix()
        ->call('runShortcut', 'status-passed', $this->login->id, $this->desktop->id)
        ->assertDispatched('test-matrix-refreshed');
});

test('the relevance shortcut toggles the selected cell', function () {
    $component = showMatrix();

    $component->call('runShortcut', 'toggle-relevant', $this->login->id, $this->desktop->id);
    expect(cell($this->login, $this->desktop)->relevant)->toBeFalse();

    $component->call('runShortcut', 'toggle-relevant', $this->login->id, $this->desktop->id);
    expect(cell($this->login, $this->desktop)->relevant)->toBeTrue();
});

test('the open and comment shortcuts open the dialogs for the selected cell', function () {
    showMatrix()
        ->call('runShortcut', 'open-cell', $this->login->id, $this->desktop->id)
        ->assertSet('editingResultId', cell($this->login, $this->desktop)->id);

    showMatrix()
        ->call('runShortcut', 'new-comment', $this->login->id, $this->tablet->id)
        ->assertSet('commentScenarioId', $this->login->id)
        ->assertSet('commentContextIds', [$this->tablet->id]);
});

test('the edit and add shortcuts open the scenario, context and matrix dialogs', function () {
    showMatrix()->call('runShortcut', 'edit-scenario', $this->login->id, $this->desktop->id)->assertSet('editingScenarioId', $this->login->id);
    showMatrix()->call('runShortcut', 'edit-context', $this->login->id, $this->desktop->id)->assertSet('editingContextId', $this->desktop->id);
    showMatrix()->call('runShortcut', 'new-scenario')->assertSet('editingScenarioId', null)->assertSet('scenarioTitle', '');
    showMatrix()->call('runShortcut', 'new-context')->assertSet('editingContextId', null)->assertSet('contextCode', '');
    showMatrix()->call('runShortcut', 'edit-matrix')->assertSet('matrixName', 'Roles and devices');
});

test('the move shortcuts reorder scenarios and contexts', function () {
    $component = showMatrix();

    $component->call('runShortcut', 'move-scenario-up', $this->logout->id, $this->desktop->id);
    expect($this->matrix->scenarios()->pluck('title')->all())->toBe(['Log out', 'Log in']);

    $component->call('runShortcut', 'move-scenario-down', $this->logout->id, $this->desktop->id);
    expect($this->matrix->scenarios()->pluck('title')->all())->toBe(['Log in', 'Log out']);

    $component->call('runShortcut', 'move-context-right', $this->login->id, $this->desktop->id);
    expect($this->matrix->contexts()->pluck('code')->all())->toBe(['LT', 'LD', 'LDO']);

    $component->call('runShortcut', 'move-context-left', $this->login->id, $this->desktop->id);
    expect($this->matrix->contexts()->pluck('code')->all())->toBe(['LD', 'LT', 'LDO']);
});

test('the delete shortcuts ask before deleting a scenario, a context or a comment', function () {
    $comment = $this->grid->addComment($this->login, [$this->desktop->id], ['title' => 'Fails']);

    showMatrix()->call('runShortcut', 'delete-scenario', $this->login->id, $this->desktop->id)->assertSet('deletingScenarioId', $this->login->id);
    showMatrix()->call('runShortcut', 'delete-context', $this->login->id, $this->desktop->id)->assertSet('deletingContextId', $this->desktop->id);
    showMatrix()->call('runShortcut', 'delete-comment', null, null, $comment->id)->assertSet('deletingCommentId', $comment->id);

    // Nothing was deleted by asking.
    expect($this->matrix->scenarios()->count())->toBe(2)->and($this->matrix->contexts()->count())->toBe(3)->and($this->matrix->comments()->count())->toBe(1);
});

test('the open shortcut opens a comment', function () {
    $comment = $this->grid->addComment($this->login, [$this->desktop->id], ['title' => 'Fails']);

    showMatrix()->call('runShortcut', 'open-comment', null, null, $comment->id)->assertSet('editingCommentId', $comment->id)->assertSet('commentTitle', 'Fails');
});

test('shortcuts without what they need, and unknown ones, do nothing', function () {
    showMatrix()
        ->call('runShortcut', 'status-passed')
        ->call('runShortcut', 'cycle-status', $this->login->id)
        ->call('runShortcut', 'open-comment')
        ->call('runShortcut', 'edit-scenario')
        ->call('runShortcut', 'no-such-action', $this->login->id, $this->desktop->id)
        ->assertHasNoErrors();

    expect(cell($this->login, $this->desktop)->status)->toBe(TestResultStatus::Untested);
});

test('the keyboard script keeps Escape from going back while a dialog is open and lists the matrix shortcuts', function () {
    $script = file_get_contents(public_path('js/shortcuts.js'));

    expect($script)->toContain("document.querySelector('dialog[open]')")
        ->and($script)->toContain('_modalWasOpenOnEscape')
        ->and($script)->toContain('Test matrix grid');
});

// ── Shortcut tips and dialog keys ─────────────────────────────────────────────

/** The texts of the tooltips (the flux tooltips) that a rendered page contains. */
function tooltipTexts(string $html): array
{
    preg_match_all('/data-flux-tooltip-content[^>]*>\s*(.*?)\s*<\/div>/s', $html, $matches);

    return array_map(fn ($text) => html_entity_decode(trim($text)), $matches[1]);
}

test('the grid page shows the shortcut keys on hover', function () {
    $html = $this->get(route('test-matrices.show', [$this->epic, $this->matrix]))->assertOk()->getContent();

    expect(tooltipTexts($html))->toContain('Esc', 'M', 'Shift+N', 'N / +', 'Ctrl+Enter', 'Log in / Desktop — A logged in user on a desktop browser (Shift+E)');

    foreach (['(Shift+←)', '(Shift+→)', '(Shift+↑)', '(Shift+↓)', 'Edit scenario (E)', 'Details and comments (Enter)'] as $tip) {
        expect($html)->toContain($tip);
    }
});

test('the list page shows the shortcut keys on hover', function () {
    $html = $this->get(route('test-matrices', $this->epic))->assertOk()->getContent();

    expect(tooltipTexts($html))->toContain('Esc', 'N / +', 'Delete');
});

test('the forms can also be saved with Cmd+Enter and the keyboard script can find the buttons', function () {
    $html = $this->get(route('test-matrices.show', [$this->epic, $this->matrix]))->getContent();

    foreach (['saveMatrix', 'saveScenario', 'saveContext', 'saveResult', 'saveComment'] as $method) {
        expect($html)->toContain('@keydown.meta.enter.prevent="$wire.'.$method.'()"');
    }

    expect(substr_count($html, 'data-save-btn="data-save-btn"'))->toBe(5)
        ->and(substr_count($html, 'data-confirm-btn="data-confirm-btn"'))->toBe(3)
        ->and(substr_count($html, 'data-confirm>'))->toBe(3)
        ->and(tooltipTexts($html))->toContain('Enter');
});

test('the cell dialog has what its keys need', function () {
    $html = showMatrix()->call('openResult', $this->login->id, $this->desktop->id)->html();

    expect($html)->toContain('data-cell-dialog')
        ->and($html)->toContain('data-relevant-toggle')
        ->and($html)->toContain('data-add-comment-btn')
        ->and($html)->toContain('data-expectation-input')
        ->and(tooltipTexts($html))->toContain('P', 'X', 'U', 'R', 'E', 'C');
});

test('the lists of contexts and scenarios in the dialogs have All and None buttons', function () {
    $html = $this->get(route('test-matrices.show', [$this->epic, $this->matrix]))->getContent();

    expect(substr_count($html, 'data-checklist-head>'))->toBe(3)
        ->and(substr_count($html, 'data-checklist>'))->toBe(3)
        ->and(tooltipTexts($html))->toContain('A', 'N');
});

test('an automated test is added with Enter in its fields', function () {
    $html = showMatrix()->call('openScenario', $this->login->id)->html();

    expect(substr_count($html, 'wire:keydown.enter.prevent="addAutomatedTest"'))->toBe(3);
});

test('the list page can be saved and confirmed with the keyboard', function () {
    $html = $this->get(route('test-matrices', $this->epic))->getContent();

    expect($html)->toContain('@keydown.meta.enter.prevent="$wire.createMatrix()"')
        ->and($html)->toContain('data-save-btn="data-save-btn"')
        ->and($html)->toContain('data-confirm-btn="data-confirm-btn"')
        ->and(tooltipTexts($html))->toContain('Ctrl+Enter', 'Enter');
});

test('the keyboard script handles keys inside the matrix dialogs and lists them in the help', function () {
    $script = file_get_contents(public_path('js/shortcuts.js'));

    expect($script)->toContain('handleMatrixDialogKeys(e)')
        ->and($script)->toContain('Test matrix dialogs')
        ->and($script)->toContain("{ p: 'passed', x: 'failed', u: 'untested' }")
        ->and($script)->toContain('[data-expectation-input]')
        ->and($script)->toContain('e.ctrlKey || e.metaKey')
        ->and($script)->toContain('[data-confirm-btn]')
        ->and($script)->toContain('[data-checklist]');
});
