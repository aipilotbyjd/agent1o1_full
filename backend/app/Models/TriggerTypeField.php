<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriggerTypeField extends Model
{
    /** @use HasFactory<\Database\Factories\TriggerTypeFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'trigger_type_id',
        'field_name',
        'field_label',
        'field_type',
        'is_required',
        'is_secret',
        'placeholder',
        'help_text',
        'validation_regex',
        'options',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_secret' => 'boolean',
        'options' => 'array',
    ];

    public function triggerType(): BelongsTo
    {
        return $this->belongsTo(TriggerType::class, 'trigger_type_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(TriggerFieldValue::class, 'trigger_type_field_id');
    }
}
