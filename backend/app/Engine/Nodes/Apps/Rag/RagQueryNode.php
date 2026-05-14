<?php

namespace App\Engine\Nodes\Apps\Rag;

use App\Agents\Internal\ChatAgent;
use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use App\Models\Workspace;
use App\Services\VectorStoreService;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

/**
 * RAG Query Node
 * 
 * Performs Retrieval-Augmented Generation:
 * 1. Generates embedding for the user query
 * 2. Searches for similar documents in the vector store
 * 3. Builds context from retrieved documents
 * 4. Calls LLM with context + query to generate answer
 * 5. Returns answer with citations
 */
class RagQueryNode extends AppNode
{
    public function __construct(private VectorStoreService $vectorStore) {}

    protected function errorCode(): string
    {
        return 'RAG_QUERY_ERROR';
    }

    protected function operations(): array
    {
        return [
            'query' => $this->query(...),
        ];
    }

    /**
     * Perform RAG query
     */
    private function query(NodeInput $payload): array
    {
        // Configuration
        $query = $payload->config['query'] ?? $payload->inputData['query'] ?? '';
        $collectionName = $payload->config['collection_name'] ?? 'default';
        $topK = (int) ($payload->config['top_k'] ?? 5);
        $minSimilarity = (float) ($payload->config['min_similarity'] ?? 0.7);
        $provider = $payload->config['provider'] ?? 'openai';
        $llmModel = $payload->config['llm_model'] ?? 'gpt-4o';
        $embeddingModel = $payload->config['embedding_model'] ?? 'text-embedding-ada-002';
        $systemPrompt = $payload->config['system_prompt'] ?? $this->getDefaultSystemPrompt();
        $includeCitations = (bool) ($payload->config['include_citations'] ?? true);

        if (empty($query)) {
            throw new \InvalidArgumentException('Query is required');
        }

        // Get workspace
        $workspaceId = $payload->executionMeta['workspace_id'] ?? null;

        if (! $workspaceId) {
            throw new \RuntimeException('Workspace ID not found in execution context');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            throw new \RuntimeException('Workspace not found');
        }

        // Step 1: Generate query embedding
        $queryEmbedding = Embeddings::for([$query])
            ->generate($this->resolveProvider($provider), $embeddingModel)
            ->toArray()[0];

        // Step 2: Search for similar documents
        $results = $this->vectorStore->similaritySearch(
            $workspace,
            $collectionName,
            $queryEmbedding,
            $topK,
            $minSimilarity
        );

        if (empty($results)) {
            return [
                'answer' => 'I could not find any relevant information to answer your question.',
                'sources' => [],
                'query' => $query,
                'collection_name' => $collectionName,
            ];
        }

        // Step 3: Build context from retrieved documents
        $context = $this->buildContext($results);

        // Step 4: Generate answer using LLM
        $prompt = $this->buildPrompt($query, $context, $systemPrompt);

        $agent = new ChatAgent(
            systemPrompt: $systemPrompt,
            provider: $this->resolveProvider($provider),
            model: $llmModel,
        );

        $response = $agent->prompt($prompt);

        // Step 5: Extract citations if requested
        $sources = $includeCitations ? $this->extractSources($results) : [];

        return [
            'answer' => $response->text(),
            'sources' => $sources,
            'query' => $query,
            'collection_name' => $collectionName,
            'retrieved_chunks' => count($results),
            'context_used' => strlen($context),
        ];
    }

    /**
     * Build context string from retrieved documents
     */
    private function buildContext(array $results): string
    {
        $contextParts = [];

        foreach ($results as $index => $result) {
            $contextParts[] = sprintf(
                "[Source %d] (Similarity: %.2f)\n%s",
                $index + 1,
                $result['similarity'],
                $result['content']
            );
        }

        return implode("\n\n---\n\n", $contextParts);
    }

    /**
     * Build the prompt for the LLM
     */
    private function buildPrompt(string $query, string $context, string $systemPrompt): string
    {
        return <<<PROMPT
Use the following context to answer the user's question. If the answer is not in the context, say so.

CONTEXT:
{$context}

QUESTION:
{$query}

Please provide a comprehensive answer based on the context above. If you reference specific information, mention which source it came from.
PROMPT;
    }

    /**
     * Extract source citations from results
     */
    private function extractSources(array $results): array
    {
        $sources = [];

        foreach ($results as $index => $result) {
            $metadata = $result['metadata'] ?? [];

            $sources[] = [
                'index' => $index + 1,
                'document_id' => $result['document_id'],
                'chunk_index' => $result['chunk_index'],
                'similarity' => round($result['similarity'], 3),
                'content_preview' => substr($result['content'], 0, 200).'...',
                'metadata' => $metadata,
            ];
        }

        return $sources;
    }

    /**
     * Get default system prompt for RAG
     */
    private function getDefaultSystemPrompt(): string
    {
        return <<<PROMPT
You are a helpful assistant that answers questions based on the provided context.

Guidelines:
- Always base your answers on the provided context
- If the context doesn't contain enough information to answer the question, say so
- Cite sources when referencing specific information
- Be clear, concise, and accurate
- If you're unsure, express that uncertainty
PROMPT;
    }

    /**
     * Resolve provider string to Lab enum
     */
    private function resolveProvider(string $provider): string
    {
        return match (strtolower($provider)) {
            'openai' => Lab::OpenAI,
            'anthropic', 'claude' => Lab::Anthropic,
            'gemini', 'google' => Lab::Gemini,
            'groq' => Lab::Groq,
            default => Lab::OpenAI,
        };
    }
}
