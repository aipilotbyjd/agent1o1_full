<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trigger extends Model
{
    /** @use HasFactory<\Database\Factories\TriggerFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'workflow_id',
        'workspace_id',
        'trigger_type_id',
        'trigger_category_id',
        'credential_id',
        'name',
        'is_active',
        'is_published',
        'webhook_uuid',
        'webhook_provider',
        'webhook_external_id',
        'webhook_secret',
        'webhook_registered_url',
        'webhook_status',
        'webhook_status_message',
        'polling_interval_seconds',
        'polling_last_check_at',
        'polling_last_seen_ids',
        'polling_endpoint_url',
        'schedule_expression',
        'schedule_next_run_at',
        'schedule_timezone',
        'schedule_last_run_at',
        'last_error',
        'last_error_at',
        'consecutive_errors',
        'trigger_count',
        'last_triggered_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'webhook_secret' => 'encrypted',
        'polling_last_seen_ids' => 'array',
        'polling_last_check_at' => 'datetime',
        'schedule_next_run_at' => 'datetime',
        'schedule_last_run_at' => 'datetime',
        'last_error_at' => 'datetime',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = [
        'webhook_secret',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function triggerType(): BelongsTo
    {
        return $this->belongsTo(TriggerType::class, 'trigger_type_id');
    }

    public function triggerCategory(): BelongsTo
    {
        return $this->belongsTo(TriggerCategory::class, 'trigger_category_id');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class, 'credential_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(TriggerFieldValue::class, 'trigger_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(TriggerExecution::class, 'trigger_id');
    }

    public function isWebhookBased(): bool
    {
        return $this->triggerType->execution_mode === 'webhook';
    }

    public function isPollingBased(): bool
    {
        return $this->triggerType->execution_mode === 'polling';
    }

    public function isManualTrigger(): bool
    {
        return $this->triggerType->execution_mode === 'manual';
    }

    public function getFieldValue(string $fieldName): ?string
    {
        return $this->fieldValues()
            ->whereHas('field', fn ($q) => $q->where('field_name', $fieldName))
            ->first()?->value;
    }

    public function getFieldValues(): array
    {
        return $this->fieldValues
            ->mapWithKeys(fn ($fv) => [$fv->field->field_name => $fv->value])
            ->toArray();
    }
}
