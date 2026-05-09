<?php

namespace App\Services;

use App\Models\DocumentEmbedding;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

/**
 * Vector Store Service for RAG (Retrieval-Augmented Generation)
 * 
 * Handles storage and retrieval of document embeddings using pgvector.
 */
class VectorStoreService
{
    /**
     * Store embeddings for document chunks
     *
     * @param  array<int, array{content: string, metadata?: array}>  $chunks
     * @return int Number of embeddings stored
     */
    public function storeEmbeddings(
        Workspace $workspace,
        string $collectionName,
        string $documentId,
        array $chunks,
        ?string $provider = null,
        ?string $model = null
    ): int {
        $provider = $provider ?? Lab::OpenAI;
        $model = $model ?? 'text-embedding-ada-002';

        $texts = array_column($chunks, 'content');

        // Generate embeddings using Laravel AI SDK
        $embeddings = Embeddings::for($texts)
            ->generate($provider, $model)
            ->toArray();

        // Store each embedding
        $stored = 0;
        foreach ($chunks as $index => $chunk) {
            DocumentEmbedding::create([
                'workspace_id' => $workspace->id,
                'collection_name' => $collectionName,
                'document_id' => $documentId,
                'chunk_index' => $index,
                'content' => $chunk['content'],
                'embedding' => $embeddings[$index],
                'metadata' => $chunk['metadata'] ?? null,
            ]);
            $stored++;
        }

        return $stored;
    }

    /**
     * Similarity search using cosine distance
     *
     * @param  array<float>  $queryEmbedding
     * @return array<int, array{content: string, metadata: ?array, distance: float, document_id: string, chunk_index: int}>
     */
    public function similaritySearch(
        Workspace $workspace,
        string $collectionName,
        array $queryEmbedding,
        int $topK = 5,
        ?float $minSimilarity = null
    ): array {
        $embeddingStr = '['.implode(',', $queryEmbedding).']';

        // Query for similar documents using cosine distance
        // cosine distance = 1 - cosine similarity
        // Lower distance = higher similarity
        $query = DB::table('document_embeddings')
            ->select([
                'id',
                'document_id',
                'chunk_index',
                'content',
                'metadata',
                DB::raw("(embedding <=> '{$embeddingStr}'::vector) as distance"),
            ])
            ->where('workspace_id', $workspace->id)
            ->where('collection_name', $collectionName)
            ->orderBy('distance', 'asc')
            ->limit($topK);

        if ($minSimilarity !== null) {
            // Convert similarity threshold to distance threshold
            // If minSimilarity = 0.8, then maxDistance = 1 - 0.8 = 0.2
            $maxDistance = 1 - $minSimilarity;
            $query->having('distance', '<=', $maxDistance);
        }

        $results = $query->get();

        return $results->map(function ($result) {
            return [
                'content' => $result->content,
                'metadata' => json_decode($result->metadata, true),
                'distance' => (float) $result->distance,
                'similarity' => 1 - (float) $result->distance, // Convert distance to similarity
                'document_id' => $result->document_id,
                'chunk_index' => $result->chunk_index,
            ];
        })->toArray();
    }

    /**
     * Delete embeddings for a document
     */
    public function deleteDocument(Workspace $workspace, string $collectionName, string $documentId): int
    {
        return DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->where('collection_name', $collectionName)
            ->where('document_id', $documentId)
            ->delete();
    }

    /**
     * Delete an entire collection
     */
    public function deleteCollection(Workspace $workspace, string $collectionName): int
    {
        return DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->where('collection_name', $collectionName)
            ->delete();
    }

    /**
     * List all collections in a workspace
     *
     * @return array<string, int> Collection names with document counts
     */
    public function listCollections(Workspace $workspace): array
    {
        return DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->select('collection_name', DB::raw('count(DISTINCT document_id) as doc_count'))
            ->groupBy('collection_name')
            ->get()
            ->pluck('doc_count', 'collection_name')
            ->toArray();
    }

    /**
     * Get collection statistics
     *
     * @return array{total_documents: int, total_chunks: int, avg_chunks_per_doc: float}
     */
    public function getCollectionStats(Workspace $workspace, string $collectionName): array
    {
        $stats = DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->where('collection_name', $collectionName)
            ->select([
                DB::raw('count(DISTINCT document_id) as total_documents'),
                DB::raw('count(*) as total_chunks'),
            ])
            ->first();

        return [
            'total_documents' => (int) $stats->total_documents,
            'total_chunks' => (int) $stats->total_chunks,
            'avg_chunks_per_doc' => $stats->total_documents > 0
                ? round($stats->total_chunks / $stats->total_documents, 2)
                : 0,
        ];
    }
}
