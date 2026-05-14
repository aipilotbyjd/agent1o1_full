<?php

namespace App\Jobs;

use App\Engine\WorkflowRunner;
use App\Enums\ExecutionStatus;
use App\Models\Execution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $maxExceptions = 1;

    /**
     * Exponential backoff: wait 10s then 60s before giving up.
     * Short first retry catches transient DB/lock issues.
     * Longer second retry handles brief external API outages.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function __construct(public Execution $execution)
    {
        $this->onQueue('workflows-default');
    }

    public function handle(WorkflowRunner $engine): void
    {
        $this->execution->refresh();

        if ($this->execution->status !== ExecutionStatus::Pending) {
            Log::info("ExecuteWorkflowJob skipped — execution {$this->execution->id} is not pending.", [
                'status' => $this->execution->status->value,
            ]);

            return;
        }

        $engine->run($this->execution);
    }

    public function failed(\Throwable $exception): void
    {
        $this->execution->fail(['message' => $exception->getMessage()]);
    }
}
