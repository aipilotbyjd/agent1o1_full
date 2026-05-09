<?php

namespace App\Events;

use App\Models\Execution;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExecutionNodeFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Execution $execution,
        public string $nodeId,
        public string $nodeType,
        public string $errorMessage,
        public array $nodeConfig,
        public array $inputData
    ) {}
}
