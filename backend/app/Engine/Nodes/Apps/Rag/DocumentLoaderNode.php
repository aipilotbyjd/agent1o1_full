<?php

namespace App\Engine\Nodes\Apps\Rag;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Document Loader Node
 * 
 * Loads documents from various sources for RAG processing:
 * - Plain text
 * - URLs (web pages)
 * - File paths
 */
class DocumentLoaderNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'DOCUMENT_LOADER_ERROR';
    }

    protected function operations(): array
    {
        return [
            'load_text' => $this->loadText(...),
            'load_url' => $this->loadUrl(...),
            'load_file' => $this->loadFile(...),
        ];
    }

    /**
     * Load plain text content
     */
    private function loadText(NodePayload $payload): array
    {
        $text = $payload->config['text'] ?? '';
        $documentId = $payload->config['document_id'] ?? Str::uuid()->toString();
        $metadata = $payload->config['metadata'] ?? [];

        if (empty($text)) {
            throw new \InvalidArgumentException('Text content is required');
        }

        return [
            'documents' => [
                [
                    'id' => $documentId,
                    'content' => $text,
                    'metadata' => array_merge($metadata, [
                        'source' => 'text',
                        'length' => strlen($text),
                    ]),
                ],
            ],
        ];
    }

    /**
     * Load content from a URL
     */
    private function loadUrl(NodePayload $payload): array
    {
        $url = $payload->config['url'] ?? '';
        $documentId = $payload->config['document_id'] ?? Str::uuid()->toString();
        $metadata = $payload->config['metadata'] ?? [];

        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Valid URL is required');
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException("Failed to fetch URL: {$response->status()}");
            }

            $content = $response->body();

            // Basic HTML stripping (in production, use a proper HTML parser)
            $content = strip_tags($content);
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);

            return [
                'documents' => [
                    [
                        'id' => $documentId,
                        'content' => $content,
                        'metadata' => array_merge($metadata, [
                            'source' => 'url',
                            'url' => $url,
                            'length' => strlen($content),
                            'fetched_at' => now()->toIso8601String(),
                        ]),
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error loading URL: {$e->getMessage()}");
        }
    }

    /**
     * Load content from a file path
     */
    private function loadFile(NodePayload $payload): array
    {
        $filePath = $payload->config['file_path'] ?? '';
        $documentId = $payload->config['document_id'] ?? Str::uuid()->toString();
        $metadata = $payload->config['metadata'] ?? [];

        if (empty($filePath)) {
            throw new \InvalidArgumentException('File path is required');
        }

        // For security, only allow reading from storage path
        $storagePath = storage_path('app/'.$filePath);

        if (! file_exists($storagePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        if (! is_readable($storagePath)) {
            throw new \InvalidArgumentException("File not readable: {$filePath}");
        }

        $content = file_get_contents($storagePath);

        if ($content === false) {
            throw new \RuntimeException("Error reading file: {$filePath}");
        }

        return [
            'documents' => [
                [
                    'id' => $documentId,
                    'content' => $content,
                    'metadata' => array_merge($metadata, [
                        'source' => 'file',
                        'file_path' => $filePath,
                        'length' => strlen($content),
                        'loaded_at' => now()->toIso8601String(),
                    ]),
                ],
            ],
        ];
    }
}
