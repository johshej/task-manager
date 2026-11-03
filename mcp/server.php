#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * tm-mcp — Model Context Protocol server for task-manager.hejslet.dk
 *
 * Speaks JSON-RPC 2.0 over stdio. Spawned by an MCP client (Claude Code etc.).
 *
 * Config: walks up from the client's CWD (or $TM_CWD env) to find a `.task-manager`
 * file, then reads URL / TOKEN / EPIC_ID / ACTIVE_TASK_ID / ACTIVE_FEATURE_ID.
 *
 * Tools mirror the task-manager-client skill:
 *   - task_get         — get a task
 *   - task_start       — mark active + set ACTIVE_TASK_ID in .task-manager
 *   - task_status      — set task status
 *   - task_note        — add note to task history
 *   - task_complete    — mark done + add summary note + clear ACTIVE_TASK_ID
 *   - task_block       — mark blocked + add note with reason
 *   - task_skip        — clear ACTIVE_TASK_ID without changing status
 *   - feature_get      — get a feature
 *   - feature_start    — mark active + set ACTIVE_FEATURE_ID
 *   - feature_status   — set feature status
 *   - feature_note     — add note to feature history
 *   - feature_create   — create a feature under an epic, appended to the bottom
 *   - task_create      — create a task under a feature, appended to the bottom
 *   - epic_list        — list all epics with full IDs
 *   - epic_note        — add note to epic history
 *   - test_matrix_*    — list/get/create/update/delete a test matrix (scenarios as rows, contexts as columns)
 *   - test_context_*   — create/update/delete a context (a column: one named combination such as "Log in / Desktop")
 *   - test_scenario_*  — create/update/delete a scenario (a row) and set in which contexts it is relevant
 *   - test_result_set  — mark a cell passed/failed/untested, relevant or not
 *   - test_comment_*   — list/create/update/delete comments; a comment is a task given to one or more contexts
 *   - automated_test_* — list/create/update/delete the automated tests that cover a scenario
 *   - config_show      — show resolved .task-manager config (token masked)
 */
const PROTOCOL_VERSION = '2025-06-18';
const SERVER_NAME = 'tm-mcp';
const SERVER_VERSION = '0.1.0';

// ---------------------------------------------------------------------------
// Config resolution
// ---------------------------------------------------------------------------

function tm_find_config(?string $startDir = null): ?string
{
    $dir = $startDir ?? (getenv('TM_CWD') ?: getcwd());
    if ($dir === false) {
        return null;
    }
    $dir = realpath($dir) ?: $dir;
    while ($dir && $dir !== '/' && $dir !== '.') {
        $candidate = $dir.'/.task-manager';
        if (is_file($candidate)) {
            return $candidate;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    return null;
}

function tm_parse_config(string $path): array
{
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (! preg_match('/^([A-Z_]+)=(.*)$/', $line, $m)) {
            continue;
        }
        $val = trim($m[2]);
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        $out[$m[1]] = $val;
    }

    return $out;
}

function tm_write_config(string $path, array $cfg): void
{
    $lines = [];
    foreach ($cfg as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $lines[] = "$k=$v";
    }
    file_put_contents($path, implode("\n", $lines)."\n");
}

function tm_load(?string $cwd = null): array
{
    $path = tm_find_config($cwd);
    if ($path === null) {
        throw new RuntimeException('No .task-manager file found (walked up from '.($cwd ?? getcwd()).').');
    }
    $cfg = tm_parse_config($path);
    if (empty($cfg['URL']) || empty($cfg['TOKEN'])) {
        throw new RuntimeException("Config at $path is missing URL or TOKEN.");
    }
    $cfg['_path'] = $path;

    return $cfg;
}

// ---------------------------------------------------------------------------
// HTTP
// ---------------------------------------------------------------------------

function tm_http(array $cfg, string $method, string $path, ?array $body = null): array
{
    $url = rtrim($cfg['URL'], '/').'/api/v1'.$path;
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer '.$cfg['TOKEN'],
        'Accept: application/json',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP error: $err");
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $resp, true);
    if ($code >= 400) {
        $msg = is_array($decoded) ? json_encode($decoded) : (string) $resp;
        throw new RuntimeException("HTTP $code on $method $path: $msg");
    }

    return is_array($decoded) ? $decoded : ['raw' => $resp];
}

