<?php

namespace App\Engine\Nodes\Apps\Gitlab;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;

class GitlabNode extends AppNode
{
    private const BASE_URL = 'https://gitlab.com/api/v4';

    protected function errorCode(): string
    {
        return 'GITLAB_ERROR';
    }

    protected function operations(): array
    {
        return [
            'create_issue' => $this->createIssue(...),
            'list_issues' => $this->listIssues(...),
            'update_issue' => $this->updateIssue(...),
            'create_merge_request' => $this->createMergeRequest(...),
            'list_projects' => $this->listProjects(...),
            'get_project' => $this->getProject(...),
            'list_pipelines' => $this->listPipelines(...),
            'trigger_pipeline' => $this->triggerPipeline(...),
        ];
    }

    private function client(NodeInput $payload): \Illuminate\Http\Client\PendingRequest
    {
        $token = (string) ($payload->credentials['access_token'] ?? $payload->credentials['private_token'] ?? '');
        $baseUrl = rtrim((string) ($payload->credentials['base_url'] ?? self::BASE_URL), '/');

        return \Illuminate\Support\Facades\Http::baseUrl($baseUrl)
            ->withHeader('PRIVATE-TOKEN', $token);
    }

    private function projectId(NodeInput $payload): string
    {
        return (string) ($payload->inputData['project_id'] ?? $payload->config['project_id'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function createIssue(NodeInput $payload): array
    {
        $project = urlencode($this->projectId($payload));

        $response = $this->client($payload)->post("/projects/{$project}/issues", array_filter([
            'title' => $payload->inputData['title'] ?? $payload->config['title'] ?? '',
            'description' => $payload->inputData['description'] ?? $payload->config['description'] ?? null,
            'labels' => $payload->config['labels'] ?? null,
            'assignee_ids' => $payload->config['assignee_ids'] ?? null,
            'milestone_id' => $payload->config['milestone_id'] ?? null,
        ]));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function listIssues(NodeInput $payload): array
    {
        $project = urlencode($this->projectId($payload));

        $response = $this->client($payload)->get("/projects/{$project}/issues", array_filter([
            'state' => $payload->config['state'] ?? 'opened',
            'per_page' => $payload->config['limit'] ?? 20,
        ]));

        $response->throw();

        return ['issues' => $response->json()];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateIssue(NodeInput $payload): array
    {
        $project = urlencode($this->projectId($payload));
        $issueIid = (string) ($payload->inputData['issue_iid'] ?? $payload->config['issue_iid'] ?? '');

        $response = $this->client($payload)->put("/projects/{$project}/issues/{$issueIid}", array_filter([
            'title' => $payload->inputData['title'] ?? $payload->config['title'] ?? null,
            'description' => $payload->inputData['description'] ?? $payload->config['description'] ?? null,
            'state_event' => $payload->config['state_event'] ?? null,
        ]));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function createMergeRequest(NodeInput $payload): array
    {
        $project = urlencode($this->projectId($payload));

        $response = $this->client($payload)->post("/projects/{$project}/merge_requests", array_filter([
            'source_branch' => $payload->inputData['source_branch'] ?? $payload->config['source_branch'] ?? '',
            'target_branch' => $payload->inputData['target_branch'] ?? $payload->config['target_branch'] ?? 'main',
            'title' => $payload->inputData['title'] ?? $payload->config['title'] ?? '',
            'description' => $payload->inputData['description'] ?? $payload->config['description'] ?? null,
            'remove_source_branch' => $payload->config['remove_source_branch'] ?? false,
        ]));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function listProjects(NodeInput $payload): array
    {
        $response = $this->client($payload)->get('/projects', array_filter([
            'membership' => true,
            'per_page' => $payload->config['limit'] ?? 20,
            'search' => $payload->config['search'] ?? null,
        ]));

        $response->throw();

        return ['projects' => $response->json()];
    }

    /**
     * @return array<string, mixed>
     */
    private function getProject(NodeInput $payload): array
    {
        $project = urlencode($this->projectId($payload));
        $response = $this->client($payload)->get("/projects/{$project}");
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function listPipelines(NodeInput $payload): array
    {
        $project = urlencode($this->projectId($payload));

        $response = $this->client($payload)->get("/projects/{$project}/pipelines", array_filter([
            'status' => $payload->config['status'] ?? null,
            'per_page' => $payload->config['limit'] ?? 20,
        ]));

        $response->throw();

        return ['pipelines' => $response->json()];
    }

    /**
     * @return array<string, mixed>
     */
    private function triggerPipeline(NodeInput $payload): array
    {
        $project = urlencode($this->projectId($payload));
        $ref = (string) ($payload->inputData['ref'] ?? $payload->config['ref'] ?? 'main');
        $token = (string) ($payload->credentials['trigger_token'] ?? $payload->credentials['access_token'] ?? '');

        $response = \Illuminate\Support\Facades\Http::post(
            rtrim((string) ($payload->credentials['base_url'] ?? self::BASE_URL), '/')."/projects/{$project}/trigger/pipeline",
            array_filter([
                'token' => $token,
                'ref' => $ref,
                'variables' => $payload->config['variables'] ?? null,
            ]),
        );

        $response->throw();

        return $response->json();
    }
}
