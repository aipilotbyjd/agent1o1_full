<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionCheckpoint extends Model
{
    use HasUuid;

    protected $fillable = [
        'execution_id',
        'frontier_state',
        'output_refs',
        'frame_stack',
        'next_sequence',
        'suspend_reason',
        'resume_at',
        'webhook_wait_uuid',
        'resume_payload',
        'checkpoint_version',
    ];

    protected function casts(): array
    {
        return [
            'frontier_state' => 'array',
            'output_refs' => 'array',
            'frame_stack' => 'array',
            'next_sequence' => 'integer',
            'resume_at' => 'datetime',
            'resume_payload' => 'array',
            'checkpoint_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Execution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }
}
