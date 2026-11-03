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

    // ── Test matrices ─────────────────────────────────────────────────────────

    /** @return array<int, mixed> */
    public function listTestMatrices(string $epicId): array
    {
        return $this->get("epics/{$epicId}/test-matrices");
    }

    /** The whole grid: contexts, scenarios with their automated tests, and every cell. @return array<string, mixed> */
    public function getTestMatrix(string $testMatrixId): array
    {
        return $this->get("test-matrices/{$testMatrixId}");
    }

    /** @return array<string, mixed> */
    public function createTestMatrix(array $data): array
    {
        return $this->post('test-matrices', $data);
    }

    /** @return array<string, mixed> */
    public function updateTestMatrix(string $testMatrixId, array $data): array
    {
        return $this->put("test-matrices/{$testMatrixId}", $data);
    }

    public function deleteTestMatrix(string $testMatrixId): void
    {
        $this->delete("test-matrices/{$testMatrixId}");
    }

    /** @return array<int, mixed> */
    public function listTestContexts(string $testMatrixId): array
    {
        return $this->get("test-matrices/{$testMatrixId}/contexts");
    }

    /** @return array<string, mixed> */
    public function getTestContext(string $testContextId): array
    {
        return $this->get("test-contexts/{$testContextId}");
    }

    /** @return array<string, mixed> */
    public function createTestContext(array $data): array
    {
        return $this->post('test-contexts', $data);
    }

    /** @return array<string, mixed> */
    public function updateTestContext(string $testContextId, array $data): array
    {
        return $this->put("test-contexts/{$testContextId}", $data);
    }

    public function deleteTestContext(string $testContextId): void
    {
        $this->delete("test-contexts/{$testContextId}");
    }

    /** @return array<int, mixed> */
    public function listTestScenarios(string $testMatrixId): array
    {
        return $this->get("test-matrices/{$testMatrixId}/scenarios");
    }

    /** @return array<string, mixed> */
    public function getTestScenario(string $testScenarioId): array
    {
        return $this->get("test-scenarios/{$testScenarioId}");
    }

    /** @return array<string, mixed> */
    public function createTestScenario(array $data): array
    {
        return $this->post('test-scenarios', $data);
    }

    /** @return array<string, mixed> */
    public function updateTestScenario(string $testScenarioId, array $data): array
    {
        return $this->put("test-scenarios/{$testScenarioId}", $data);
    }

    /**
     * Makes the scenario relevant in the listed contexts and not relevant in the others.
     *
     * @param  array<int, string>  $testContextIds
     * @return array<string, mixed>
     */
    public function setTestScenarioRelevance(string $testScenarioId, array $testContextIds): array
    {
        return $this->put("test-scenarios/{$testScenarioId}/relevance", ['test_context_ids' => $testContextIds]);
    }

    public function deleteTestScenario(string $testScenarioId): void
    {
        $this->delete("test-scenarios/{$testScenarioId}");
    }

    /** @return array<int, mixed> */
    public function listTestResults(string $testMatrixId): array
    {
        return $this->get("test-matrices/{$testMatrixId}/results");
    }

    /** @return array<string, mixed> */
    public function getTestResult(string $testResultId): array
    {
        return $this->get("test-results/{$testResultId}");
    }

    /** @param  array<string, mixed>  $data  Any of status (untested|passed|failed), relevant and expectation. @return array<string, mixed> */
    public function updateTestResult(string $testResultId, array $data): array
    {
        return $this->patch("test-results/{$testResultId}", $data);
    }

    /** @return array<int, mixed> */
    public function listTestComments(string $testMatrixId): array
    {
        return $this->get("test-matrices/{$testMatrixId}/comments");
    }

    /** @return array<string, mixed> */
    public function getTestComment(string $testCommentId): array
    {
        return $this->get("test-comments/{$testCommentId}");
    }

    /** A comment is a task given to one or more contexts. @return array<string, mixed> */
    public function createTestComment(array $data): array
    {
        return $this->post('test-comments', $data);
    }

    /** @return array<string, mixed> */
    public function updateTestComment(string $testCommentId, array $data): array
    {
        return $this->put("test-comments/{$testCommentId}", $data);
    }

    public function deleteTestComment(string $testCommentId): void
    {
        $this->delete("test-comments/{$testCommentId}");
    }

    /** @return array<int, mixed> */
    public function listAutomatedTests(string $testScenarioId): array
    {
        return $this->get("test-scenarios/{$testScenarioId}/automated-tests");
    }

    /** @return array<string, mixed> */
    public function getAutomatedTest(string $automatedTestId): array
    {
        return $this->get("automated-tests/{$automatedTestId}");
    }

    /** @return array<string, mixed> */
    public function createAutomatedTest(array $data): array
    {
        return $this->post('automated-tests', $data);
    }

    /** @return array<string, mixed> */
    public function updateAutomatedTest(string $automatedTestId, array $data): array
    {
        return $this->put("automated-tests/{$automatedTestId}", $data);
    }

    public function deleteAutomatedTest(string $automatedTestId): void
    {
        $this->delete("automated-tests/{$automatedTestId}");
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
