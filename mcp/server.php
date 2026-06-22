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
 *   - queue            — list current epic queue (tasks + features, sorted)
 *   - task_get         — get a task
 *   - task_start       — mark doing + set ACTIVE_TASK_ID in .task-manager
 *   - task_status      — set task status
 *   - task_note        — add note to task history
 *   - task_complete    — mark done + add summary note + clear ACTIVE_TASK_ID
 *   - task_block       — mark blocked + add note with reason
 *   - task_skip        — clear ACTIVE_TASK_ID without changing status
 *   - feature_get      — get a feature
 *   - feature_start    — mark active + set ACTIVE_FEATURE_ID
 *   - feature_status   — set feature status
 *   - feature_note     — add note to feature history
 *   - epic_list        — list all epics with full IDs
 *   - epic_note        — add note to epic history
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
        ['name' => 'queue', 'description' => 'Fetch the current epic queue (sorted tasks + features).',
            'inputSchema' => ['type' => 'object', 'properties' => ['cwd' => $cwdProp]]],

        ['name' => 'task_get', 'description' => 'Get a task by id.',
            'inputSchema' => ['type' => 'object', 'properties' => ['task_id' => $idProp, 'cwd' => $cwdProp]]],

        ['name' => 'task_start', 'description' => 'Start a task: PATCH status=doing and write ACTIVE_TASK_ID into .task-manager.',
            'inputSchema' => ['type' => 'object', 'required' => ['task_id'],
                'properties' => ['task_id' => $idProp, 'cwd' => $cwdProp]]],

        ['name' => 'task_status', 'description' => 'Set task status (todo|doing|blocked|building_automated_tests|running_automated_tests|done|merged_to_staging|deployed_to_staging|merged_to_master|deployed_to_master).',
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

        ['name' => 'epic_list', 'description' => 'List all epics with their full IDs, names, and statuses.',
            'inputSchema' => ['type' => 'object', 'properties' => ['cwd' => $cwdProp]]],

        ['name' => 'epic_note', 'description' => 'Add a note to the epic history.',
            'inputSchema' => ['type' => 'object', 'required' => ['body'],
                'properties' => ['body' => ['type' => 'string'], 'cwd' => $cwdProp]]],

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
        case 'queue':
            $epic = tm_require($cfg, 'EPIC_ID', null, 'EPIC_ID');

            return tm_http($cfg, 'GET', "/epics/$epic/queue");

        case 'task_get':
            $id = tm_require($cfg, 'ACTIVE_TASK_ID', $args['task_id'] ?? null, 'task_id');

            return tm_http($cfg, 'GET', "/tasks/$id");

        case 'task_start':
            $id = $args['task_id'];
            $res = tm_http($cfg, 'PATCH', "/tasks/$id/status", ['status' => 'doing']);
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

        case 'epic_list':
            return tm_http($cfg, 'GET', '/epics');

        case 'epic_note':
            $epic = tm_require($cfg, 'EPIC_ID', null, 'EPIC_ID');

            return tm_http($cfg, 'POST', "/epics/$epic/history", ['action' => 'note', 'body' => $args['body']]);

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
