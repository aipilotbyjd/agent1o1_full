<?php

namespace App\Http\Resources\Api\V1;

use App\Services\ExecutionLogMaskingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExecutionLog
 */
class ExecutionLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $maskingService = app(ExecutionLogMaskingService::class);
        $workspaceId = $this->execution->workspace_id;

        return [
            'id' => $this->id,
            'execution_id' => $this->execution_id,
            'execution_node_id' => $this->execution_node_id,
            'level' => $this->level,
            'message' => $maskingService->maskData($this->message, $workspaceId),
            'context' => $maskingService->maskData($this->context, $workspaceId),
            'logged_at' => $this->logged_at,
        ];
    }
}
