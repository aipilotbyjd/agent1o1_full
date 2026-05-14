<?php

namespace App\Engine\Nodes\Apps\Mongodb;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use Illuminate\Support\Facades\Http;

/**
 * MongoDB node — uses the MongoDB Atlas Data API (HTTP).
 * Works without the MongoDB PHP extension.
 *
 * Credentials:
 *   api_key        — Atlas Data API key
 *   base_url       — e.g. https://data.mongodb-api.com/app/{appId}/endpoint/data/v1
 *   data_source    — Atlas cluster name (e.g. "Cluster0")
 *   database       — default database name
 */
class MongodbNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'MONGODB_ERROR';
    }

    protected function operations(): array
    {
        return [
            'find' => $this->find(...),
            'find_one' => $this->findOne(...),
            'insert_one' => $this->insertOne(...),
            'insert_many' => $this->insertMany(...),
            'update_one' => $this->updateOne(...),
            'update_many' => $this->updateMany(...),
            'delete_one' => $this->deleteOne(...),
            'aggregate' => $this->aggregate(...),
        ];
    }

    private function client(NodeInput $payload): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = (string) ($payload->credentials['api_key'] ?? $payload->credentials['access_token'] ?? '');
        $baseUrl = rtrim((string) ($payload->credentials['base_url'] ?? 'https://data.mongodb-api.com'), '/');

        return Http::withHeader('api-key', $apiKey)
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->contentType('application/json');
    }

    private function context(NodeInput $payload): array
    {
        return [
            'dataSource' => (string) ($payload->credentials['data_source'] ?? 'Cluster0'),
            'database' => (string) ($payload->inputData['database'] ?? $payload->config['database'] ?? $payload->credentials['database'] ?? ''),
            'collection' => (string) ($payload->inputData['collection'] ?? $payload->config['collection'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function find(NodeInput $payload): array
    {
        $response = $this->client($payload)->post('/action/find', array_merge($this->context($payload), array_filter([
            'filter' => $payload->inputData['filter'] ?? $payload->config['filter'] ?? [],
            'projection' => $payload->config['projection'] ?? null,
            'sort' => $payload->config['sort'] ?? null,
            'limit' => $payload->config['limit'] ?? 20,
            'skip' => $payload->config['skip'] ?? null,
        ])));

        $response->throw();

        return ['documents' => $response->json('documents', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOne(NodeInput $payload): array
    {
        $response = $this->client($payload)->post('/action/findOne', array_merge($this->context($payload), array_filter([
            'filter' => $payload->inputData['filter'] ?? $payload->config['filter'] ?? [],
            'projection' => $payload->config['projection'] ?? null,
        ])));

        $response->throw();

        return ['document' => $response->json('document')];
    }

    /**
     * @return array<string, mixed>
     */
    private function insertOne(NodeInput $payload): array
    {
        $document = $payload->inputData['document'] ?? $payload->config['document'] ?? [];

        $response = $this->client($payload)->post('/action/insertOne', array_merge($this->context($payload), ['document' => $document]));
        $response->throw();

        return ['insertedId' => $response->json('insertedId')];
    }

    /**
     * @return array<string, mixed>
     */
    private function insertMany(NodeInput $payload): array
    {
        $documents = $payload->inputData['documents'] ?? $payload->config['documents'] ?? [];

        $response = $this->client($payload)->post('/action/insertMany', array_merge($this->context($payload), ['documents' => $documents]));
        $response->throw();

        return ['insertedIds' => $response->json('insertedIds', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateOne(NodeInput $payload): array
    {
        $response = $this->client($payload)->post('/action/updateOne', array_merge($this->context($payload), array_filter([
            'filter' => $payload->inputData['filter'] ?? $payload->config['filter'] ?? [],
            'update' => $payload->inputData['update'] ?? $payload->config['update'] ?? [],
            'upsert' => $payload->config['upsert'] ?? false,
        ])));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function updateMany(NodeInput $payload): array
    {
        $response = $this->client($payload)->post('/action/updateMany', array_merge($this->context($payload), [
            'filter' => $payload->inputData['filter'] ?? $payload->config['filter'] ?? [],
            'update' => $payload->inputData['update'] ?? $payload->config['update'] ?? [],
        ]));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteOne(NodeInput $payload): array
    {
        $response = $this->client($payload)->post('/action/deleteOne', array_merge($this->context($payload), [
            'filter' => $payload->inputData['filter'] ?? $payload->config['filter'] ?? [],
        ]));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregate(NodeInput $payload): array
    {
        $response = $this->client($payload)->post('/action/aggregate', array_merge($this->context($payload), [
            'pipeline' => $payload->inputData['pipeline'] ?? $payload->config['pipeline'] ?? [],
        ]));

        $response->throw();

        return ['documents' => $response->json('documents', [])];
    }
}