// ---------------------------------------------------------------------------
// Tools
// ---------------------------------------------------------------------------

function tm_tools(): array
{
    $cwdProp = ['type' => 'string', 'description' => 'Project working directory (where .task-manager lives). Optional; defaults to TM_CWD env or process CWD.'];
    $idProp = ['type' => 'string', 'description' => 'UUID. Optional — falls back to ACTIVE_TASK_ID / ACTIVE_FEATURE_ID in .task-manager.'];

    return [
        ['name' => 'task_get', 'description' => 'Get a task by id.',
            'inputSchema' => ['type' => 'object', 'properties' => ['task_id' => $idProp, 'cwd' => $cwdProp]]],

        ['name' => 'task_start', 'description' => 'Start a task: PATCH status=active and write ACTIVE_TASK_ID into .task-manager.',
            'inputSchema' => ['type' => 'object', 'required' => ['task_id'],
                'properties' => ['task_id' => $idProp, 'cwd' => $cwdProp]]],

        ['name' => 'task_status', 'description' => 'Set task status (todo|active|blocked|building_automated_tests|running_automated_tests|done|archived|merged_to_staging|deployed_to_staging|merged_to_master|deployed_to_master).',
            'inputSchema' => ['type' => 'object', 'required' => ['status'],
                'properties' => ['task_id' => $idProp, 'status' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'task_note', 'description' => 'Add a note to a task history.',
            'inputSchema' => ['type' => 'object', 'required' => ['body'],
                'properties' => ['task_id' => $idProp, 'body' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'task_complete', 'description' => 'Complete a task: set status=done, post a summary note, clear ACTIVE_TASK_ID.',
            'inputSchema' => ['type' => 'object', 'required' => ['summary'],
                'properties' => ['task_id' => $idProp, 'summary' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'task_block', 'description' => 'Block a task: set status=blocked, post a note with the reason.',
            'inputSchema' => ['type' => 'object', 'required' => ['reason'],
                'properties' => ['task_id' => $idProp, 'reason' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'task_skip', 'description' => 'Stop working on the active task without changing its status. Clears ACTIVE_TASK_ID.',
            'inputSchema' => ['type' => 'object', 'properties' => ['cwd' => $cwdProp]]],

        ['name' => 'task_create', 'description' => 'Create a task under a feature, appended to the bottom of the task list (order_index computed automatically, status defaults to todo).',
            'inputSchema' => ['type' => 'object', 'required' => ['title'],
                'properties' => [
                    'feature_id' => $idProp,
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'description' => 'Defaults to todo.'],
                    'priority' => ['type' => 'integer'],
                    'tdd' => ['type' => 'boolean'],
                    'ai_mode' => ['type' => 'string'],
                    'environment' => ['type' => 'string'],
                    'cwd' => $cwdProp,
                ]]],

        ['name' => 'feature_get', 'description' => 'Get a feature by id.',
            'inputSchema' => ['type' => 'object', 'properties' => ['feature_id' => $idProp, 'cwd' => $cwdProp]]],

        ['name' => 'feature_start', 'description' => 'Start a feature: PUT status=active, write ACTIVE_FEATURE_ID.',
            'inputSchema' => ['type' => 'object', 'required' => ['feature_id'],
                'properties' => ['feature_id' => $idProp, 'cwd' => $cwdProp]]],

        ['name' => 'feature_status', 'description' => 'Set feature status (todo|active|done|archived|merged_to_staging|deployed_to_staging|merged_to_master|deployed_to_master).',
            'inputSchema' => ['type' => 'object', 'required' => ['status'],
                'properties' => ['feature_id' => $idProp, 'status' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'feature_note', 'description' => 'Add a note to a feature history.',
            'inputSchema' => ['type' => 'object', 'required' => ['body'],
                'properties' => ['feature_id' => $idProp, 'body' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'feature_create', 'description' => 'Create a feature under an epic, appended to the bottom of the feature list (order_index computed automatically, status defaults to todo).',
            'inputSchema' => ['type' => 'object', 'required' => ['name'],
                'properties' => [
                    'epic_id' => ['type' => 'string', 'description' => 'UUID. Optional — falls back to EPIC_ID in .task-manager.'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'description' => 'Defaults to todo.'],
                    'tdd' => ['type' => 'boolean'],
                    'ai_mode' => ['type' => 'string'],
                    'environment' => ['type' => 'string'],
                    'cwd' => $cwdProp,
                ]]],

        ['name' => 'epic_list', 'description' => 'List all epics with their full IDs, names, and statuses.',
            'inputSchema' => ['type' => 'object', 'properties' => ['cwd' => $cwdProp]]],

        ['name' => 'epic_note', 'description' => 'Add a note to the epic history.',
            'inputSchema' => ['type' => 'object', 'required' => ['body'],
                'properties' => ['body' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'test_matrix_list', 'description' => 'List the test matrices of an epic (scenarios as rows, contexts as columns), with how many cells are passed, failed and untested.',
            'inputSchema' => ['type' => 'object', 'properties' => ['epic_id' => ['type' => 'string', 'description' => 'UUID. Optional — falls back to EPIC_ID in .task-manager.'], 'cwd' => $cwdProp]]],

        ['name' => 'test_matrix_get', 'description' => 'Get a whole test matrix: contexts (columns), scenarios (rows, with their automated tests) and every cell (result id, relevant, status, expectation).',
            'inputSchema' => ['type' => 'object', 'required' => ['test_matrix_id'], 'properties' => ['test_matrix_id' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'test_matrix_create', 'description' => 'Create a test matrix in an epic.',
            'inputSchema' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                'epic_id' => ['type' => 'string', 'description' => 'UUID. Optional — falls back to EPIC_ID in .task-manager.'],
                'name' => ['type' => 'string'], 'description' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'test_matrix_update', 'description' => 'Update a test matrix (name, description, or the feature that collects the tasks made from comments).',
            'inputSchema' => ['type' => 'object', 'required' => ['test_matrix_id'], 'properties' => [
                'test_matrix_id' => ['type' => 'string'], 'name' => ['type' => 'string'], 'description' => ['type' => 'string'],
                'feature_id' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'test_matrix_delete', 'description' => 'Delete a test matrix with its contexts, scenarios, results and comment links (the comment tasks stay).',
            'inputSchema' => ['type' => 'object', 'required' => ['test_matrix_id'], 'properties' => ['test_matrix_id' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'test_context_create', 'description' => 'Add a context (a column) to a test matrix: one named combination such as "Log in / Desktop / Offline". The code is the short header; name and description give the full meaning. It gets a cell in every scenario.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_matrix_id', 'code', 'name'], 'properties' => [
                'test_matrix_id' => ['type' => 'string'], 'code' => ['type' => 'string', 'description' => 'Short, unique within the matrix, max 20 characters.'],
                'name' => ['type' => 'string'], 'description' => ['type' => 'string'],
                'test_scenario_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Only these scenarios are relevant in the context. Default: all.'],
                'cwd' => $cwdProp]]],

        ['name' => 'test_context_update', 'description' => 'Update a context (code, name, description, order_index).',
            'inputSchema' => ['type' => 'object', 'required' => ['test_context_id'], 'properties' => [
                'test_context_id' => ['type' => 'string'], 'code' => ['type' => 'string'], 'name' => ['type' => 'string'],
                'description' => ['type' => 'string'], 'order_index' => ['type' => 'integer'], 'cwd' => $cwdProp]]],

        ['name' => 'test_context_delete', 'description' => 'Delete a context with its cells.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_context_id'], 'properties' => ['test_context_id' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'test_scenario_create', 'description' => 'Add a scenario (a row) to a test matrix. It gets a cell in every context.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_matrix_id', 'title'], 'properties' => [
                'test_matrix_id' => ['type' => 'string'], 'title' => ['type' => 'string'], 'description' => ['type' => 'string'],
                'test_context_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Only these contexts are relevant for the scenario. Default: all.'],
                'cwd' => $cwdProp]]],

        ['name' => 'test_scenario_update', 'description' => 'Update a scenario (title, description, order_index).',
            'inputSchema' => ['type' => 'object', 'required' => ['test_scenario_id'], 'properties' => [
                'test_scenario_id' => ['type' => 'string'], 'title' => ['type' => 'string'], 'description' => ['type' => 'string'],
                'order_index' => ['type' => 'integer'], 'cwd' => $cwdProp]]],

        ['name' => 'test_scenario_relevance', 'description' => 'Make a scenario relevant in exactly the listed contexts and not relevant in the others.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_scenario_id', 'test_context_ids'], 'properties' => [
                'test_scenario_id' => ['type' => 'string'], 'test_context_ids' => ['type' => 'array', 'items' => ['type' => 'string']], 'cwd' => $cwdProp]]],

        ['name' => 'test_scenario_delete', 'description' => 'Delete a scenario with its cells and automated tests.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_scenario_id'], 'properties' => ['test_scenario_id' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'test_result_set', 'description' => 'Set a cell: status (untested|passed|failed), relevant (false = the scenario does not apply in that context), expectation (what is expected when not the plain "works", such as "Refused"). Identify the cell by test_result_id, or by test_scenario_id and test_context_id.',
            'inputSchema' => ['type' => 'object', 'properties' => [
                'test_result_id' => ['type' => 'string'], 'test_scenario_id' => ['type' => 'string'], 'test_context_id' => ['type' => 'string'],
                'status' => ['type' => 'string'], 'relevant' => ['type' => 'boolean'], 'expectation' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'test_comment_list', 'description' => 'List the comments of a test matrix. A comment is a task: it has title, description, status and the test_context_ids it is given to.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_matrix_id'], 'properties' => ['test_matrix_id' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'test_comment_create', 'description' => 'Comment on a scenario for one or more contexts it affects. Makes a task (status todo) in the feature that collects the matrix\'s comments.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_scenario_id', 'test_context_ids', 'title'], 'properties' => [
                'test_scenario_id' => ['type' => 'string'], 'test_context_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'At least one context of the scenario\'s matrix.'],
                'title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'status' => ['type' => 'string'], 'priority' => ['type' => 'integer'], 'cwd' => $cwdProp]]],

        ['name' => 'test_comment_update', 'description' => 'Update a comment: title, description, status, priority, or the contexts it is given to.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_comment_id'], 'properties' => [
                'test_comment_id' => ['type' => 'string'], 'title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'status' => ['type' => 'string'],
                'priority' => ['type' => 'integer'], 'test_context_ids' => ['type' => 'array', 'items' => ['type' => 'string']], 'cwd' => $cwdProp]]],

        ['name' => 'test_comment_delete', 'description' => 'Delete a comment together with its task.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_comment_id'], 'properties' => ['test_comment_id' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'automated_test_list', 'description' => 'List the automated tests that cover a scenario.',
            'inputSchema' => ['type' => 'object', 'required' => ['test_scenario_id'], 'properties' => ['test_scenario_id' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'automated_test_create', 'description' => 'List an automated test as covering a scenario: its name and where it is (file_path), and its kind (feature, browser, unit …).',
            'inputSchema' => ['type' => 'object', 'required' => ['test_scenario_id', 'name'], 'properties' => [
                'test_scenario_id' => ['type' => 'string'], 'name' => ['type' => 'string'], 'file_path' => ['type' => 'string'],
                'kind' => ['type' => 'string'], 'description' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'automated_test_update', 'description' => 'Update an automated test of a scenario.',
            'inputSchema' => ['type' => 'object', 'required' => ['automated_test_id'], 'properties' => [
                'automated_test_id' => ['type' => 'string'], 'name' => ['type' => 'string'], 'file_path' => ['type' => 'string'],
                'kind' => ['type' => 'string'], 'description' => ['type' => 'string'], 'order_index' => ['type' => 'integer'], 'cwd' => $cwdProp]]],

        ['name' => 'automated_test_delete', 'description' => 'Remove an automated test from a scenario.',
            'inputSchema' => ['type' => 'object', 'required' => ['automated_test_id'], 'properties' => ['automated_test_id' => ['type' => 'string'], 'cwd' => $cwdProp]]],

        ['name' => 'config_show', 'description' => 'Show resolved .task-manager config (token masked).',
            'inputSchema' => ['type' => 'object', 'properties' => ['cwd' => $cwdProp]]],
    ];
}

function tm_require(array $cfg, string $key, ?string $override, string $label): string
{
    if ($override !== null && $override !== '') {
        return $override;
    }
    if (! empty($cfg[$key])) {
        return $cfg[$key];
    }
    throw new RuntimeException("Missing $label: pass it explicitly or set $key in .task-manager.");
}

function tm_pick(array $args, array $keys, array $extra = []): array
{
    $payload = $extra;
    foreach ($keys as $key) {
        if (array_key_exists($key, $args) && $args[$key] !== null) {
            $payload[$key] = $args[$key];
        }
    }

    return $payload;
}

function tm_set_active(array $cfg, ?string $taskId, ?string $featureId): void
{
    $path = $cfg['_path'];
    $parsed = tm_parse_config($path);
    unset($parsed['ACTIVE_TASK_ID'], $parsed['ACTIVE_FEATURE_ID']);
    if ($taskId) {
        $parsed['ACTIVE_TASK_ID'] = $taskId;
    }
    if ($featureId) {
        $parsed['ACTIVE_FEATURE_ID'] = $featureId;
    }
    tm_write_config($path, $parsed);
}

function tm_call_tool(string $name, array $args): array
{
    $cwd = $args['cwd'] ?? null;
    $cfg = tm_load($cwd);

    switch ($name) {
        case 'task_get':
            $id = tm_require($cfg, 'ACTIVE_TASK_ID', $args['task_id'] ?? null, 'task_id');

            return tm_http($cfg, 'GET', "/tasks/$id");

        case 'task_start':
            $id = $args['task_id'];
            $res = tm_http($cfg, 'PATCH', "/tasks/$id/status", ['status' => 'active']);
            tm_set_active($cfg, $id, null);

            return ['ok' => true, 'task' => $res, 'active_task_id' => $id];

        case 'task_status':
            $id = tm_require($cfg, 'ACTIVE_TASK_ID', $args['task_id'] ?? null, 'task_id');

            return tm_http($cfg, 'PATCH', "/tasks/$id/status", ['status' => $args['status']]);

        case 'task_note':
            $id = tm_require($cfg, 'ACTIVE_TASK_ID', $args['task_id'] ?? null, 'task_id');

            return tm_http($cfg, 'POST', "/tasks/$id/history", ['action' => 'note', 'body' => $args['body']]);

        case 'task_complete':
            $id = tm_require($cfg, 'ACTIVE_TASK_ID', $args['task_id'] ?? null, 'task_id');
            $status = tm_http($cfg, 'PATCH', "/tasks/$id/status", ['status' => 'done']);
            $note = tm_http($cfg, 'POST', "/tasks/$id/history",
                ['action' => 'note', 'body' => 'Done. '.$args['summary']]);
            $parsed = tm_parse_config($cfg['_path']);
            if (($parsed['ACTIVE_TASK_ID'] ?? null) === $id) {
                unset($parsed['ACTIVE_TASK_ID']);
                tm_write_config($cfg['_path'], $parsed);
            }

            return ['ok' => true, 'status' => $status, 'note' => $note];

        case 'task_block':
            $id = tm_require($cfg, 'ACTIVE_TASK_ID', $args['task_id'] ?? null, 'task_id');
            $status = tm_http($cfg, 'PATCH', "/tasks/$id/status", ['status' => 'blocked']);
            $note = tm_http($cfg, 'POST', "/tasks/$id/history",
                ['action' => 'note', 'body' => 'Blocked: '.$args['reason']]);

            return ['ok' => true, 'status' => $status, 'note' => $note];

        case 'task_skip':
            $parsed = tm_parse_config($cfg['_path']);
            $was = $parsed['ACTIVE_TASK_ID'] ?? null;
            unset($parsed['ACTIVE_TASK_ID']);
            tm_write_config($cfg['_path'], $parsed);

            return ['ok' => true, 'cleared_active_task_id' => $was];

        case 'task_create':
            $featureId = tm_require($cfg, 'ACTIVE_FEATURE_ID', $args['feature_id'] ?? null, 'feature_id');
            $existing = tm_http($cfg, 'GET', "/features/$featureId/tasks");
            $payload = array_filter([
                'feature_id' => $featureId,
                'title' => $args['title'],
                'description' => $args['description'] ?? null,
                'status' => $args['status'] ?? 'todo',
                'priority' => $args['priority'] ?? null,
                'order_index' => count($existing['data'] ?? []),
                'tdd' => $args['tdd'] ?? null,
                'ai_mode' => $args['ai_mode'] ?? null,
                'environment' => $args['environment'] ?? null,
            ], fn ($v) => $v !== null);

            return tm_http($cfg, 'POST', '/tasks', $payload);

        case 'feature_get':
            $id = tm_require($cfg, 'ACTIVE_FEATURE_ID', $args['feature_id'] ?? null, 'feature_id');

            return tm_http($cfg, 'GET', "/features/$id");

        case 'feature_start':
            $id = $args['feature_id'];
            $res = tm_http($cfg, 'PUT', "/features/$id", ['status' => 'active']);
            tm_set_active($cfg, null, $id);

            return ['ok' => true, 'feature' => $res, 'active_feature_id' => $id];

        case 'feature_status':
            $id = tm_require($cfg, 'ACTIVE_FEATURE_ID', $args['feature_id'] ?? null, 'feature_id');

            return tm_http($cfg, 'PUT', "/features/$id", ['status' => $args['status']]);

        case 'feature_note':
            $id = tm_require($cfg, 'ACTIVE_FEATURE_ID', $args['feature_id'] ?? null, 'feature_id');

            return tm_http($cfg, 'POST', "/features/$id/history", ['action' => 'note', 'body' => $args['body']]);

        case 'feature_create':
            $epicId = tm_require($cfg, 'EPIC_ID', $args['epic_id'] ?? null, 'epic_id');
            $existing = tm_http($cfg, 'GET', "/epics/$epicId/features");
            $payload = array_filter([
                'epic_id' => $epicId,
                'name' => $args['name'],
                'description' => $args['description'] ?? null,
                'status' => $args['status'] ?? 'todo',
                'order_index' => count($existing['data'] ?? []),
                'tdd' => $args['tdd'] ?? null,
                'ai_mode' => $args['ai_mode'] ?? null,
                'environment' => $args['environment'] ?? null,
            ], fn ($v) => $v !== null);

            return tm_http($cfg, 'POST', '/features', $payload);

        case 'epic_list':
            return tm_http($cfg, 'GET', '/epics');

        case 'epic_note':
            $epic = tm_require($cfg, 'EPIC_ID', null, 'EPIC_ID');

            return tm_http($cfg, 'POST', "/epics/$epic/history", ['action' => 'note', 'body' => $args['body']]);

        case 'test_matrix_list':
            $epicId = tm_require($cfg, 'EPIC_ID', $args['epic_id'] ?? null, 'epic_id');

            return tm_http($cfg, 'GET', "/epics/$epicId/test-matrices");

        case 'test_matrix_get':
            return tm_http($cfg, 'GET', '/test-matrices/'.$args['test_matrix_id']);

        case 'test_matrix_create':
            $epicId = tm_require($cfg, 'EPIC_ID', $args['epic_id'] ?? null, 'epic_id');

            return tm_http($cfg, 'POST', '/test-matrices', tm_pick($args, ['name', 'description'], ['epic_id' => $epicId]));

        case 'test_matrix_update':
            return tm_http($cfg, 'PUT', '/test-matrices/'.$args['test_matrix_id'], tm_pick($args, ['name', 'description', 'feature_id']));

        case 'test_matrix_delete':
            return tm_http($cfg, 'DELETE', '/test-matrices/'.$args['test_matrix_id']) + ['ok' => true];

        case 'test_context_create':
            return tm_http($cfg, 'POST', '/test-contexts', tm_pick($args, ['test_matrix_id', 'code', 'name', 'description', 'test_scenario_ids']));

        case 'test_context_update':
            return tm_http($cfg, 'PUT', '/test-contexts/'.$args['test_context_id'], tm_pick($args, ['code', 'name', 'description', 'order_index']));

        case 'test_context_delete':
            return tm_http($cfg, 'DELETE', '/test-contexts/'.$args['test_context_id']) + ['ok' => true];

        case 'test_scenario_create':
            return tm_http($cfg, 'POST', '/test-scenarios', tm_pick($args, ['test_matrix_id', 'title', 'description', 'test_context_ids']));

        case 'test_scenario_update':
            return tm_http($cfg, 'PUT', '/test-scenarios/'.$args['test_scenario_id'], tm_pick($args, ['title', 'description', 'order_index']));

        case 'test_scenario_relevance':
            return tm_http($cfg, 'PUT', '/test-scenarios/'.$args['test_scenario_id'].'/relevance', ['test_context_ids' => $args['test_context_ids']]);

        case 'test_scenario_delete':
            return tm_http($cfg, 'DELETE', '/test-scenarios/'.$args['test_scenario_id']) + ['ok' => true];

        case 'test_result_set':
            $resultId = $args['test_result_id'] ?? null;
            if ($resultId === null) {
                if (empty($args['test_scenario_id']) || empty($args['test_context_id'])) {
                    throw new RuntimeException('Pass test_result_id, or both test_scenario_id and test_context_id.');
                }
                $scenario = tm_http($cfg, 'GET', '/test-scenarios/'.$args['test_scenario_id']);
                $results = tm_http($cfg, 'GET', '/test-matrices/'.($scenario['data']['test_matrix_id'] ?? '').'/results');
                foreach ($results['data'] ?? [] as $result) {
                    if ($result['test_scenario_id'] === $args['test_scenario_id'] && $result['test_context_id'] === $args['test_context_id']) {
                        $resultId = $result['id'];
                    }
                }
                if ($resultId === null) {
                    throw new RuntimeException('No cell for that scenario and context.');
                }
            }

            return tm_http($cfg, 'PATCH', "/test-results/$resultId", tm_pick($args, ['status', 'relevant', 'expectation']));

        case 'test_comment_list':
            return tm_http($cfg, 'GET', '/test-matrices/'.$args['test_matrix_id'].'/comments');

        case 'test_comment_create':
            return tm_http($cfg, 'POST', '/test-comments', tm_pick($args, ['test_scenario_id', 'test_context_ids', 'title', 'description', 'status', 'priority']));

        case 'test_comment_update':
            return tm_http($cfg, 'PUT', '/test-comments/'.$args['test_comment_id'], tm_pick($args, ['title', 'description', 'status', 'priority', 'test_context_ids']));

        case 'test_comment_delete':
            return tm_http($cfg, 'DELETE', '/test-comments/'.$args['test_comment_id']) + ['ok' => true];

        case 'automated_test_list':
            return tm_http($cfg, 'GET', '/test-scenarios/'.$args['test_scenario_id'].'/automated-tests');

        case 'automated_test_create':
            return tm_http($cfg, 'POST', '/automated-tests', tm_pick($args, ['test_scenario_id', 'name', 'file_path', 'kind', 'description']));

        case 'automated_test_update':
            return tm_http($cfg, 'PUT', '/automated-tests/'.$args['automated_test_id'], tm_pick($args, ['name', 'file_path', 'kind', 'description', 'order_index']));

        case 'automated_test_delete':
            return tm_http($cfg, 'DELETE', '/automated-tests/'.$args['automated_test_id']) + ['ok' => true];

        case 'config_show':
            $shown = $cfg;
            if (! empty($shown['TOKEN'])) {
                $t = $shown['TOKEN'];
                $shown['TOKEN'] = strlen($t) > 8 ? substr($t, 0, 4).'…'.substr($t, -4) : '***';
            }

            return $shown;
    }
    throw new RuntimeException("Unknown tool: $name");
}

// ---------------------------------------------------------------------------
// JSON-RPC / MCP loop
// ---------------------------------------------------------------------------

function mcp_send(array $msg): void
{
    fwrite(STDOUT, json_encode($msg, JSON_UNESCAPED_SLASHES)."\n");
    fflush(STDOUT);
}

function mcp_result($id, array $result): void
{
    mcp_send(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
}

function mcp_error($id, int $code, string $message): void
{
    mcp_send(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
}

function mcp_text_result($id, $payload, bool $isError = false): void
{
    $text = is_string($payload) ? $payload : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    mcp_result($id, [
        'content' => [['type' => 'text', 'text' => $text]],
        'isError' => $isError,
    ]);
}

function mcp_handle(array $msg): void
{
    $id = $msg['id'] ?? null;
    $method = $msg['method'] ?? '';
    $params = $msg['params'] ?? [];

    // Notifications have no id; do not respond.
    $isNotification = ! array_key_exists('id', $msg);

    try {
        switch ($method) {
            case 'initialize':
                mcp_result($id, [
                    'protocolVersion' => PROTOCOL_VERSION,
                    'capabilities' => ['tools' => new stdClass],
                    'serverInfo' => ['name' => SERVER_NAME, 'version' => SERVER_VERSION],
                ]);

                return;

            case 'notifications/initialized':
            case 'notifications/cancelled':
                return;

            case 'ping':
                mcp_result($id, new stdClass);

                return;

            case 'tools/list':
                mcp_result($id, ['tools' => tm_tools()]);

                return;

            case 'tools/call':
                $name = $params['name'] ?? '';
                $args = $params['arguments'] ?? [];
                try {
                    $res = tm_call_tool($name, is_array($args) ? $args : []);
                    mcp_text_result($id, $res);
                } catch (Throwable $e) {
                    mcp_text_result($id, 'Error: '.$e->getMessage(), true);
                }

                return;

            default:
                if ($isNotification) {
                    return;
                }
                mcp_error($id, -32601, "Method not found: $method");
        }
    } catch (Throwable $e) {
        if (! $isNotification) {
            mcp_error($id, -32603, $e->getMessage());
        }
    }
}

// Main loop — read newline-delimited JSON-RPC from stdin.
while (! feof(STDIN)) {
    $line = fgets(STDIN);
    if ($line === false) {
        break;
    }
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    $msg = json_decode($line, true);
    if (! is_array($msg)) {
        continue;
    }
    mcp_handle($msg);
}
