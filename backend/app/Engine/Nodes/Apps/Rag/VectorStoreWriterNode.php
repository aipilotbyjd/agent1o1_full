<?php

namespace App\Engine\Nodes\Apps\Rag;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use App\Models\Workspace;
use App\Services\VectorStoreService;

/**
 * Vector Store Writer Node
 * 
 * Stores document chunks as embeddings in the vector database.
 */
class VectorStoreWriterNode extends AppNode
{
    public function __construct(private VectorStoreService $vectorStore) {}

    protected function errorCode(): string
    {
        return 'VECTOR_STORE_WRITER_ERROR';
    }

    protected function operations(): array
    {
        return [
            'store' => $this->store(...),
            'delete_document' => $this->deleteDocument(...),
            'delete_collection' => $this->deleteCollection(...),
        ];
    }

    /**
     * Store chunks as embeddings
     */
    private function store(NodeInput $payload): array
    {
        $chunks = $payload->inputData['chunks'] ?? [];
        $collectionName = $payload->config['collection_name'] ?? 'default';
        $provider = $payload->config['provider'] ?? 'openai';
        $model = $payload->config['model'] ?? 'text-embedding-ada-002';

        if (empty($chunks)) {
            throw new \InvalidArgumentException('No chunks to store');
        }

        // Get workspace from execution context
        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;

        if (! $workspaceId) {
            throw new \RuntimeException('Workspace ID not found in execution context');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            throw new \RuntimeException('Workspace not found');
        }

        // Group chunks by document_id
        $chunksByDocument = [];
        foreach ($chunks as $chunk) {
            $documentId = $chunk['document_id'] ?? 'unknown';

            if (! isset($chunksByDocument[$documentId])) {
                $chunksByDocument[$documentId] = [];
            }

            $chunksByDocument[$documentId][] = [
                'content' => $chunk['content'],
                'metadata' => $chunk['metadata'] ?? [],
            ];
        }

        $totalStored = 0;

        // Store embeddings for each document
        foreach ($chunksByDocument as $documentId => $documentChunks) {
            $stored = $this->vectorStore->storeEmbeddings(
                $workspace,
                $collectionName,
                $documentId,
                $documentChunks,
                $provider,
                $model
            );

            $totalStored += $stored;
        }

        return [
            'collection_name' => $collectionName,
            'documents_processed' => count($chunksByDocument),
            'chunks_stored' => $totalStored,
            'provider' => $provider,
            'model' => $model,
        ];
    }

    /**
     * Delete a document from the vector store
     */
    private function deleteDocument(NodeInput $payload): array
    {
        $documentId = $payload->config['document_id'] ?? '';
        $collectionName = $payload->config['collection_name'] ?? 'default';

        if (empty($documentId)) {
            throw new \InvalidArgumentException('Document ID is required');
        }

        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;

        if (! $workspaceId) {
            throw new \RuntimeException('Workspace ID not found in execution context');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            throw new \RuntimeException('Workspace not found');
        }

        $deleted = $this->vectorStore->deleteDocument($workspace, $collectionName, $documentId);

        return [
            'collection_name' => $collectionName,
            'document_id' => $documentId,
            'chunks_deleted' => $deleted,
        ];
    }

    /**
     * Delete an entire collection
     */
    private function deleteCollection(NodeInput $payload): array
    {
        $collectionName = $payload->config['collection_name'] ?? '';

        if (empty($collectionName)) {
            throw new \InvalidArgumentException('Collection name is required');
        }

        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;

        if (! $workspaceId) {
            throw new \RuntimeException('Workspace ID not found in execution context');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            throw new \RuntimeException('Workspace not found');
        }

        $deleted = $this->vectorStore->deleteCollection($workspace, $collectionName);

        return [
            'collection_name' => $collectionName,
            'chunks_deleted' => $deleted,
        ];
    }
}
