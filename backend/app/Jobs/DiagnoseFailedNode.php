<?php

namespace App\Jobs;

use App\Agents\Internal\ErrorDiagnosisAgent;
use App\Models\AiFixSuggestion;
use App\Models\Execution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * DiagnoseFailedNode — calls the AI ErrorDiagnosisAgent to explain why a
 * workflow node failed and suggest fixes.
 *
 * On success  → creates an AiFixSuggestion record with status='pending' (awaiting review).
 * On failure  → failed() creates an AiFixSuggestion record with status='failed' so the
 *               user can see that diagnosis was attempted but could not complete,
 *               rather than the failure disappearing silently.
 *
 * tries=1 because AI API calls are expensive and retrying a fundamentally bad
 * request (e.g. invalid node config) wastes credits. A transient error (API
 * timeout) will surface as a 'failed' suggestion, which the user can re-trigger.
 */
class DiagnoseFailedNode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        private int $executionId,
        private string $failedNodeKey,
        private string $errorMessage,
        private string $nodeType,
        private array $nodeConfig,
        private array $inputData,
    ) {
        $this->onQueue('long-running');
    }

    /**
     * Call the AI agent and persist the diagnosis result.
     *
     * Exceptions are no longer swallowed — they propagate so the queue
     * can record a proper failure and trigger failed() below.
     */
    public function handle(): void
    {
        $execution = Execution::query()->find($this->executionId);

        if ($execution === null) {
            return;
        }

        try {
            $agent = new ErrorDiagnosisAgent(
                errorMessage: $this->errorMessage,
                nodeType: $this->nodeType,
                nodeConfig: $this->nodeConfig,
                inputData: $this->inputData,
            );

            $response = $agent->prompt(
                'Diagnose the error and suggest fixes for this failed workflow node.',
            );

            AiFixSuggestion::query()->create([
                'workspace_id' => $execution->workspace_id,
                'execution_id' => $this->executionId,
                'workflow_id' => $execution->workflow_id,
                'failed_node_key' => $this->failedNodeKey,
                'error_message' => $this->errorMessage,
                'diagnosis' => $response['diagnosis'] ?? '',
                'suggestions' => $response['suggestions'] ?? [],
                'model_used' => config('ai.default', 'openai'),
                'tokens_used' => 0,
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::warning('DiagnoseFailedNode: AI diagnosis failed, marking suggestion as failed', [
                'execution_id' => $this->executionId,
                'node_key' => $this->failedNodeKey,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Called by the queue when the job permanently fails (after all tries are exhausted).
     *
     * Creates a visible AiFixSuggestion record with status='failed' so the user
     * knows diagnosis was attempted but did not succeed, rather than seeing nothing.
     */
    public function failed(\Throwable $exception): void
    {
        $execution = Execution::query()->find($this->executionId);

        if ($execution === null) {
            return;
        }

        AiFixSuggestion::query()->create([
            'workspace_id' => $execution->workspace_id,
            'execution_id' => $this->executionId,
            'workflow_id' => $execution->workflow_id,
            'failed_node_key' => $this->failedNodeKey,
            'error_message' => $this->errorMessage,
            'diagnosis' => '',
            'suggestions' => [],
            'model_used' => config('ai.default', 'openai'),
            'tokens_used' => 0,
            'status' => 'failed',
        ]);

        Log::warning('DiagnoseFailedNode: AI diagnosis permanently failed, suggestion marked as failed', [
            'execution_id' => $this->executionId,
            'node_key' => $this->failedNodeKey,
            'error' => $exception->getMessage(),
        ]);
    }
}
