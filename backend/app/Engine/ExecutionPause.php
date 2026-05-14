<?php

namespace App\Engine;

use Carbon\CarbonInterface;

class ExecutionPause
{
    /**
     * @param  array<string, mixed>  $nodeOutput
     */
    public function __construct(
        public readonly string $reason,
        public readonly CarbonInterface $resumeAt,
        public readonly array $nodeOutput = [],
        public readonly ?string $webhookWaitUuid = null,
    ) {}
}
