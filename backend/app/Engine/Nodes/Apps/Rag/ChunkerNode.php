<?php

namespace App\Engine\Nodes\Apps\Rag;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;

/**
 * Text Chunker Node
 * 
 * Splits documents into smaller chunks for embedding and retrieval.
 * Supports multiple chunking strategies:
 * - Fixed size (character or token count)
 * - Semantic (paragraph/sentence boundaries)
 * - Overlapping chunks for context preservation
 */
class ChunkerNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'CHUNKER_ERROR';
    }

    protected function operations(): array
    {
        return [
            'chunk_fixed' => $this->chunkFixed(...),
            'chunk_semantic' => $this->chunkSemantic(...),
        ];
    }

    /**
     * Fixed-size chunking with optional overlap
     */
    private function chunkFixed(NodePayload $payload): array
    {
        $documents = $payload->inputData['documents'] ?? [];
        $chunkSize = (int) ($payload->config['chunk_size'] ?? 1000);
        $overlap = (int) ($payload->config['overlap'] ?? 200);

        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1');
        }

        if ($overlap >= $chunkSize) {
            throw new \InvalidArgumentException('Overlap must be less than chunk size');
        }

        $allChunks = [];

        foreach ($documents as $document) {
            $content = $document['content'] ?? '';
            $documentId = $document['id'] ?? 'unknown';
            $metadata = $document['metadata'] ?? [];

            if (empty($content)) {
                continue;
            }

            $chunks = $this->splitIntoChunks($content, $chunkSize, $overlap);

            foreach ($chunks as $index => $chunkText) {
                $allChunks[] = [
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                    'content' => $chunkText,
                    'metadata' => array_merge($metadata, [
                        'chunk_method' => 'fixed',
                        'chunk_size' => $chunkSize,
                        'overlap' => $overlap,
                        'total_chunks' => count($chunks),
                    ]),
                ];
            }
        }

        return [
            'chunks' => $allChunks,
            'total_chunks' => count($allChunks),
        ];
    }

    /**
     * Semantic chunking (respects paragraph and sentence boundaries)
     */
    private function chunkSemantic(NodePayload $payload): array
    {
        $documents = $payload->inputData['documents'] ?? [];
        $maxChunkSize = (int) ($payload->config['max_chunk_size'] ?? 1000);

        $allChunks = [];

        foreach ($documents as $document) {
            $content = $document['content'] ?? '';
            $documentId = $document['id'] ?? 'unknown';
            $metadata = $document['metadata'] ?? [];

            if (empty($content)) {
                continue;
            }

            $chunks = $this->semanticSplit($content, $maxChunkSize);

            foreach ($chunks as $index => $chunkText) {
                $allChunks[] = [
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                    'content' => $chunkText,
                    'metadata' => array_merge($metadata, [
                        'chunk_method' => 'semantic',
                        'max_chunk_size' => $maxChunkSize,
                        'total_chunks' => count($chunks),
                    ]),
                ];
            }
        }

        return [
            'chunks' => $allChunks,
            'total_chunks' => count($allChunks),
        ];
    }

    /**
     * Split text into fixed-size chunks with overlap
     *
     * @return array<string>
     */
    private function splitIntoChunks(string $text, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $length = strlen($text);
        $position = 0;

        while ($position < $length) {
            $chunk = substr($text, $position, $chunkSize);
            $chunks[] = $chunk;

            $position += $chunkSize - $overlap;

            // Avoid tiny trailing chunks
            if ($length - $position < $chunkSize / 2) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Split text semantically (by paragraphs and sentences)
     *
     * @return array<string>
     */
    private function semanticSplit(string $text, int $maxChunkSize): array
    {
        // Split by double newlines (paragraphs)
        $paragraphs = preg_split('/\n\n+/', $text);
        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if (empty($paragraph)) {
                continue;
            }

            // If adding this paragraph would exceed max size, save current chunk
            if (strlen($currentChunk) + strlen($paragraph) > $maxChunkSize && ! empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = '';
            }

            // If single paragraph exceeds max size, split by sentences
            if (strlen($paragraph) > $maxChunkSize) {
                $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);

                foreach ($sentences as $sentence) {
                    if (strlen($currentChunk) + strlen($sentence) > $maxChunkSize && ! empty($currentChunk)) {
                        $chunks[] = trim($currentChunk);
                        $currentChunk = '';
                    }

                    $currentChunk .= $sentence.' ';
                }
            } else {
                $currentChunk .= $paragraph."\n\n";
            }
        }

        // Add remaining chunk
        if (! empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }
}
