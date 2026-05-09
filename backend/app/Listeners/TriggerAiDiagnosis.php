<?php

namespace App\Listeners;

use App\Events\ExecutionNodeFailed;
use App\Jobs\DiagnoseFailedNode;
use App\Mail\ExecutionFailedNotification;
use Illuminate\Support\Facades\Mail;

class TriggerAiDiagnosis
{
    /**
     * Handle the event.
     */
    public function handle(ExecutionNodeFailed $event): void
    {
        DiagnoseFailedNode::dispatch(
            $event->execution->id,
            $event->nodeId,
            $event->errorMessage,
            $event->nodeType,
            $event->nodeConfig,
            $event->inputData
        );

        // Load relations for email
        $event->execution->loadMissing(['workflow.workspace.owner']);
        $owner = $event->execution->workflow->workspace->owner ?? null;
        
        if ($owner) {
            Mail::to($owner->email)->queue(new ExecutionFailedNotification($event->execution, $event->execution->workflow));
        }
    }
}
