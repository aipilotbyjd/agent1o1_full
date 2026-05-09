<?php

namespace App\Engine\Execution;

use Carbon\CarbonInterface;

/**
 * Represents a node suspension request during async execution.
 *
 * Nodes that implement SuspendsExecution return a Suspension to
 * signal that execution should pause and resume at a later time.
 */
class Suspension
{
    /**
     * @param  array<string, mixed>  $nodeOutput
     * @param  string|null  $webhookWaitUuid  Set by WaitNode(webhook mode) — stored on
     *                                        the checkpoint so the /webhook-wait/{uuid}
     *                                        route can look up and resume this execution.
     */
    public function __construct(
        public readonly string $reason,
        public readonly CarbonInterface $resumeAt,
        public readonly array $nodeOutput = [],
        public readonly ?string $webhookWaitUuid = null,
    ) {}
}
