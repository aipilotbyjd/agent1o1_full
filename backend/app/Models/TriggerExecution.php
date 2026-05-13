<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriggerExecution extends Model
{
    /** @use HasFactory<\Database\Factories\TriggerExecutionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'trigger_id',
        'workflow_execution_id',
        'source',
        'triggered_at',
        'trigger_payload',
        'status',
        'error_message',
    ];

    protected $casts = [
        'trigger_payload' => 'array',
        'triggered_at' => 'datetime',
    ];

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(Trigger::class, 'trigger_id');
    }
}
