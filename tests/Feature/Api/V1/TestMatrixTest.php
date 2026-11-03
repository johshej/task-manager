<?php

use App\Enums\TaskStatus;
use App\Enums\TestResultStatus;
use App\Models\AutomatedTest;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\Task;
use App\Models\TestComment;
use App\Models\TestContext;
use App\Models\TestMatrix;
use App\Models\TestResult;
use App\Models\TestScenario;
use App\Models\User;
use App\Services\TestMatrixGrid;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->epic = Epic::factory()->create();
    $this->matrix = TestMatrix::factory()->for($this->epic)->create(['name' => 'Roles and devices']);
    $this->grid = app(TestMatrixGrid::class);
});

// ── Matrices ──────────────────────────────────────────────────────────────────

test('the test matrix api requires a token', function () {
    $this->getJson("/api/v1/epics/{$this->epic->id}/test-matrices")->assertUnauthorized();
    $this->getJson("/api/v1/test-matrices/{$this->matrix->id}")->assertUnauthorized();
});

test('can list the test matrices of an epic', function () {
    TestMatrix::factory()->for($this->epic)->create();
    TestMatrix::factory()->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/epics/{$this->epic->id}/test-matrices")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('can create a test matrix at the bottom of the epic', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/test-matrices', ['epic_id' => $this->epic->id, 'name' => 'Second', 'description' => 'More tests'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Second')
        ->assertJsonPath('data.epic_id', $this->epic->id)
        ->assertJsonPath('data.order_index', $this->matrix->order_index + 1);

    $this->assertDatabaseHas('test_matrices', ['name' => 'Second', 'epic_id' => $this->epic->id]);
});

test('a test matrix needs a name and an existing epic', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/test-matrices', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'epic_id']);
});

test('can update and delete a test matrix', function () {
    $this->withToken($this->token)
        ->putJson("/api/v1/test-matrices/{$this->matrix->id}", ['name' => 'Renamed'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Renamed');

    $this->withToken($this->token)
        ->deleteJson("/api/v1/test-matrices/{$this->matrix->id}")
        ->assertNoContent();

    $this->assertModelMissing($this->matrix);
});

test('showing a test matrix returns the whole grid', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Log in / Desktop']);
    $tablet = $this->grid->addContext($this->matrix, ['code' => 'TB', 'name' => 'Log in / Tablet']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    AutomatedTest::factory()->for($scenario, 'scenario')->create(['name' => 'logs in']);

    $this->withToken($this->token)
        ->getJson("/api/v1/test-matrices/{$this->matrix->id}")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.contexts')
        ->assertJsonCount(1, 'data.scenarios')
        ->assertJsonCount(2, 'data.results')
        ->assertJsonPath('data.scenarios.0.automated_tests.0.name', 'logs in')
        ->assertJsonPath('data.summary', ['relevant' => 2, 'passed' => 0, 'failed' => 0, 'untested' => 2]);
});

test('the summary counts only the cells that are relevant', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $tablet = $this->grid->addContext($this->matrix, ['code' => 'TB', 'name' => 'Tablet']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in'], [$desktop->id]);
    TestResult::where('test_context_id', $desktop->id)->update(['status' => TestResultStatus::Failed]);

    expect($this->matrix->fresh()->summary())->toBe(['relevant' => 1, 'passed' => 0, 'failed' => 1, 'untested' => 0]);
});

// ── Contexts ──────────────────────────────────────────────────────────────────

test('can create a context and it gets a cell in every scenario', function () {
    $first = $this->grid->addScenario($this->matrix, ['title' => 'First']);
    $second = $this->grid->addScenario($this->matrix, ['title' => 'Second']);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/test-contexts', [
            'test_matrix_id' => $this->matrix->id,
            'code' => 'LD',
            'name' => 'Log in / Desktop',
            'description' => 'A logged in user on a desktop browser',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'LD')
        ->assertJsonPath('data.name', 'Log in / Desktop')
        ->assertJsonPath('data.description', 'A logged in user on a desktop browser');

    expect(TestResult::where('test_context_id', $response->json('data.id'))->where('relevant', true)->count())->toBe(2);
});

test('a new context can be relevant for only some scenarios', function () {
    $first = $this->grid->addScenario($this->matrix, ['title' => 'First']);
    $second = $this->grid->addScenario($this->matrix, ['title' => 'Second']);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/test-contexts', [
            'test_matrix_id' => $this->matrix->id,
            'code' => 'LD',
            'name' => 'Log in / Desktop',
            'test_scenario_ids' => [$second->id],
        ])
        ->assertCreated();

    $contextId = $response->json('data.id');
    expect(TestResult::where('test_scenario_id', $first->id)->where('test_context_id', $contextId)->value('relevant'))->toBeFalse()
        ->and(TestResult::where('test_scenario_id', $second->id)->where('test_context_id', $contextId)->value('relevant'))->toBeTrue();
});

