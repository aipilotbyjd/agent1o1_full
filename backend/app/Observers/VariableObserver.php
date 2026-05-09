<?php

namespace App\Observers;

use App\Models\Variable;
use App\Services\ExecutionLogMaskingService;

class VariableObserver
{
    public function __construct(
        private ExecutionLogMaskingService $maskingService
    ) {}

    /**
     * Handle the Variable "saved" event.
     */
    public function saved(Variable $variable): void
    {
        if ($variable->is_secret) {
            $this->maskingService->clearCache($variable->workspace_id);
        }
    }

    /**
     * Handle the Variable "deleted" event.
     */
    public function deleted(Variable $variable): void
    {
        if ($variable->is_secret) {
            $this->maskingService->clearCache($variable->workspace_id);
        }
    }
}
