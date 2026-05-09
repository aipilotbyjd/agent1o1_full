<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ResumeWorkflowJob;
use App\Models\ExecutionCheckpoint;
use App\Enums\ExecutionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives the resume signal for a Wait node in webhook mode.
 *
 * When a workflow reaches a Wait node configured with mode=webhook, the engine:
 *  1. Generates a unique UUID and stores it on the execution checkpoint
 *  2. Suspends the execution (status = Waiting)
 *  3. Returns the resume URL to the workflow caller
 *
 * When an external system hits POST /api/v1/webhook-wait/{uuid}, this controller:
 *  1. Looks up the checkpoint by webhook_wait_uuid (404 if not found)
 *  2. Verifies the execution is still Waiting (409 if already resumed)
 *  3. Stores the incoming request payload as resume_payload on the checkpoint
 *     so the engine can inject it as the WaitNode's output on resume
 *  4. Dispatches ResumeWorkflowJob immediately
 *  5. Returns 200 — the workflow resumes asynchronously
 */
class WaitWebhookController
{
    public function resume(Request $request, string $uuid): JsonResponse
    {
        $checkpoint = ExecutionCheckpoint::where('webhook_wait_uuid', $uuid)
            ->with('execution')
            ->first();

        if (! $checkpoint) {
            return response()->json(['error' => 'Wait webhook not found or already consumed.'], 404);
        }

        $execution = $checkpoint->execution;

        if (! $execution) {
            return response()->json(['error' => 'Execution not found.'], 404);
        }

        if ($execution->status !== ExecutionStatus::Waiting) {
            return response()->json([
                'error' => 'Execution is no longer waiting.',
                'status' => $execution->status->value,
            ], 409);
        }

        // Store the incoming payload so the engine can inject it as the WaitNode output.
        $checkpoint->update([
            'resume_payload' => [
                'method' => $request->method(),
                'headers' => $this->normalizeHeaders($request),
                'query' => $request->query(),
                'body' => $request->all(),
            ],
        ]);

        // Dispatch the resume job immediately — the long-delayed failsafe job
        // dispatched by the engine will see status !== Waiting and skip itself.
        ResumeWorkflowJob::dispatch($execution);

        return response()->json([
            'success' => true,
            'message' => 'Workflow resumed.',
            'execution_id' => $execution->id,
        ]);
    }

    private function normalizeHeaders(Request $request): array
    {
        return array_map(
            fn ($values) => is_array($values) ? ($values[0] ?? '') : $values,
            $request->headers->all(),
        );
    }
}
