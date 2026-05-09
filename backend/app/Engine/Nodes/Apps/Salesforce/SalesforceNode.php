<?php

namespace App\Engine\Nodes\Apps\Salesforce;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Facades\Http;

/**
 * Salesforce node — uses the Salesforce REST API.
 *
 * Credentials:
 *   access_token: OAuth2 access token
 *   instance_url: e.g. https://yourorg.salesforce.com
 *   api_version:  API version, default "v59.0"
 */
class SalesforceNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'SALESFORCE_ERROR';
    }

    protected function operations(): array
    {
        return [
            'create_record' => $this->createRecord(...),
            'update_record' => $this->updateRecord(...),
            'get_record' => $this->getRecord(...),
            'delete_record' => $this->deleteRecord(...),
            'soql_query' => $this->soqlQuery(...),
            'list_objects' => $this->listObjects(...),
        ];
    }

    private function client(NodePayload $payload): \Illuminate\Http\Client\PendingRequest
    {
        $token = (string) ($payload->credentials['access_token'] ?? '');
        $instanceUrl = rtrim((string) ($payload->credentials['instance_url'] ?? ''), '/');
        $apiVersion = (string) ($payload->credentials['api_version'] ?? 'v59.0');

        return Http::withToken($token)
            ->baseUrl("{$instanceUrl}/services/data/{$apiVersion}");
    }

    /**
     * @return array<string, mixed>
     */
    private function createRecord(NodePayload $payload): array
    {
        $object = (string) ($payload->inputData['object'] ?? $payload->config['object'] ?? 'Contact');
        $fields = $payload->inputData['fields'] ?? $payload->config['fields'] ?? [];

        $response = $this->client($payload)->post("/sobjects/{$object}", $fields);
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRecord(NodePayload $payload): array
    {
        $object = (string) ($payload->inputData['object'] ?? $payload->config['object'] ?? 'Contact');
        $recordId = (string) ($payload->inputData['record_id'] ?? $payload->config['record_id'] ?? '');
        $fields = $payload->inputData['fields'] ?? $payload->config['fields'] ?? [];

        $response = $this->client($payload)->patch("/sobjects/{$object}/{$recordId}", $fields);
        $response->throw();

        return ['updated' => true, 'id' => $recordId];
    }

    /**
     * @return array<string, mixed>
     */
    private function getRecord(NodePayload $payload): array
    {
        $object = (string) ($payload->inputData['object'] ?? $payload->config['object'] ?? 'Contact');
        $recordId = (string) ($payload->inputData['record_id'] ?? $payload->config['record_id'] ?? '');
        $fields = implode(',', (array) ($payload->config['fields'] ?? []));

        $response = $this->client($payload)->get("/sobjects/{$object}/{$recordId}", $fields ? ['fields' => $fields] : []);
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteRecord(NodePayload $payload): array
    {
        $object = (string) ($payload->inputData['object'] ?? $payload->config['object'] ?? 'Contact');
        $recordId = (string) ($payload->inputData['record_id'] ?? $payload->config['record_id'] ?? '');

        $response = $this->client($payload)->delete("/sobjects/{$object}/{$recordId}");
        $response->throw();

        return ['deleted' => true, 'id' => $recordId];
    }

    /**
     * @return array<string, mixed>
     */
    private function soqlQuery(NodePayload $payload): array
    {
        $soql = (string) ($payload->inputData['query'] ?? $payload->config['query'] ?? '');

        $response = $this->client($payload)->get('/query', ['q' => $soql]);
        $response->throw();

        return [
            'records' => $response->json('records', []),
            'totalSize' => $response->json('totalSize', 0),
            'done' => $response->json('done', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listObjects(NodePayload $payload): array
    {
        $response = $this->client($payload)->get('/sobjects');
        $response->throw();

        $sobjects = collect($response->json('sobjects', []))
            ->map(fn ($o) => ['name' => $o['name'], 'label' => $o['label'], 'queryable' => $o['queryable']])
            ->values()
            ->all();

        return ['objects' => $sobjects];
    }
}
