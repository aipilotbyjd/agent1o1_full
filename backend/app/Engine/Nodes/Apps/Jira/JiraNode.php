<?php

namespace App\Engine\Nodes\Apps\Jira;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Facades\Http;

class JiraNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'JIRA_ERROR';
    }

    protected function operations(): array
    {
        return [
            'create_issue' => $this->createIssue(...),
            'update_issue' => $this->updateIssue(...),
            'get_issue' => $this->getIssue(...),
            'search_issues' => $this->searchIssues(...),
            'add_comment' => $this->addComment(...),
            'transition_issue' => $this->transitionIssue(...),
            'list_projects' => $this->listProjects(...),
        ];
    }

    private function client(NodePayload $payload): \Illuminate\Http\Client\PendingRequest
    {
        $domain = rtrim((string) ($payload->credentials['domain'] ?? ''), '/');
        $email = (string) ($payload->credentials['email'] ?? '');
        $apiToken = (string) ($payload->credentials['api_token'] ?? $payload->credentials['access_token'] ?? '');

        return Http::baseUrl("{$domain}/rest/api/3")
            ->withBasicAuth($email, $apiToken)
            ->acceptJson()
            ->contentType('application/json');
    }

    /**
     * @return array<string, mixed>
     */
    private function createIssue(NodePayload $payload): array
    {
        $projectKey = (string) ($payload->inputData['project_key'] ?? $payload->config['project_key'] ?? '');
        $issueType = (string) ($payload->config['issue_type'] ?? 'Task');
        $summary = (string) ($payload->inputData['summary'] ?? $payload->config['summary'] ?? '');
        $description = (string) ($payload->inputData['description'] ?? $payload->config['description'] ?? '');

        $body = [
            'fields' => array_filter([
                'project' => ['key' => $projectKey],
                'issuetype' => ['name' => $issueType],
                'summary' => $summary,
                'description' => $description ? [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $description]]]],
                ] : null,
                'priority' => $payload->config['priority'] ? ['name' => $payload->config['priority']] : null,
            ]),
        ];

        $response = $this->client($payload)->post('/issue', $body);
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function updateIssue(NodePayload $payload): array
    {
        $issueKey = (string) ($payload->inputData['issue_key'] ?? $payload->config['issue_key'] ?? '');

        $fields = array_filter([
            'summary' => $payload->inputData['summary'] ?? $payload->config['summary'] ?? null,
            'priority' => isset($payload->config['priority']) ? ['name' => $payload->config['priority']] : null,
        ]);

        $response = $this->client($payload)->put("/issue/{$issueKey}", ['fields' => $fields]);
        $response->throw();

        return ['updated' => true, 'issue_key' => $issueKey];
    }

    /**
     * @return array<string, mixed>
     */
    private function getIssue(NodePayload $payload): array
    {
        $issueKey = (string) ($payload->inputData['issue_key'] ?? $payload->config['issue_key'] ?? '');

        $response = $this->client($payload)->get("/issue/{$issueKey}");
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function searchIssues(NodePayload $payload): array
    {
        $jql = (string) ($payload->inputData['jql'] ?? $payload->config['jql'] ?? '');
        $maxResults = (int) ($payload->config['limit'] ?? 50);

        $response = $this->client($payload)->post('/search', [
            'jql' => $jql,
            'maxResults' => $maxResults,
            'fields' => $payload->config['fields'] ?? ['summary', 'status', 'assignee', 'priority', 'created'],
        ]);

        $response->throw();

        return [
            'issues' => $response->json('issues', []),
            'total' => $response->json('total', 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addComment(NodePayload $payload): array
    {
        $issueKey = (string) ($payload->inputData['issue_key'] ?? $payload->config['issue_key'] ?? '');
        $body = (string) ($payload->inputData['body'] ?? $payload->inputData['comment'] ?? $payload->config['body'] ?? '');

        $response = $this->client($payload)->post("/issue/{$issueKey}/comment", [
            'body' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $body]]]],
            ],
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionIssue(NodePayload $payload): array
    {
        $issueKey = (string) ($payload->inputData['issue_key'] ?? $payload->config['issue_key'] ?? '');
        $transitionId = (string) ($payload->inputData['transition_id'] ?? $payload->config['transition_id'] ?? '');

        $response = $this->client($payload)->post("/issue/{$issueKey}/transitions", [
            'transition' => ['id' => $transitionId],
        ]);

        $response->throw();

        return ['transitioned' => true, 'issue_key' => $issueKey];
    }

    /**
     * @return array<string, mixed>
     */
    private function listProjects(NodePayload $payload): array
    {
        $response = $this->client($payload)->get('/project/search', [
            'maxResults' => $payload->config['limit'] ?? 50,
        ]);

        $response->throw();

        return ['projects' => $response->json('values', [])];
    }
}
