<?php

namespace App\Listeners;

use App\Events\ExecutionNodeFailed;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listens for ExecutionNodeFailed events and dispatches the user notification.
 *
 * Queued on the 'notifications' queue so it never blocks the engine.
 */
class SendExecutionFailedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(ExecutionNodeFailed $event): void
    {
        $this->notificationService->notifyExecutionFailed(
            execution: $event->execution,
            errorMessage: $event->errorMessage,
        );
    }
}
