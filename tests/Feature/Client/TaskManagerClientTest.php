<?php

use Illuminate\Support\Facades\Http;
use TaskManager\Client\TaskManagerClient;
use TaskManager\Client\TaskManagerException;

function makeClient(): TaskManagerClient
{
    return new TaskManagerClient('https://tm.example.com', 'test-token');
}

// ── Epics ─────────────────────────────────────────────────────────────────────

test('listEpics returns array of epics', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => [
        ['id' => 'abc', 'name' => 'My Epic'],
    ]])]);

    $epics = makeClient()->listEpics();

    expect($epics)->toHaveCount(1)
        ->and($epics[0]['name'])->toBe('My Epic');
});

test('listEpics filters by repository_url', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => [
        ['id' => 'abc', 'repository_url' => 'git@github.com:user/repo.git'],
    ]])]);

    $epics = makeClient()->listEpics('git@github.com:user/repo.git');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'repository_url='));
    expect($epics[0]['repository_url'])->toBe('git@github.com:user/repo.git');
});

test('getEpic returns a single epic', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 'abc', 'name' => 'Epic']])]);

    $epic = makeClient()->getEpic('abc');

    expect($epic['id'])->toBe('abc');
});

test('createEpic sends POST and returns created epic', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 'new', 'name' => 'New Epic']], 201)]);

    $epic = makeClient()->createEpic(['name' => 'New Epic']);

    Http::assertSent(fn ($req) => $req->method() === 'POST' && $req->data()['name'] === 'New Epic');
    expect($epic['id'])->toBe('new');
});

test('updateEpic sends PUT and returns updated epic', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 'abc', 'name' => 'Renamed']])]);

    $epic = makeClient()->updateEpic('abc', ['name' => 'Renamed']);

    Http::assertSent(fn ($req) => $req->method() === 'PUT');
    expect($epic['name'])->toBe('Renamed');
});

test('deleteEpic sends DELETE', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(null, 204)]);

    makeClient()->deleteEpic('abc');

    Http::assertSent(fn ($req) => $req->method() === 'DELETE');
});

test('getEpicQueue returns tasks ordered by execution_order', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => [
        ['id' => 't1', 'execution_order' => 0],
        ['id' => 't2', 'execution_order' => 1],
    ]])]);

    $tasks = makeClient()->getEpicQueue('abc');

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/queue'));
    expect($tasks)->toHaveCount(2)
        ->and($tasks[0]['execution_order'])->toBe(0);
});

test('addEpicNote sends note to epic history', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 'h1', 'action' => 'note']], 201)]);

    $entry = makeClient()->addEpicNote('abc', ['message' => 'Started analysis']);

    Http::assertSent(fn ($req) => $req->data()['action'] === 'note'
        && $req->data()['metadata']['message'] === 'Started analysis'
    );
    expect($entry['action'])->toBe('note');
});

test('getEpicHistory returns history entries', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => [
        ['id' => 'h1', 'action' => 'created'],
    ]])]);

    $history = makeClient()->getEpicHistory('abc');

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/history'));
    expect($history)->toHaveCount(1)
        ->and($history[0]['action'])->toBe('created');
});

// ── Features ──────────────────────────────────────────────────────────────────

test('listFeatures returns features for an epic', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => [
        ['id' => 'f1', 'name' => 'Auth'],
    ]])]);

    $features = makeClient()->listFeatures('abc');

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/features'));
    expect($features)->toHaveCount(1)
        ->and($features[0]['name'])->toBe('Auth');
});

test('getFeature returns a single feature', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 'f1', 'name' => 'Auth']])]);

    $feature = makeClient()->getFeature('f1');

    expect($feature['id'])->toBe('f1');
});

test('createFeature sends POST and returns created feature', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 'f1', 'name' => 'Auth']], 201)]);

    $feature = makeClient()->createFeature(['epic_id' => 'abc', 'name' => 'Auth']);

    Http::assertSent(fn ($req) => $req->method() === 'POST' && $req->data()['name'] === 'Auth');
    expect($feature['id'])->toBe('f1');
});

test('addFeatureNote sends note to feature history', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 'h1', 'action' => 'note']], 201)]);

    $entry = makeClient()->addFeatureNote('f1', ['message' => 'Reviewed']);

    Http::assertSent(fn ($req) => $req->data()['action'] === 'note');
    expect($entry['action'])->toBe('note');
});

// ── Tasks ─────────────────────────────────────────────────────────────────────

test('listTasks returns tasks for a feature', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => [
        ['id' => 't1', 'title' => 'Write tests'],
    ]])]);

    $tasks = makeClient()->listTasks('f1');

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/tasks'));
    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]['title'])->toBe('Write tests');
});

test('getTask returns a single task', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 't1', 'title' => 'Write tests']])]);

    $task = makeClient()->getTask('t1');

    expect($task['id'])->toBe('t1');
});

test('createTask sends POST and returns created task', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 't1', 'title' => 'Write tests']], 201)]);

    $task = makeClient()->createTask(['feature_id' => 'f1', 'title' => 'Write tests']);

    Http::assertSent(fn ($req) => $req->method() === 'POST' && $req->data()['title'] === 'Write tests');
    expect($task['id'])->toBe('t1');
});

test('updateTaskStatus sends PATCH to status endpoint', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 't1', 'status' => 'done']])]);

    $task = makeClient()->updateTaskStatus('t1', 'done');

    Http::assertSent(fn ($req) => $req->method() === 'PATCH' && $req->data()['status'] === 'done');
    expect($task['status'])->toBe('done');
});

test('addTaskNote sends note to task history', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => ['id' => 'h1', 'action' => 'note']], 201)]);

    $entry = makeClient()->addTaskNote('t1', ['message' => 'Analyzed', 'model' => 'claude-sonnet-4-6']);

    Http::assertSent(fn ($req) => $req->data()['metadata']['message'] === 'Analyzed');
    expect($entry['action'])->toBe('note');
});

test('getTaskHistory returns history entries', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => [
        ['id' => 'h1', 'action' => 'created'],
    ]])]);

    $history = makeClient()->getTaskHistory('t1');

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/history'));
    expect($history)->toHaveCount(1)
        ->and($history[0]['action'])->toBe('created');
});

// ── Error handling ─────────────────────────────────────────────────────────────

test('throws TaskManagerException on 404', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    expect(fn () => makeClient()->getEpic('missing'))
        ->toThrow(TaskManagerException::class, 'Not Found');
});

test('throws TaskManagerException on 422 with message', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['message' => 'Validation failed'], 422)]);

    expect(fn () => makeClient()->createEpic([]))
        ->toThrow(TaskManagerException::class, 'Validation failed');
});

test('sends bearer token in all requests', function () {
    Http::fake(['https://tm.example.com/*' => Http::response(['data' => []])]);

    makeClient()->listEpics();

    Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer test-token'));
});
