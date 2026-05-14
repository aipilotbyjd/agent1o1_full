<?php

namespace App\Engine\Nodes\Apps\Linear;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use Illuminate\Support\Facades\Http;

/**
 * Linear node — uses the Linear GraphQL API.
 *
 * Credentials required:
 *   api_key / access_token: Linear personal API key or OAuth token
 */
class LinearNode extends AppNode
{
    private const GRAPHQL_URL = 'https://api.linear.app/graphql';

    protected function errorCode(): string
    {
        return 'LINEAR_ERROR';
    }

    protected function operations(): array
    {
        return [
            'create_issue' => $this->createIssue(...),
            'update_issue' => $this->updateIssue(...),
            'list_issues' => $this->listIssues(...),
            'get_issue' => $this->getIssue(...),
            'list_teams' => $this->listTeams(...),
            'create_comment' => $this->createComment(...),
        ];
    }

    private function query(NodeInput $payload, string $query, array $variables = []): array
    {
        $apiKey = (string) ($payload->credentials['api_key'] ?? $payload->credentials['access_token'] ?? '');

        $response = Http::withHeader('Authorization', $apiKey)
            ->post(self::GRAPHQL_URL, ['query' => $query, 'variables' => $variables]);

        $response->throw();

        $data = $response->json();
        if (isset($data['errors'])) {
            throw new \RuntimeException('GraphQL error: '.json_encode($data['errors']));
        }

        return $data['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function createIssue(NodeInput $payload): array
    {
        $mutation = '
            mutation CreateIssue($input: IssueCreateInput!) {
                issueCreate(input: $input) {
                    success
                    issue { id identifier title url state { name } }
                }
            }
        ';

        $input = array_filter([
            'teamId' => $payload->inputData['team_id'] ?? $payload->config['team_id'] ?? null,
            'title' => $payload->inputData['title'] ?? $payload->config['title'] ?? '',
            'description' => $payload->inputData['description'] ?? $payload->config['description'] ?? null,
            'priority' => isset($payload->config['priority']) ? (int) $payload->config['priority'] : null,
            'assigneeId' => $payload->config['assignee_id'] ?? null,
        ]);

        $data = $this->query($payload, $mutation, ['input' => $input]);

        return $data['issueCreate'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateIssue(NodeInput $payload): array
    {
        $mutation = '
            mutation UpdateIssue($id: String!, $input: IssueUpdateInput!) {
                issueUpdate(id: $id, input: $input) {
                    success
                    issue { id identifier title url state { name } }
                }
            }
        ';

        $issueId = (string) ($payload->inputData['issue_id'] ?? $payload->config['issue_id'] ?? '');
        $input = array_filter([
            'title' => $payload->inputData['title'] ?? $payload->config['title'] ?? null,
            'description' => $payload->inputData['description'] ?? $payload->config['description'] ?? null,
            'stateId' => $payload->config['state_id'] ?? null,
        ]);

        $data = $this->query($payload, $mutation, ['id' => $issueId, 'input' => $input]);

        return $data['issueUpdate'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function listIssues(NodeInput $payload): array
    {
        $query = '
            query ListIssues($filter: IssueFilter, $first: Int) {
                issues(filter: $filter, first: $first) {
                    nodes { id identifier title url priority state { name } createdAt }
                }
            }
        ';

        $data = $this->query($payload, $query, [
            'first' => $payload->config['limit'] ?? 25,
            'filter' => $payload->config['filter'] ?? null,
        ]);

        return ['issues' => $data['issues']['nodes'] ?? []];
    }

    /**
     * @return array<string, mixed>
     */
    private function getIssue(NodeInput $payload): array
    {
        $query = '
            query GetIssue($id: String!) {
                issue(id: $id) { id identifier title description url priority state { name } assignee { name } }
            }
        ';

        $issueId = (string) ($payload->inputData['issue_id'] ?? $payload->config['issue_id'] ?? '');
        $data = $this->query($payload, $query, ['id' => $issueId]);

        return $data['issue'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function listTeams(NodeInput $payload): array
    {
        $query = '
            query ListTeams {
                teams { nodes { id name key } }
            }
        ';

        $data = $this->query($payload, $query);

        return ['teams' => $data['teams']['nodes'] ?? []];
    }

    /**
     * @return array<string, mixed>
     */
    private function createComment(NodeInput $payload): array
    {
        $mutation = '
            mutation CreateComment($input: CommentCreateInput!) {
                commentCreate(input: $input) {
                    success
                    comment { id body createdAt }
                }
            }
        ';

        $input = [
            'issueId' => $payload->inputData['issue_id'] ?? $payload->config['issue_id'] ?? '',
            'body' => $payload->inputData['body'] ?? $payload->inputData['comment'] ?? $payload->config['body'] ?? '',
        ];

        $data = $this->query($payload, $mutation, ['input' => $input]);

        return $data['commentCreate'] ?? [];
    }
}
