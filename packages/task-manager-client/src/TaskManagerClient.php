<?php

namespace TaskManager\Client;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TaskManagerClient
{
    private PendingRequest $http;

    public function __construct(string $baseUrl, string $token)
    {
        $this->http = Http::baseUrl(rtrim($baseUrl, '/').'/api/v1')
            ->withToken($token)
            ->acceptJson()
            ->timeout(30);
    }

    // ── Epics ─────────────────────────────────────────────────────────────────

    /** @return array<int, mixed> */
    public function listEpics(?string $repositoryUrl = null): array
    {
        $params = $repositoryUrl ? ['repository_url' => $repositoryUrl] : [];

        return $this->get('epics', $params);
    }

    /** @return array<string, mixed> */
    public function getEpic(string $epicId): array
    {
        return $this->get("epics/{$epicId}");
    }

    /** @return array<string, mixed> */
    public function createEpic(array $data): array
    {
        return $this->post('epics', $data);
    }

    /** @return array<string, mixed> */
    public function updateEpic(string $epicId, array $data): array
    {
        return $this->put("epics/{$epicId}", $data);
    }

    public function deleteEpic(string $epicId): void
    {
        $this->delete("epics/{$epicId}");
    }

    /** @return array<int, mixed> Ordered by execution_order ascending */
    public function getEpicQueue(string $epicId): array
    {
        return $this->get("epics/{$epicId}/queue");
    }

    /** @return array<int, mixed> */
    public function listFeatures(string $epicId): array
    {
        return $this->get("epics/{$epicId}/features");
    }

    /** @return array<int, mixed> */
    public function getEpicHistory(string $epicId): array
    {
        return $this->get("epics/{$epicId}/history");
    }

    /** @return array<string, mixed> */
    public function addEpicNote(string $epicId, array $metadata, array $options = []): array
    {
        return $this->post("epics/{$epicId}/history", array_merge(['action' => 'note', 'metadata' => $metadata], $options));
    }

    // ── Features ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function getFeature(string $featureId): array
    {
        return $this->get("features/{$featureId}");
    }

    /** @return array<string, mixed> */
    public function createFeature(array $data): array
    {
        return $this->post('features', $data);
    }

    /** @return array<string, mixed> */
    public function updateFeature(string $featureId, array $data): array
    {
        return $this->put("features/{$featureId}", $data);
    }

    public function deleteFeature(string $featureId): void
    {
        $this->delete("features/{$featureId}");
    }

    /** @return array<int, mixed> */
    public function getFeatureHistory(string $featureId): array
    {
        return $this->get("features/{$featureId}/history");
    }

    /** @return array<string, mixed> */
    public function addFeatureNote(string $featureId, array $metadata, array $options = []): array
    {
        return $this->post("features/{$featureId}/history", array_merge(['action' => 'note', 'metadata' => $metadata], $options));
    }

    // ── Tasks ─────────────────────────────────────────────────────────────────

    /** @return array<int, mixed> */
    public function listTasks(string $featureId): array
    {
        return $this->get("features/{$featureId}/tasks");
    }

    /** @return array<string, mixed> */
    public function getTask(string $taskId): array
    {
        return $this->get("tasks/{$taskId}");
    }

    /** @return array<string, mixed> */
    public function createTask(array $data): array
    {
        return $this->post('tasks', $data);
    }

    /** @return array<string, mixed> */
    public function updateTask(string $taskId, array $data): array
    {
        return $this->put("tasks/{$taskId}", $data);
    }

    public function deleteTask(string $taskId): void
    {
        $this->delete("tasks/{$taskId}");
    }

    /** @return array<string, mixed> */
    public function updateTaskStatus(string $taskId, string $status): array
    {
        return $this->patch("tasks/{$taskId}/status", ['status' => $status]);
    }

    /** @return array<int, mixed> */
    public function getTaskHistory(string $taskId): array
    {
        return $this->get("tasks/{$taskId}/history");
    }

    /** @return array<string, mixed> */
    public function addTaskNote(string $taskId, array $metadata, array $options = []): array
    {
        return $this->post("tasks/{$taskId}/history", array_merge(['action' => 'note', 'metadata' => $metadata], $options));
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    /** @return array<string, mixed>|array<int, mixed> */
    private function get(string $path, array $query = []): array
    {
        return $this->unwrap($this->http->get($path, $query));
    }

    /** @return array<string, mixed> */
    private function post(string $path, array $data): array
    {
        return $this->unwrap($this->http->post($path, $data));
    }

    /** @return array<string, mixed> */
    private function put(string $path, array $data): array
    {
        return $this->unwrap($this->http->put($path, $data));
    }

    /** @return array<string, mixed> */
    private function patch(string $path, array $data): array
    {
        return $this->unwrap($this->http->patch($path, $data));
    }

    private function delete(string $path): void
    {
        $response = $this->http->delete($path);

        if ($response->failed()) {
            throw new TaskManagerException("DELETE {$path} failed: {$response->status()}");
        }
    }

    /** @return array<string, mixed>|array<int, mixed> */
    private function unwrap(Response $response): array
    {
        if ($response->failed()) {
            throw new TaskManagerException(
                "Request failed ({$response->status()}): ".($response->json('message') ?? $response->body())
            );
        }

        $body = $response->json();

        return is_array($body['data'] ?? null) ? $body['data'] : $body;
    }
}
