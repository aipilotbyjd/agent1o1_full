<?php

namespace App\Engine\Nodes\Apps\Dropbox;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Facades\Http;

/**
 * Dropbox node — uses the Dropbox API v2.
 *
 * Credentials:
 *   access_token — Dropbox OAuth2 access token (long-lived or refreshable)
 */
class DropboxNode extends AppNode
{
    private const API_URL = 'https://api.dropboxapi.com/2';
    private const CONTENT_URL = 'https://content.dropboxapi.com/2';

    protected function errorCode(): string
    {
        return 'DROPBOX_ERROR';
    }

    protected function operations(): array
    {
        return [
            'list_folder' => $this->listFolder(...),
            'upload' => $this->upload(...),
            'download' => $this->download(...),
            'delete' => $this->delete(...),
            'create_folder' => $this->createFolder(...),
            'move' => $this->move(...),
            'copy' => $this->copy(...),
            'get_metadata' => $this->getMetadata(...),
            'search' => $this->search(...),
            'get_shared_link' => $this->getSharedLink(...),
        ];
    }

    private function token(NodePayload $payload): string
    {
        return (string) ($payload->credentials['access_token'] ?? '');
    }

    private function api(NodePayload $payload): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->token($payload))->baseUrl(self::API_URL);
    }

    /**
     * @return array<string, mixed>
     */
    private function listFolder(NodePayload $payload): array
    {
        $path = (string) ($payload->inputData['path'] ?? $payload->config['path'] ?? '');
        $recursive = (bool) ($payload->config['recursive'] ?? false);

        $response = $this->api($payload)->post('/files/list_folder', [
            'path' => $path,
            'recursive' => $recursive,
            'limit' => $payload->config['limit'] ?? 100,
        ]);

        $response->throw();

        return [
            'entries' => $response->json('entries', []),
            'has_more' => $response->json('has_more', false),
            'cursor' => $response->json('cursor'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function upload(NodePayload $payload): array
    {
        $path = (string) ($payload->inputData['path'] ?? $payload->config['path'] ?? '');
        $content = (string) ($payload->inputData['content'] ?? $payload->config['content'] ?? '');
        $mode = (string) ($payload->config['mode'] ?? 'add');
        $autorename = (bool) ($payload->config['autorename'] ?? true);

        $apiArg = json_encode([
            'path' => $path,
            'mode' => $mode,
            'autorename' => $autorename,
            'mute' => false,
        ]);

        $response = Http::withToken($this->token($payload))
            ->withHeader('Dropbox-API-Arg', $apiArg)
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withBody($content, 'application/octet-stream')
            ->post(self::CONTENT_URL.'/files/upload');

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function download(NodePayload $payload): array
    {
        $path = (string) ($payload->inputData['path'] ?? $payload->config['path'] ?? '');
        $apiArg = json_encode(['path' => $path]);

        $response = Http::withToken($this->token($payload))
            ->withHeader('Dropbox-API-Arg', $apiArg)
            ->post(self::CONTENT_URL.'/files/download');

        $response->throw();

        $metadata = json_decode($response->header('Dropbox-API-Result') ?? '{}', true) ?? [];

        return [
            'content' => $response->body(),
            'metadata' => $metadata,
            'size' => strlen($response->body()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function delete(NodePayload $payload): array
    {
        $path = (string) ($payload->inputData['path'] ?? $payload->config['path'] ?? '');

        $response = $this->api($payload)->post('/files/delete_v2', ['path' => $path]);
        $response->throw();

        return ['deleted' => true, 'metadata' => $response->json('metadata', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function createFolder(NodePayload $payload): array
    {
        $path = (string) ($payload->inputData['path'] ?? $payload->config['path'] ?? '');
        $autorename = (bool) ($payload->config['autorename'] ?? false);

        $response = $this->api($payload)->post('/files/create_folder_v2', ['path' => $path, 'autorename' => $autorename]);
        $response->throw();

        return $response->json('metadata', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function move(NodePayload $payload): array
    {
        $from = (string) ($payload->inputData['from'] ?? $payload->config['from'] ?? '');
        $to = (string) ($payload->inputData['to'] ?? $payload->config['to'] ?? '');

        $response = $this->api($payload)->post('/files/move_v2', ['from_path' => $from, 'to_path' => $to]);
        $response->throw();

        return $response->json('metadata', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function copy(NodePayload $payload): array
    {
        $from = (string) ($payload->inputData['from'] ?? $payload->config['from'] ?? '');
        $to = (string) ($payload->inputData['to'] ?? $payload->config['to'] ?? '');

        $response = $this->api($payload)->post('/files/copy_v2', ['from_path' => $from, 'to_path' => $to]);
        $response->throw();

        return $response->json('metadata', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function getMetadata(NodePayload $payload): array
    {
        $path = (string) ($payload->inputData['path'] ?? $payload->config['path'] ?? '');

        $response = $this->api($payload)->post('/files/get_metadata', ['path' => $path]);
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function search(NodePayload $payload): array
    {
        $query = (string) ($payload->inputData['query'] ?? $payload->config['query'] ?? '');
        $path = (string) ($payload->config['path'] ?? '');

        $response = $this->api($payload)->post('/files/search_v2', array_filter([
            'query' => $query,
            'options' => array_filter([
                'path' => $path ?: null,
                'max_results' => $payload->config['limit'] ?? 20,
            ]),
        ]));

        $response->throw();

        return [
            'matches' => $response->json('matches', []),
            'has_more' => $response->json('has_more', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSharedLink(NodePayload $payload): array
    {
        $path = (string) ($payload->inputData['path'] ?? $payload->config['path'] ?? '');

        $response = $this->api($payload)->post('/sharing/create_shared_link_with_settings', array_filter([
            'path' => $path,
            'settings' => $payload->config['settings'] ?? null,
        ]));

        if ($response->status() === 409 && str_contains($response->body(), 'shared_link_already_exists')) {
            $listResponse = $this->api($payload)->post('/sharing/list_shared_links', ['path' => $path, 'direct_only' => true]);
            $listResponse->throw();
            $links = $listResponse->json('links', []);

            return ['url' => $links[0]['url'] ?? null, 'metadata' => $links[0] ?? []];
        }

        $response->throw();

        return ['url' => $response->json('url'), 'metadata' => $response->json()];
    }
}
