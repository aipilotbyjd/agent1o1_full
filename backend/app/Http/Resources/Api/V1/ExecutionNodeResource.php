<?php

namespace App\Http\Resources\Api\V1;

use App\Services\ExecutionLogMaskingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExecutionNode
 */
class ExecutionNodeResource extends JsonResource
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
            'node_id' => $this->node_id,
            'node_type' => $this->node_type,
            'node_name' => $this->node_name,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_ms' => $this->duration_ms,
            'input_data' => $maskingService->maskData($this->input_data, $workspaceId),
            'output_data' => $maskingService->maskData($this->output_data, $workspaceId),
            'error' => $maskingService->maskData($this->error, $workspaceId),
            'sequence' => $this->sequence,
        ];
    }
}
