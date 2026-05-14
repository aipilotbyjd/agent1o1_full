<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriggerType extends Model
{
    /** @use HasFactory<\Database\Factories\TriggerTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'slug',
        'name',
        'description',
        'execution_mode',
        'zapier_mode',
        'requires_credential',
        'requires_config_fields',
        'webhook_events',
        'is_active',
    ];

    protected $casts = [
        'requires_credential' => 'boolean',
        'requires_config_fields' => 'boolean',
        'webhook_events' => 'array',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TriggerCategory::class, 'category_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TriggerTypeField::class, 'trigger_type_id');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(Trigger::class, 'trigger_type_id');
    }

    public function isWebhookBased(): bool
    {
        return $this->execution_mode === 'webhook';
    }

    public function isPollingBased(): bool
    {
        return $this->execution_mode === 'polling';
    }

    public function isManualTrigger(): bool
    {
        return $this->execution_mode === 'manual';
    }
}