test('context codes are unique within a matrix only', function () {
    TestContext::factory()->for($this->matrix, 'matrix')->create(['code' => 'DT']);
    $other = TestMatrix::factory()->for($this->epic)->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/test-contexts', ['test_matrix_id' => $this->matrix->id, 'code' => 'DT', 'name' => 'Duplicate'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->withToken($this->token)
        ->postJson('/api/v1/test-contexts', ['test_matrix_id' => $other->id, 'code' => 'DT', 'name' => 'Same code elsewhere'])
        ->assertCreated();
});

test('can list, show, update and delete contexts', function () {
    $context = TestContext::factory()->for($this->matrix, 'matrix')->create(['code' => 'DT', 'name' => 'Desktop']);

    $this->withToken($this->token)->getJson("/api/v1/test-matrices/{$this->matrix->id}/contexts")->assertSuccessful()->assertJsonCount(1, 'data');
    $this->withToken($this->token)->getJson("/api/v1/test-contexts/{$context->id}")->assertSuccessful()->assertJsonPath('data.code', 'DT');
    $this->withToken($this->token)
        ->putJson("/api/v1/test-contexts/{$context->id}", ['name' => 'Desktop / Offline', 'code' => 'DO'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Desktop / Offline')
        ->assertJsonPath('data.code', 'DO');
    $this->withToken($this->token)->deleteJson("/api/v1/test-contexts/{$context->id}")->assertNoContent();

    $this->assertModelMissing($context);
});

test('deleting a context removes its cells', function () {
    $context = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    expect(TestResult::count())->toBe(1);

    $this->withToken($this->token)->deleteJson("/api/v1/test-contexts/{$context->id}")->assertNoContent();

    expect(TestResult::count())->toBe(0);
});

// ── Scenarios ─────────────────────────────────────────────────────────────────

test('can create a scenario and it gets a cell in every context', function () {
    $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $this->grid->addContext($this->matrix, ['code' => 'TB', 'name' => 'Tablet']);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/test-scenarios', ['test_matrix_id' => $this->matrix->id, 'title' => 'Log in', 'description' => 'With e-mail'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Log in');

    expect(TestResult::where('test_scenario_id', $response->json('data.id'))->where('relevant', true)->count())->toBe(2);
});

test('a new scenario can be relevant in only some contexts', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $tablet = $this->grid->addContext($this->matrix, ['code' => 'TB', 'name' => 'Tablet']);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/test-scenarios', ['test_matrix_id' => $this->matrix->id, 'title' => 'Log in', 'test_context_ids' => [$tablet->id]])
        ->assertCreated();

    $scenarioId = $response->json('data.id');
    expect(TestResult::where('test_scenario_id', $scenarioId)->where('test_context_id', $desktop->id)->value('relevant'))->toBeFalse()
        ->and(TestResult::where('test_scenario_id', $scenarioId)->where('test_context_id', $tablet->id)->value('relevant'))->toBeTrue();
});

test('a scenario cannot name contexts from another matrix', function () {
    $foreign = TestContext::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/test-scenarios', ['test_matrix_id' => $this->matrix->id, 'title' => 'Log in', 'test_context_ids' => [$foreign->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('test_context_ids.0');
});

