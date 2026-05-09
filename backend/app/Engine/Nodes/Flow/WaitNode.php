<?php

namespace App\Engine\Nodes\Flow;

use App\Engine\Contracts\NodeHandler;
use App\Engine\Contracts\SuspendsExecution;
use App\Engine\Execution\Suspension;
use App\Engine\NodeResult;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Str;

/**
 * Wait node — pauses a workflow until one of two conditions is met:
 *
 *  duration mode  (config['mode'] = 'duration' or absent)
 *    Pauses for a fixed number of seconds, then resumes automatically.
 *    Behaviour is identical to DelayNode: checkpoint + delayed ResumeWorkflowJob.
 *
 *  webhook mode   (config['mode'] = 'webhook')
 *    Pauses indefinitely until an HTTP request arrives at:
 *      POST /api/v1/webhook-wait/{uuid}
 *    The request body is injected as the node's output so downstream
 *    nodes can read it via $item['body'], $item['headers'], etc.
 *
 *    A ResumeWorkflowJob with a 10-year delay is still dispatched as a
 *    failsafe. The /webhook-wait route dispatches an immediate one when
 *    called. Because ResumeWorkflowJob skips executions not in Waiting
 *    state, whichever job arrives second is a safe no-op.
 */
class WaitNode implements NodeHandler, SuspendsExecution
{
    public function handle(NodePayload $payload): NodeResult
    {
        $mode = $payload->config['mode'] ?? 'duration';

        if ($mode === 'webhook') {
            return NodeResult::completed([
                'mode' => 'webhook',
                'waiting_for_webhook' => true,
            ]);
        }

        $seconds = (int) ($payload->config['delay_seconds'] ?? $payload->config['seconds'] ?? 0);

        return NodeResult::completed([
            'mode' => 'duration',
            'delayed_seconds' => $seconds,
            'scheduled_at' => now()->toIso8601String(),
        ]);
    }

    public function suspend(NodePayload $payload): Suspension
    {
        $mode = $payload->config['mode'] ?? 'duration';

        if ($mode === 'webhook') {
            return new Suspension(
                reason: 'webhook_wait',
                // Far future so the auto-dispatched ResumeWorkflowJob
                // effectively never fires — the webhook route dispatches
                // the real immediate resume.
                resumeAt: now()->addYears(10),
                nodeOutput: [
                    'mode' => 'webhook',
                    'waiting_for_webhook' => true,
                ],
                webhookWaitUuid: (string) Str::uuid(),
            );
        }

        $seconds = (int) ($payload->config['delay_seconds'] ?? $payload->config['seconds'] ?? 0);

        return new Suspension(
            reason: 'delay',
            resumeAt: now()->addSeconds(max($seconds, 0)),
            nodeOutput: [
                'mode' => 'duration',
                'delayed_seconds' => $seconds,
                'scheduled_at' => now()->toIso8601String(),
            ],
        );
    }
}
