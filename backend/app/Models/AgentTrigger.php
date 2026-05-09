<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTrigger extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'agent_id',
        'workspace_id',
        'type',
        'config',
        'initial_message',
        'is_active',
        'last_fired_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'last_fired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