test('relevance of a scenario can be set for all its contexts at once', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $tablet = $this->grid->addContext($this->matrix, ['code' => 'TB', 'name' => 'Tablet']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);

    $this->withToken($this->token)
        ->putJson("/api/v1/test-scenarios/{$scenario->id}/relevance", ['test_context_ids' => [$desktop->id]])
        ->assertSuccessful();

    expect(TestResult::where('test_scenario_id', $scenario->id)->where('test_context_id', $desktop->id)->value('relevant'))->toBeTrue()
        ->and(TestResult::where('test_scenario_id', $scenario->id)->where('test_context_id', $tablet->id)->value('relevant'))->toBeFalse();

    $this->withToken($this->token)
        ->putJson("/api/v1/test-scenarios/{$scenario->id}/relevance", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('test_context_ids');
});

test('can list, show, update and delete scenarios', function () {
    $scenario = TestScenario::factory()->for($this->matrix, 'matrix')->create(['title' => 'Log in']);
    AutomatedTest::factory()->for($scenario, 'scenario')->create(['name' => 'logs in']);

    $this->withToken($this->token)->getJson("/api/v1/test-matrices/{$this->matrix->id}/scenarios")->assertSuccessful()->assertJsonCount(1, 'data');
    $this->withToken($this->token)->getJson("/api/v1/test-scenarios/{$scenario->id}")->assertSuccessful()->assertJsonPath('data.automated_tests.0.name', 'logs in');
    $this->withToken($this->token)->putJson("/api/v1/test-scenarios/{$scenario->id}", ['title' => 'Sign in'])->assertSuccessful()->assertJsonPath('data.title', 'Sign in');
    $this->withToken($this->token)->deleteJson("/api/v1/test-scenarios/{$scenario->id}")->assertNoContent();

    $this->assertModelMissing($scenario);
    expect(AutomatedTest::count())->toBe(0);
});

test('new scenarios and contexts go to the bottom', function () {
    $first = $this->grid->addScenario($this->matrix, ['title' => 'First']);
    $second = $this->grid->addScenario($this->matrix, ['title' => 'Second']);
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $tablet = $this->grid->addContext($this->matrix, ['code' => 'TB', 'name' => 'Tablet']);

    expect($second->order_index)->toBe($first->order_index + 1)
        ->and($tablet->order_index)->toBe($desktop->order_index + 1)
        ->and($this->matrix->scenarios()->pluck('title')->all())->toBe(['First', 'Second']);
});

// ── Results ───────────────────────────────────────────────────────────────────

test('can mark a cell passed or failed, and the time of the test is kept', function () {
    $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    $result = TestResult::first();
    expect($result->status)->toBe(TestResultStatus::Untested)->and($result->tested_at)->toBeNull();

    $this->withToken($this->token)
        ->patchJson("/api/v1/test-results/{$result->id}", ['status' => 'failed'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'failed');
    expect($result->fresh()->tested_at)->not->toBeNull();

    $this->withToken($this->token)
        ->patchJson("/api/v1/test-results/{$result->id}", ['status' => 'untested'])
        ->assertSuccessful();
    expect($result->fresh()->tested_at)->toBeNull();
});

test('can mark a scenario not relevant in a context and say what is expected there', function () {
    $this->grid->addContext($this->matrix, ['code' => 'GL', 'name' => 'Guest via link']);
    $this->grid->addScenario($this->matrix, ['title' => 'Edit a sheet']);
    $result = TestResult::first();

    $this->withToken($this->token)
        ->patchJson("/api/v1/test-results/{$result->id}", ['relevant' => false, 'expectation' => 'Refused'])
        ->assertSuccessful()
        ->assertJsonPath('data.relevant', false)
        ->assertJsonPath('data.expectation', 'Refused');
});

test('a result status must be a known one', function () {
    $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $this->grid->addScenario($this->matrix, ['title' => 'Log in']);

    $this->withToken($this->token)
        ->patchJson('/api/v1/test-results/'.TestResult::first()->id, ['status' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('can list and show the results of a matrix', function () {
    $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    $this->grid->addScenario($this->matrix, ['title' => 'Log out']);

    $this->withToken($this->token)->getJson("/api/v1/test-matrices/{$this->matrix->id}/results")->assertSuccessful()->assertJsonCount(2, 'data');
    $this->withToken($this->token)->getJson('/api/v1/test-results/'.TestResult::first()->id)->assertSuccessful()->assertJsonPath('data.relevant', true);
});

// ── Comments ──────────────────────────────────────────────────────────────────

test('a comment is a task, given to the contexts it affects, in a feature made for the matrix', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $tablet = $this->grid->addContext($this->matrix, ['code' => 'TB', 'name' => 'Tablet']);
    $phone = $this->grid->addContext($this->matrix, ['code' => 'PH', 'name' => 'Phone']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/test-comments', [
            'test_scenario_id' => $scenario->id,
            'test_context_ids' => [$tablet->id, $phone->id],
            'title' => 'Log in fails',
            'description' => 'The button does nothing',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Log in fails')
        ->assertJsonPath('data.status', 'todo')
        ->assertJsonPath('data.test_scenario_id', $scenario->id)
        ->assertJsonPath('data.test_matrix_id', $this->matrix->id);

    expect($response->json('data.test_context_ids'))->toEqualCanonicalizing([$tablet->id, $phone->id]);

    $feature = $this->matrix->fresh()->feature;
    expect($feature)->not->toBeNull()
        ->and($feature->epic_id)->toBe($this->epic->id)
        ->and($feature->name)->toBe('Test findings: Roles and devices');

    $task = Task::findOrFail($response->json('data.task_id'));
    expect($task->feature_id)->toBe($feature->id)->and($task->title)->toBe('Log in fails');
});

test('comments of a matrix share one feature', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);

    foreach (['One', 'Two'] as $title) {
        $this->withToken($this->token)
            ->postJson('/api/v1/test-comments', ['test_scenario_id' => $scenario->id, 'test_context_ids' => [$desktop->id], 'title' => $title])
            ->assertCreated();
    }

    expect(Feature::where('epic_id', $this->epic->id)->count())->toBe(1)
        ->and($this->matrix->fresh()->feature->tasks()->orderBy('order_index')->pluck('title')->all())->toBe(['One', 'Two']);
});

test('a comment can use an existing feature chosen for the matrix', function () {
    $feature = Feature::factory()->for($this->epic)->create(['name' => 'Findings']);
    $this->matrix->update(['feature_id' => $feature->id]);
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);

    $this->withToken($this->token)
        ->postJson('/api/v1/test-comments', ['test_scenario_id' => $scenario->id, 'test_context_ids' => [$desktop->id], 'title' => 'Fails'])
        ->assertCreated();

    expect($feature->tasks()->count())->toBe(1)->and(Feature::where('epic_id', $this->epic->id)->count())->toBe(1);
});

test('a comment needs a title, a scenario and at least one context of that matrix', function () {
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    $foreign = TestContext::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/test-comments', ['test_scenario_id' => $scenario->id, 'test_context_ids' => [], 'title' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'test_context_ids']);

    $this->withToken($this->token)
        ->postJson('/api/v1/test-comments', ['test_scenario_id' => $scenario->id, 'test_context_ids' => [$foreign->id], 'title' => 'Fails'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('test_context_ids.0');
});

test('can list, show and update a comment and the contexts it is given to', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $tablet = $this->grid->addContext($this->matrix, ['code' => 'TB', 'name' => 'Tablet']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    $comment = $this->grid->addComment($scenario, [$desktop->id], ['title' => 'Fails']);

    $this->withToken($this->token)->getJson("/api/v1/test-matrices/{$this->matrix->id}/comments")->assertSuccessful()->assertJsonCount(1, 'data');
    $this->withToken($this->token)->getJson("/api/v1/test-comments/{$comment->id}")->assertSuccessful()->assertJsonPath('data.test_context_ids', [$desktop->id]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/test-comments/{$comment->id}", ['title' => 'Log in fails on two', 'status' => TaskStatus::Active->value, 'test_context_ids' => [$desktop->id, $tablet->id]])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Log in fails on two')
        ->assertJsonPath('data.status', 'active');

    expect($response->json('data.test_context_ids'))->toEqualCanonicalizing([$desktop->id, $tablet->id])
        ->and($comment->task->fresh()->status)->toBe(TaskStatus::Active);
});

test('deleting a comment deletes its task, and deleting its task deletes the comment', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    $first = $this->grid->addComment($scenario, [$desktop->id], ['title' => 'First']);
    $second = $this->grid->addComment($scenario, [$desktop->id], ['title' => 'Second']);

    $this->withToken($this->token)->deleteJson("/api/v1/test-comments/{$first->id}")->assertNoContent();
    $this->assertModelMissing($first);
    $this->assertDatabaseMissing('tasks', ['id' => $first->task_id]);

    $this->withToken($this->token)->deleteJson("/api/v1/tasks/{$second->task_id}")->assertNoContent();
    $this->assertModelMissing($second);
});

test('deleting a context removes it from its comments but keeps the comment', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $tablet = $this->grid->addContext($this->matrix, ['code' => 'TB', 'name' => 'Tablet']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    $comment = $this->grid->addComment($scenario, [$desktop->id, $tablet->id], ['title' => 'Fails']);

    $this->withToken($this->token)->deleteJson("/api/v1/test-contexts/{$tablet->id}")->assertNoContent();

    expect($comment->fresh()->contexts->pluck('id')->all())->toBe([$desktop->id]);
});

// ── Automated tests ───────────────────────────────────────────────────────────

test('can list the automated tests of a scenario', function () {
    $scenario = TestScenario::factory()->for($this->matrix, 'matrix')->create();
    AutomatedTest::factory()->count(2)->for($scenario, 'scenario')->create();
    AutomatedTest::factory()->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/test-scenarios/{$scenario->id}/automated-tests")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('can create, show, update and delete an automated test', function () {
    $scenario = TestScenario::factory()->for($this->matrix, 'matrix')->create();

    $created = $this->withToken($this->token)
        ->postJson('/api/v1/automated-tests', [
            'test_scenario_id' => $scenario->id,
            'name' => 'logs in with e-mail',
            'file_path' => 'tests/Browser/LoginTest.php',
            'kind' => 'browser',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'logs in with e-mail')
        ->assertJsonPath('data.file_path', 'tests/Browser/LoginTest.php')
        ->assertJsonPath('data.kind', 'browser');
    $id = $created->json('data.id');

    $this->withToken($this->token)->getJson("/api/v1/automated-tests/{$id}")->assertSuccessful()->assertJsonPath('data.test_scenario_id', $scenario->id);
    $this->withToken($this->token)->putJson("/api/v1/automated-tests/{$id}", ['name' => 'signs in'])->assertSuccessful()->assertJsonPath('data.name', 'signs in');
    $this->withToken($this->token)->deleteJson("/api/v1/automated-tests/{$id}")->assertNoContent();

    $this->assertDatabaseMissing('automated_tests', ['id' => $id]);
});

test('an automated test needs a name and an existing scenario', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/automated-tests', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'test_scenario_id']);
});

test('deleting an epic removes its test matrices with everything in them', function () {
    $desktop = $this->grid->addContext($this->matrix, ['code' => 'DT', 'name' => 'Desktop']);
    $scenario = $this->grid->addScenario($this->matrix, ['title' => 'Log in']);
    AutomatedTest::factory()->for($scenario, 'scenario')->create();
    $this->grid->addComment($scenario, [$desktop->id], ['title' => 'Fails']);

    $this->epic->delete();

    expect(TestMatrix::count())->toBe(0)->and(TestContext::count())->toBe(0)->and(TestScenario::count())->toBe(0)
        ->and(TestResult::count())->toBe(0)->and(AutomatedTest::count())->toBe(0)->and(TestComment::count())->toBe(0);
});
